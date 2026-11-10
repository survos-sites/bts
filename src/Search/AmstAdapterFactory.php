<?php

declare(strict_types=1);

namespace App\Search;

use Doctrine\ORM\EntityManagerInterface;
use Survos\SearchBundle\Adapter\AdapterFactoryInterface;
use Survos\SearchBundle\Adapter\AdapterInterface;

final readonly class AmstAdapterFactory implements AdapterFactoryInterface
{
    public function __construct(private EntityManagerInterface $manager, private ObservationVectors $vectors)
    {
    }

    public function support(string $dsn): bool
    {
        return 'amst://default' === $dsn;
    }

    public function createAdapter(string $dsn): AdapterInterface
    {
        return new AmstAdapter($this->manager, $this->vectors);
    }
}
