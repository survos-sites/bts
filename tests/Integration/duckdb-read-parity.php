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
echo "PASS English and Dutch translations remain distinct.\n";

ob_end_flush();
