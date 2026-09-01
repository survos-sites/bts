<?php

use Castor\Attribute\AsTask;
use Survos\CoreBundle\Service\SurvosUtils;
use Survos\JsonlBundle\IO\JsonlReader;
use Survos\MeiliBundle\Model\Dataset;

use function Castor\{io, run, capture, import, http_download};
$autoloadCandidates = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}
use League\Csv\Reader;
use Castor\Attribute\{AsOption,AsArgument};

try {
    import('.castor/vendor/tacman/castor-tools/castor.php');
} catch (Throwable $e) {
    io()->error('castor composer install');
    io()->error($e->getMessage());
}

const EN_FILENAME='data/amst_english.csv';
const APP_NAMESPACE='app';

/**
 * Return the available demo datasets, keyed by code.
 *
 * @return array<string, Dataset>
 */
function demo_datasets(): array
{
        $datasets = [
            new Dataset(
                name: 'amst',
                url: 'https://statics.belowthesurface.amsterdam/downloadbare-datasets/Downloadtabel_NL.csv',
                target: 'data/amst.csv',
            ),
        ];
    foreach ($datasets as $dataset) {
        $map[$dataset->name] = $dataset;
    }
    return $map;
}

#[AsTask('build', APP_NAMESPACE, "download and load")]
function build(
    #[AsOption(description: "limit the number of records to import")] ?int $limit=50,
    #[AsOption(description: "Update the sqlite database")] ?bool $sqlite=null,
): void
{
    run("bin/console d:sc:update --force");
    download(); // download the data
    createDictionary(); // download the English and create the map
    load_database($limit); // exports to .jsonl and then imports
    translate('en');
}

#[AsTask('translate', APP_NAMESPACE, "Translate the strings to English in babel")]
function translate(
    #[AsOption(description: "to which language")] string $locale='en'
): void
{
    run('bin/console babel:translate ' . $locale);
}

#[AsTask('download', APP_NAMESPACE, "Download the nl and en csv files")]
function download(?string $code=null): void
{
    if (!file_exists(EN_FILENAME)) {
        $translationUrl = 'https://statics.belowthesurface.amsterdam/downloadbare-datasets/Downloadtabel_EN.csv';
        http_download($translationUrl, EN_FILENAME);
    }

    $map = demo_datasets();
    if ($code && !array_key_exists($code, $map)) {
        io()->error("The code '{$code}' does not exist: " . implode('|', array_keys($map)));
        return;
    }
    $datasets = $code ? [$map[$code]] : array_values($map);
    foreach ($datasets as $dataset) {
        // use fs()?
        if ($dataset->url) {
            if (!file_exists($dataset->target)) {
                $dir = \dirname($dataset->target);
                if ($dir !== '' && !\is_dir($dir)) {
                    \mkdir($dir, 0777, true);
                }

                io()->writeln(sprintf('Downloading %s → %s', $dataset->url, $dataset->target));
                http_download($dataset->url, $dataset->target);
                io()->writeln(realpath($dataset->target) . ' written');
                if ($dataset->afterDownload) {
                    io()->warning($dataset->afterDownload);
                    run($dataset->afterDownload);
                }

            } else {
                io()->writeln(sprintf('Target %s already exists, skipping download.', $dataset->target));
            }
        }
    }

    // now create a k/v lookup table using nl as the source

}

