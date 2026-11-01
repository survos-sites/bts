<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

final class RecordJson
{
    #[AsTwigFilter('sparse_json')]
    public function sparseJson(mixed $record): string
    {
        $data = json_decode(json_encode($record, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        return json_encode($this->sparse($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sparse(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $list = array_is_list($value);
        $result = [];
        foreach ($value as $key => $item) {
            $item = $this->sparse($item);
            if (null !== $item && '' !== $item && [] !== $item) {
                $result[$key] = $item;
            }
        }

        return $list ? array_values($result) : $result;
    }
}
