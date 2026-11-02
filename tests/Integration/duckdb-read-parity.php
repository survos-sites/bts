<?php

declare(strict_types=1);

use App\Kernel;
use App\Entity\Amst;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
// Symfony sessions require headers to remain available across these CLI HTTP requests.
ob_start();
if (3 !== $argc) {
    throw new InvalidArgumentException('Pass the SQLite archive URL and local DuckDB URL.');
}

final class PublishedArchiveFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        return Amst::class === $targetEntity->getName() ? $targetTableAlias.'.website = 1' : '';
    }
}

function requestSnapshot(string $url, string $path): array
{
    putenv('DATABASE_URL='.$url);
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $url;
    $kernel = new Kernel('dev', false);
    $kernel->boot();
    if (str_starts_with($url, 'sqlite:')) {
        // Compare DuckDB with the selected published subset of the untouched source archive.
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $em->getConfiguration()->addFilter('published_archive', PublishedArchiveFilter::class);
        $em->getFilters()->enable('published_archive');
    }
    $request = Request::create($path);
    try {
        $response = $kernel->handle($request);
        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('Request failed: '.$path.' ('.$response->getStatusCode().')');
        }
        $content = $response->getContent();
        if (str_starts_with($path, '/api/')) {
            return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        }
        $crawler = new Symfony\Component\DomCrawler\Crawler($content);
        return [
            'selectedLabels' => $crawler->filter('.ux-search-current-refinements__value')->each(static fn ($node) => trim($node->text())),
            'removeValues' => $crawler->filter('.ux-search-current-refinements__remove[data-live-value-param]')->each(static fn ($node) => $node->attr('data-live-value-param')),
            'categoryValues' => $crawler->filter('input[data-live-property-param="categorie"]')->each(static fn ($node) => $node->attr('value')),
            'categoryLabels' => $crawler->filter('.ux-search-refinement-list__label[for^="categorie-"] .ux-search-refinement-list__label-text')->each(static fn ($node) => trim($node->text())),
            'categoryBadges' => $crawler->filter('article .badge.bg-blue-lt')->each(static fn ($node) => trim($node->text())),
            'headings' => $crawler->filter('h1, h2')->each(static fn ($node) => trim($node->text())),
            'total' => $crawler->filter('.ux-search-total-hits')->each(static fn ($node) => trim($node->text())),
            'cards' => $crawler->filter('article')->each(static fn ($node) => preg_replace('/\s+/', ' ', trim($node->text()))),
        ];
    } finally {
        if (isset($response)) {
            $kernel->terminate($request, $response);
        }
        $kernel->shutdown();
    }
}

$paths = [
    '/en/', '/nl/', '/en/search', '/nl/search',
    '/en/search?query=munt', '/en/search?page=2',
    '/en/search?query=bottle', '/nl/search?query=fles',
    '/en/search?query=bottle&page=2', '/en/search?query=bottle&categorie=GLS',
    '/en/search?categorie=MTL', '/nl/search?categorie=CER',
    '/api/amsts.jsonld', '/api/amsts/NZC1-00002MTL001.jsonld',
];
foreach ($paths as $path) {
    $archive = requestSnapshot($argv[1], $path);
    $duck = requestSnapshot($argv[2], $path);
    if ($archive !== $duck) {
        throw new RuntimeException('Response parity failed: '.$path);
    }
    echo 'PASS '.$path.PHP_EOL;
}
$english = requestSnapshot($argv[2], '/en/search');
$dutch = requestSnapshot($argv[2], '/nl/search');
if (!in_array('miniature', $english['headings'], true) || !in_array('miniatuur', $dutch['headings'], true)) {
    throw new RuntimeException('English/Dutch entity translation hydration failed.');
}
if (!in_array('Metal', $english['categoryLabels'], true) || !in_array('Metaal', $dutch['categoryLabels'], true)
    || 'Metal' !== $english['categoryBadges'][0] || 'Metaal' !== $dutch['categoryBadges'][0]) {
    throw new RuntimeException('Category labels must follow the selected locale on facets and cards.');
}
foreach (['en' => 'Metal', 'nl' => 'Metaal'] as $locale => $label) {
    $selected = requestSnapshot($argv[2], '/'.$locale.'/search?categorie=MTL');
    if ([$label] !== $selected['selectedLabels'] || ['MTL'] !== $selected['removeValues']
        || !in_array('MTL', $selected['categoryValues'], true)) {
        throw new RuntimeException('Category label overrides must preserve raw facet and removal values in '.$locale.'.');
    }
}
echo "PASS English and Dutch entity, facet and selected-filter labels preserve category codes.\n";

$englishBottles = requestSnapshot($argv[2], '/en/search?query=bottle');
$dutchBottles = requestSnapshot($argv[2], '/nl/search?query=fles');
if (!in_array('mineral water bottle', $englishBottles['headings'], true)
    || !in_array('mineraalwaterfles', $dutchBottles['headings'], true)) {
    throw new RuntimeException('Instant Search must match translated English and source Dutch bottle names.');
}
if ([] !== requestSnapshot($argv[2], '/nl/search?query=bottle')['cards']) {
    throw new RuntimeException('Dutch Instant Search must not match an English-only translation.');
}
echo "PASS English bottle and Dutch fles search their displayed language.\n";

ob_end_flush();
