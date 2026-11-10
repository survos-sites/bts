<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Amst;
use Survos\SearchBundle\Adapter\Doctrine\DoctrineAdapter;
use Survos\SearchBundle\Attribute\AsSearch;
use Survos\SearchBundle\Search\AbstractSearch;
use Survos\SearchBundle\Search\HitTemplateSearchInterface;
use Survos\SearchBundle\Twig\Components\Facet\RefinementList;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsSearch(index: Amst::class, name: 'amst', adapter: 'amst')]
final class AmstSearch extends AbstractSearch implements HitTemplateSearchInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function build(array $options = []): void
    {
        $this->setAdapterParameters([
            DoctrineAdapter::SEARCH_FIELDS => ['o.code', 'o.vondstnummer', 'o.objectBacking', 'o.descriptionBacking'],
            DoctrineAdapter::COUNT_DISTINCT => false,
            DoctrineAdapter::FETCH_JOIN_COLLECTION => false,
        ]);
        $this->addFacet('projectCode', $this->translator->trans('projectCode.label', domain: 'amst'), RefinementList::class);
        $this->addFacet('categorie', $this->translator->trans('categorie.label', domain: 'amst'), RefinementList::class);
        $this->addAvailableSort('o.code:asc', $this->translator->trans('vondstnummer.label', domain: 'amst'));
        $this->addAvailableSort('o.vlakMax:asc', $this->translator->trans('sort.depth_asc'));
        $this->addAvailableSort('o.vlakMax:desc', $this->translator->trans('sort.depth_desc'));
    }

    public function getHitTemplate(): ?string
    {
        return 'search/amst-card.html.twig';
    }
}
