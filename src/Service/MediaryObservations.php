<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MediaryObservations
{
    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(MEDIARY_ENDPOINT)%')] private string $endpoint,
        #[Autowire('%env(default::MEDIARY_API_TOKEN)%')] private ?string $token,
    ) {
    }

    public function observe(string $url, string $task = 'observe'): array
    {
        if (null === $this->token || '' === $this->token) {
            throw new \RuntimeException('Configure MEDIARY_API_TOKEN before requesting Observe.');
        }
        return $this->rpc('analyzeUrl', ['url' => $url, 'task' => $task, 'scope' => 'bts/observe-sample', 'token' => $this->token, 'force' => false, 'callbackUrl' => '']);
    }

    public function persisted(string $id, string $task = 'observe'): array
    {
        $result = $this->rpc('sidecarGet', ['id' => $id, 'task' => $task]);
        if (true !== $result['found']) {
            throw new \RuntimeException('Mediary has not persisted the Observe result for '.$id);
        }
        return $result['data'];
    }

    private function rpc(string $method, array $params): array
    {
        $options = [
            'json' => ['jsonrpc' => '2.0', 'method' => $method, 'id' => 'bts', 'params' => $params],
            'timeout' => 180,
            'max_duration' => 180,
        ];
        if (str_contains($this->endpoint, '.wip')) {
            $options['proxy'] = 'http://127.0.0.1:7080';
        }
        $result = $this->http->request('POST', rtrim($this->endpoint, '/').'/api/v1', $options)->toArray();
        if (isset($result['error'])) {
            // Do not include the request, which contains the API token, in errors/logs.
            throw new \RuntimeException('Mediary '.$method.' failed: '.json_encode($result['error'], JSON_THROW_ON_ERROR));
        }
        return $result['result'];
    }
}
