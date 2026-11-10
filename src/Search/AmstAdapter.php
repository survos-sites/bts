<?php

declare(strict_types=1);

namespace App\Search;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Survos\SearchBundle\Adapter\Doctrine\DoctrineAdapter;
use Survos\SearchBundle\Adapter\SemanticSearchAdapterInterface;
use Survos\SearchBundle\Search\Query;
use Survos\SearchBundle\Search\ResultSet\ResultSet;
use Survos\SearchBundle\Search\SearchInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final readonly class AmstAdapter extends DoctrineAdapter implements SemanticSearchAdapterInterface
{
    public function __construct(EntityManagerInterface $manager, private ObservationVectors $vectors)
    {
        parent::__construct($manager);
    }

    public function supportsSemanticSearch(SearchInterface $search): bool
    {
        return $this->vectors->coverage()['indexed'] > 0;
    }

    public function configureParameters(OptionsResolver $resolver): void
    {
        parent::configureParameters($resolver);
        $resolver->setDefaults(['minimumSimilarity' => 0.2, 'vectorCoverage' => $this->vectors->coverage()]);
    }

    public function search(Query $query, SearchInterface $search): ResultSet
    {
        $execution = clone $query;
        if ('semantic' !== $query->getSearchMode() || '' === trim($query->getQueryString())) {
            if (null === $execution->getActiveSort()) {
                $execution->setActiveSort('o.code:asc');
            }
            return parent::search($execution, $search);
        }
        $matches = $this->vectors->matches($query->getQueryString(), $query->getMinimumSimilarity());
        $original = $search->getResolvedAdapterParameters();
        $parameters = $original;
        $base = $original[self::QUERY_BUILDER];
        $parameters[self::QUERY_BUILDER] = static function (QueryBuilder $qb) use ($base, $matches): void {
            $base($qb);
            if ([] === $matches) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('o.code IN (:semanticCodes)')->setParameter('semanticCodes', array_keys($matches));
            }
        };
        $search->setResolvedAdapterParameters($parameters);
        // Keep the prompt and page in the UI state. Rank the bounded matching set before slicing.
        $execution->setQueryString('')->setCurrentPage(1)->setActiveHitsPerPage(max(1, count($matches)));
        try {
            $result = parent::search($execution, $search);
            $hits = $result->getHits();
            foreach ($hits as $hit) {
                $hit->setScore($matches[$hit->getData()->code]);
            }
            if (null === $query->getActiveSort()) {
                usort($hits, static fn ($a, $b) => ($b->getScore() <=> $a->getScore()) ?: strcmp($a->getData()->code, $b->getData()->code));
            }
            return $result->setHits(array_slice($hits, ($query->getCurrentPage() - 1) * $query->getActiveHitsPerPage(), $query->getActiveHitsPerPage()));
        } finally {
            $search->setResolvedAdapterParameters($original);
        }
    }
}
