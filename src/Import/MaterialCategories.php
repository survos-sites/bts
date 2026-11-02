<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MaterialCategories
{
    /** @var array<string, array{nl: string, en: string, example: string}> */
    private readonly array $categories;

    public function __construct(#[Autowire('%kernel.project_dir%/resources/material-categories.json')] string $filename)
    {
        $this->categories = json_decode(file_get_contents($filename), true, flags: JSON_THROW_ON_ERROR)['categories'];
    }

    public function normalize(string $code): string
    {
        $code = strtoupper(trim($code));
        // One published find is explicitly uncategorized by the source.
        if ('XXX' !== $code && !array_key_exists($code, $this->categories)) {
            throw new \UnexpectedValueException(sprintf('Unknown published material category "%s".', $code));
        }

        return $code;
    }

    public function label(string $code, string $locale): string
    {
        $code = $this->normalize($code);
        $language = explode('_', str_replace('-', '_', $locale))[0];
        $language = 'en' === $language ? 'en' : 'nl';
        if ('XXX' === $code) {
            return 'en' === $language ? 'Unclassified' : 'Niet ingedeeld';
        }

        return $this->categories[$code][$language];
    }
}
