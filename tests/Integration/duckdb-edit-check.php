<?php

declare(strict_types=1);

use App\Entity\Amst;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
if (!$db->getDatabasePlatform() instanceof App\Doctrine\BtsDuckDbPlatform) {
    throw new RuntimeException('Run this check against the local DuckDB database.');
}
$code = 'DUCK-'.bin2hex(random_bytes(6));
try {
    $record = new Amst();
    $record->code = $code;
    $record->vondstnummer = 'duckdb-insert';
    $record->object = 'munt';
    $record->projectCode = 'NZC1';
    $record->categorie = 'MTL';
    $record->website = 0;
    $em->persist($record);
    $em->flush();
    $em->clear();
    $record = $em->find(Amst::class, $code);
    if ('duckdb-insert' !== $record->vondstnummer) {
        throw new RuntimeException('ORM insert did not persist.');
    }
    if ('coin' !== $record->object) {
        throw new RuntimeException('English translation was lost after ORM insert: '.$record->object);
    }
    $record->vondstnummer = 'duckdb-update';
    $em->flush();
    $em->clear();
    $record = $em->find(Amst::class, $code);
    if ('duckdb-update' !== $record->vondstnummer || 'coin' !== $record->object) {
        throw new RuntimeException('ORM update or translation hydration failed.');
    }
    $em->remove($record);
    $em->flush();
    $em->clear();
    if (null !== $em->find(Amst::class, $code)) {
        throw new RuntimeException('ORM delete did not persist.');
    }
    echo "PASS ORM insert, update, delete, and English translation after edits.\n";
} finally {
    $db->executeStatement('DELETE FROM amst WHERE code = ?', [$code]);
    $db->close();
    $kernel->shutdown();
}