#[AsTask('dictionary', APP_NAMESPACE, "create the .jsonl dictionary file used by babel:translate listener")]
function createDictionary()
{
    $enReader = Reader::from('data/amst_english.csv');
    $enReader->setHeaderOffset(0);

    $nlReader = Reader::from('data/amst.csv');
    $nlReader->setHeaderOffset(0);

    $dict = [];

// Use MultipleIterator to keep both in sync
    $multiIterator = new MultipleIterator(MultipleIterator::MIT_NEED_ALL);
    $multiIterator->attachIterator($nlReader->getRecords());
    $multiIterator->attachIterator($enReader->getRecords());
    io()->title("Creating English-language dictionary");
    $filename = 'data/amst_dictionary.json';
    if (file_exists($filename)) {
        io()->warning("The file '{$filename}' already exists");
        return;
    }

    foreach ($multiIterator as [$nlRecord, $enRecord]) {
        // Sanity check: ensure rows are aligned
        assert($nlRecord['vondstnummer'] === $enRecord['vondstnummer'],
            sprintf('Row mismatch: %s vs %s', $nlRecord['vondstnummer'], $enRecord['vondstnummer']));

        foreach (array_keys($nlRecord) as $field) {
            $nlValue = trim($nlRecord[$field] ?? '');
            $enValue = trim($enRecord[$field] ?? '');
            // skip numbers, blanks, etc.
            if (is_numeric($nlValue) || empty($nlValue)) {
                continue;
            }
            if (in_array($field, ['vondstnummer', 'project_code', 'categorie', 'vak', 'vlak',
                'rookpijpen_productiecentrum',
                'rookpijpen_pijpenmaker',
                'glas_ds_type',
                'aardewerk_ds_type',
                'aardewerk_herkomst',
                'leer_archeologischobjecttype',
                'past_aan_hoort_bij',
                ])) {
                continue; // skip code fields, really should be translatable fields!
            }
            if ($nlValue  == $enValue) {
//                dump(field: $field, value: $nlValue);
//                continue;
            }

            if ($nlValue !== '' && $enValue !== '' /* && $nlValue !== $enValue */) {
                $dict[$nlValue] = $enValue;
            }
            // @todo: split the lists, e.g.
            // "tekst; wapen; kroon; Koninkrijk der Nederlanden; merklood" => "text; coat of arms; crown; Kingdom of the Netherlands; product seal"
        }
    }
    file_put_contents($filename, json_encode($dict, JSON_PRETTY_PRINT));
    io()->writeln("data/amst_dictionary.json written");
}

/**
 * Loads the database for a given demo dataset:
 *   - downloads the raw dataset (if needed)
 *   - runs import:convert to produce JSONL + profile
 *   - runs import:entities to import into Doctrine
 *
 * Code generation (code:entity) remains a separate, explicit step.
 */
#[AsTask('load', description: 'Loads the database for a demo dataset')]
function load_database(
    #[AsOption(description: 'Limit number of entities to import')]
    ?int $limit = null,
    #[AsOption(description: "reset the database")] bool $reset = false
): void {
    $code = 'amst';
    /** @var array<string, Dataset> $map */
    $map = demo_datasets();
    $dataset = $map[$code];

    if (!$dataset->jsonl) {
        io()->warning("stopped, no jsonl, maybe run another command?");
        return;
    }
    if (!file_exists($dataset->jsonl)) {
        $cmd = 'bin/console import:convert --no-debug %s --dataset=%s';
        $convertCmd = sprintf(
            $cmd,
            $dataset->target,
            $dataset->name
        );
        io()->writeln($convertCmd);
        run($convertCmd);
    }

    // create the indices so load will populate them
    run('bin/console meili:settings:update --force');

    // 4) Import entities into Doctrine.
    //
    // Note: This assumes your entity class is App\Entity\<Code>, e.g. App\Entity\Car
    // and that you've already generated the entity via code:entity.
    $limitArg = $limit ? sprintf(' --limit=%d', $limit) : '';
    $cmd = 'bin/console import:entities %s %s%s';
    if ($limit) {
        $cmd .= ' --limit ' . $limit;
    }
    if ($reset) {
        $cmd .= ' --reset';
    }
    $importCmd = sprintf(
        $cmd,
        ucfirst($code),
        $dataset->jsonl,
        $limitArg
    );
    io()->writeln($importCmd);
    run($importCmd);
    // In the new world, code generation (code:entity) and templates are explicit steps.
    // This Castor task focuses on:
    //   download → convert/profile → import.
}
