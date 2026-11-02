<?php

declare(strict_types=1);

namespace App\Twig;

use App\Import\MaterialCategories;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFilter;

final readonly class CategoryLabels
{
    public function __construct(private MaterialCategories $categories, private RequestStack $requests)
    {
    }

    #[AsTwigFilter('category_label')]
    public function label(string $code): string
    {
        // Console rendering uses the Dutch source language.
        return $this->categories->label($code, $this->requests->getCurrentRequest()?->getLocale() ?? 'nl');
    }
}
