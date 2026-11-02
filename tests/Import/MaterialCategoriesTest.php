<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Import\MaterialCategories;
use PHPUnit\Framework\TestCase;

final class MaterialCategoriesTest extends TestCase
{
    private function categories(): MaterialCategories
    {
        return new MaterialCategories(dirname(__DIR__, 2).'/resources/material-categories.json');
    }

    public function testOfficialBilingualLabelsAndCanonicalCodes(): void
    {
        $categories = $this->categories();
        self::assertSame('MTL', $categories->normalize(' mtl '));
        self::assertSame('Metal', $categories->label('MTL', 'en'));
        self::assertSame('Metaal', $categories->label('MTL', 'nl'));
        self::assertSame('Ceramics', $categories->label('CER', 'en_GB'));
        self::assertSame('Aardewerk', $categories->label('CER', 'nl-NL'));
        self::assertSame('Sculptures', $categories->label('KSC', 'en'));
        self::assertSame('Unclassified', $categories->label('XXX', 'en'));
        self::assertSame('Niet ingedeeld', $categories->label('XXX', 'nl'));
    }

    public function testUnknownCategoryFailsRatherThanInventingALabel(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->categories()->normalize('TYPO');
    }
}
