<?php

declare(strict_types=1);

namespace App\Search;

use Doctrine\DBAL\Connection;
use DuckDb\DBAL\Platforms\DuckDBPlatform;
use Survos\SearchBundle\Search\VectorSearch;

/** A derived AI projection in the catalog's DuckDB file; source entity text stays intact. */
final readonly class ObservationVectors
{
    public function __construct(private Connection $db, private ObservationEmbedder $embedder)
    {
    }

    public function ensureSchema(): void
    {
        if (!$this->db->getDatabasePlatform() instanceof DuckDBPlatform) {
            throw new \RuntimeException('The observation sample requires the local DuckDB connection.');
        }
        $this->db->executeStatement('CREATE TABLE IF NOT EXISTS bts_observation (
            code VARCHAR PRIMARY KEY, media_id VARCHAR NOT NULL, image_url VARCHAR NOT NULL,
            prose VARCHAR NOT NULL, result_json VARCHAR NOT NULL, observed_at TIMESTAMP NOT NULL DEFAULT current_timestamp
        )');
        $this->db->executeStatement('CREATE TABLE IF NOT EXISTS bts_observation_vector (
            code VARCHAR PRIMARY KEY, profile VARCHAR NOT NULL, fingerprint VARCHAR NOT NULL,
            input_text VARCHAR NOT NULL, embedding DOUBLE[] NOT NULL
        )');
    }

    public function coverage(): array
    {
        $total = (int) $this->db->fetchOne('SELECT count(*) FROM amst');
        if (!$this->db->getDatabasePlatform() instanceof DuckDBPlatform || !(bool) $this->db->fetchOne("SELECT count(*) FROM information_schema.tables WHERE table_schema = 'main' AND table_name = 'bts_observation_vector'")) {
            return ['indexed' => 0, 'total' => $total];
        }
        return ['indexed' => (int) $this->db->fetchOne('SELECT count(*) FROM bts_observation_vector v JOIN amst a ON a.code = v.code WHERE v.profile = ?', [$this->embedder->profile()]), 'total' => $total];
    }

    public function observation(string $code, string $url): ?array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM bts_observation WHERE code = ? AND image_url = ?', [$code, $url]);
        return false === $row ? null : $row;
    }

    public function saveObservation(string $code, string $mediaId, string $url, string $prose, array $result): void
    {
        if ('' === trim($prose)) {
            throw new \UnexpectedValueException('Observe returned no prose for '.$code);
        }
        $this->db->executeStatement('INSERT INTO bts_observation (code, media_id, image_url, prose, result_json)
            VALUES (?, ?, ?, ?, ?) ON CONFLICT (code) DO UPDATE SET media_id = excluded.media_id,
            image_url = excluded.image_url, prose = excluded.prose, result_json = excluded.result_json, observed_at = now()',
            [$code, $mediaId, $url, $prose, json_encode($result, JSON_THROW_ON_ERROR)]);
        // An observation refresh invalidates the derived vector, even if embedding is deferred.
        $this->db->executeStatement('DELETE FROM bts_observation_vector WHERE code = ?', [$code]);
    }

    public function vectorize(string $code): bool
    {
        $prose = $this->db->fetchOne('SELECT prose FROM bts_observation WHERE code = ?', [$code]);
        if (false === $prose) {
            throw new \RuntimeException('Run Observe before embedding '.$code);
        }
        $fingerprint = VectorSearch::fingerprint($this->embedder->profile(), $prose);
        if ($this->db->fetchOne('SELECT fingerprint FROM bts_observation_vector WHERE code = ?', [$code]) === $fingerprint) {
            return false;
        }
        $vector = $this->embedder->embedQuery($prose);
        $this->db->executeStatement('INSERT INTO bts_observation_vector (code, profile, fingerprint, input_text, embedding)
            VALUES (?, ?, ?, ?, CAST(? AS DOUBLE[])) ON CONFLICT (code) DO UPDATE SET profile = excluded.profile,
            fingerprint = excluded.fingerprint, input_text = excluded.input_text, embedding = excluded.embedding',
            [$code, $this->embedder->profile(), $fingerprint, $prose, json_encode($vector, JSON_THROW_ON_ERROR)]);
        return true;
    }

    /** @return array<string, float> */
    public function matches(string $query, ?float $minimum): array
    {
        $vector = $this->embedder->embedQuery($query);
        $rows = $this->db->fetchAllAssociative('SELECT code, list_cosine_similarity(embedding, CAST(? AS DOUBLE[])) AS score
            FROM bts_observation_vector WHERE profile = ? AND score >= ? ORDER BY score DESC, code',
            [json_encode($vector, JSON_THROW_ON_ERROR), $this->embedder->profile(), $minimum ?? 0.2]);
        return array_map('floatval', array_column($rows, 'score', 'code'));
    }
}
