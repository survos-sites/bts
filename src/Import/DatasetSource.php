<?php

declare(strict_types=1);

namespace App\Import;

final readonly class DatasetSource
{
    public function __construct(
        public string $name,
        public string $url,
        public string $target,
        public string $jsonl,
    ) {
    }
}
