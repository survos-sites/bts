<?php

declare(strict_types=1);

namespace App\Tests;

use Survos\ImportBundle\Event\ImportConvertRowEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PublishedFindImportTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        (new \Doctrine\ORM\Tools\SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    public function testConversionCallbackSkipsUnpublishedFinds(): void
    {
        $event = new ImportConvertRowEvent(
            ['vondstnummer' => 'NZD1.00007LEE003', 'website' => '0', 'object' => 'schoen'],
            input: 'archive.csv', format: 'csv', index: 1, dataset: 'amst',
        );
        self::getContainer()->get(EventDispatcherInterface::class)->dispatch($event);
        self::assertNull($event->row);
    }

    public function testPublishedFindRetainsSourceLinkAndImage(): void
    {
        $event = new ImportConvertRowEvent(
            ['vondstnummer' => 'NZC1.00002MTL001', 'website' => '1', 'object' => 'miniatuur', 'categorie' => ' mtl '],
            input: 'archive.csv', format: 'csv', index: 1, dataset: 'amst',
        );
        self::getContainer()->get(EventDispatcherInterface::class)->dispatch($event);
        self::assertNotNull($event->row);
        self::assertSame('MTL', $event->row['categorie']);
        self::assertSame('NZC1-00002MTL001', $event->row['code']);
        self::assertSame('https://belowthesurface.amsterdam/nl/vondst/NZC1.00002MTL001', $event->row['citation_url']);
        self::assertSame('https://statics.belowthesurface.amsterdam/vondst/600/NZC1.00002MTL001(01).png', $event->row['image']);
    }
}
