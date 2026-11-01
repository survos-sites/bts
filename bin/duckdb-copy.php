<?php

declare(strict_types=1);

// Copy an existing local archive into an EMPTY, schema-created DuckDB database.
use App\Doctrine\BtsDuckDbPlatform;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
if (2 !== $argc || !is_file($argv[1])) {
    throw new InvalidArgumentException('Usage: DATABASE_URL=duckdb://... php bin/duckdb-copy.php /absolute/path/to/archive.sqlite');
}
$source = new PDO('sqlite:file:'.realpath($argv[1]).'?mode=ro', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$kernel = new Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$platform = $db->getDatabasePlatform();
if (!$platform instanceof BtsDuckDbPlatform) {
    throw new RuntimeException('The target connection must be the local DuckDB driver.');
}
$sourceTables = $source->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
$counts = [];
$source->beginTransaction();
$db->beginTransaction();
try {
    foreach ($db->createSchemaManager()->listTableNames() as $table) {
        // New FetchBundle tables start empty. Never duplicate queued jobs.
        if (in_array($table, ['fetch_page', 'fetch_record', 'messenger_messages'], true)) {
            continue;
        }
        if (!in_array($table, $sourceTables, true)) {
            throw new RuntimeException('Source archive has no table '.$table);
        }
        $name = $platform->quoteIdentifier($table);
        if (0 !== (int) $db->fetchOne('SELECT count(*) FROM '.$name)) {
            throw new RuntimeException('Target must be empty: '.$table);
        }
        $rows = $source->query('SELECT * FROM '.$name.('amst' === $table ? ' WHERE website = 1' : ''));
        $count = 0;
        $insert = null;
        while (false !== $row = $rows->fetch(PDO::FETCH_ASSOC)) {
            if (null === $insert) {
                $columns = array_map($platform->quoteIdentifier(...), array_keys($row));
                $insert = $db->prepare('INSERT INTO '.$name.' ('.implode(', ', $columns).') VALUES ('.implode(', ', array_fill(0, count($columns), '?')).')');
            }
            foreach (array_values($row) as $position => $value) {
                $type = match (true) {
                    null === $value => \Doctrine\DBAL\ParameterType::NULL,
                    is_int($value) => \Doctrine\DBAL\ParameterType::INTEGER,
                    default => \Doctrine\DBAL\ParameterType::STRING,
                };
                $insert->bindValue($position + 1, $value, $type);
            }
            $insert->executeStatement();
            ++$count;
        }
        if ($count !== (int) $db->fetchOne('SELECT count(*) FROM '.$name)) {
            throw new RuntimeException('Row-count mismatch for '.$table);
        }
        $counts[$table] = $count;
    }
    foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
        if ($metadata->isMappedSuperclass || !$metadata->isIdGeneratorSequence()) {
            continue;
        }
        $max = (int) $db->fetchOne('SELECT max('.$platform->quoteIdentifier($metadata->getSingleIdentifierColumnName()).') FROM '.$platform->quoteIdentifier($metadata->getTableName()));
        if ($max > 0) {
            // Explicit imported IDs do not advance DuckDB sequences.
            $db->fetchOne('SELECT max(nextval(?)) FROM range(?)', [$metadata->sequenceGeneratorDefinition['sequenceName'], $max]);
        }
    }
    $db->commit();
    $source->commit();
    $db->executeStatement('CHECKPOINT');
    echo json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    if ($db->isTransactionActive()) {
        $db->rollBack();
    }
    if ($source->inTransaction()) {
        $source->rollBack();
    }
    $db->close();
    $kernel->shutdown();
}
