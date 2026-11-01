<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\RecordJson;
use PHPUnit\Framework\TestCase;

final class RecordJsonTest extends TestCase
{
    public function testSparseViewerKeepsRealZeroAndFalseValues(): void
    {
        $record = (object) [
            'code' => 'NZD1-00007LEE003', 'image' => null, 'description' => '',
            'website' => 0, 'published' => false, 'tags' => [],
            'empty' => (object) [], 'nested' => ['missing' => null, 'depth' => 0],
            'list' => [null, 'shoe', '', false, [], '0'],
        ];
        $json = (new RecordJson())->sparseJson($record);
        self::assertSame([
            'code' => 'NZD1-00007LEE003', 'website' => 0, 'published' => false,
            'nested' => ['depth' => 0], 'list' => ['shoe', false, '0'],
        ], json_decode($json, true, flags: JSON_THROW_ON_ERROR));
        self::assertNull($record->image);
    }
}
