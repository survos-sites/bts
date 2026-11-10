<?php

declare(strict_types=1);

namespace App\Search;

use Survos\SearchBundle\Contract\QueryEmbedderInterface;
use Survos\SearchBundle\Search\VectorSearch;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ObservationEmbedder implements QueryEmbedderInterface
{
    public function __construct(
        private HttpClientInterface $http,
        private CacheInterface $cache,
        #[Autowire('%env(default::OPENAI_API_KEY)%')] private ?string $apiKey,
        #[Autowire('%env(BTS_EMBEDDING_MODEL)%')] private string $model,
        #[Autowire('%env(int:BTS_EMBEDDING_DIMENSIONS)%')] public int $dimensions,
    ) {
    }

    public function profile(): string
    {
        return $this->model.':'.$this->dimensions.':observation-v1';
    }

    public function embedQuery(string $text): array
    {
        if ('' === trim($text)) {
            throw new \InvalidArgumentException('Cannot embed empty observation/query text.');
        }
        if (null === $this->apiKey || '' === $this->apiKey) {
            throw new \RuntimeException('Configure OPENAI_API_KEY for the semantic sample.');
        }
        return $this->cache->get('bts_embedding_'.VectorSearch::fingerprint($this->profile(), $text), function () use ($text): array {
            $result = $this->http->request('POST', 'https://api.openai.com/v1/embeddings', [
                'auth_bearer' => $this->apiKey,
                'json' => ['model' => $this->model, 'dimensions' => $this->dimensions, 'input' => $text],
                'timeout' => 60,
                'max_duration' => 60,
            ])->toArray();
            $vector = $result['data'][0]['embedding'];
            VectorSearch::validate($vector, $this->dimensions);
            return $vector;
        });
    }
}
