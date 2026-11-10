<?php

declare(strict_types=1);

namespace App\Command;

use App\Search\ObservationVectors;
use App\Service\MediaryObservations;
use Doctrine\DBAL\Connection;
use Survos\MediaBundle\Service\MediaBatchDispatcher;
use Survos\DataContracts\Vocabulary\MediaSyncKeys;
use Survos\DataContracts\Util\MediaIdentity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:observe-sample', 'Run Mediary Observe and embed a bounded sample of published finds.')]
final class ObserveSampleCommand extends Command
{
    public function __construct(private readonly Connection $db, private readonly MediaryObservations $mediary, private readonly ObservationVectors $vectors, private readonly MediaBatchDispatcher $media)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Sample size (1–20), one find per material category.', '8');
        $this->addOption('observe-only', null, InputOption::VALUE_NONE, 'Save observations; defer embedding.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
        if (false === $limit) {
            throw new \InvalidArgumentException('The sample limit must be between 1 and 20.');
        }
        $this->vectors->ensureSchema();
        $rows = $this->db->fetchAllAssociative('SELECT code, image FROM (
            SELECT code, image, row_number() OVER (PARTITION BY categorie ORDER BY code) AS category_position
            FROM amst WHERE website = 1 AND image IS NOT NULL AND image <> \'\'
        ) WHERE category_position = 1 ORDER BY code LIMIT '.$limit);
        $pending = array_filter($rows, fn (array $row): bool => null === $this->vectors->observation($row['code'], $row['image']));
        if ([] !== $pending) {
            $io->writeln('Registering '.count($pending).' sample images through MediaBundle before Observe…');
            $registered = $this->media->dispatch('bts', array_values(array_column($pending, 'image')), ['sync' => true, 'context' => array_fill_keys(array_column($pending, 'image'), [MediaSyncKeys::ARCHIVE => true])]);
            if ([] !== $registered->rejected) {
                throw new \RuntimeException('Mediary rejected sample image URLs.');
            }
        }
        foreach ($rows as $row) {
            $code = $row['code'];
            $saved = $this->vectors->observation($code, $row['image']);
            if (null === $saved) {
                $deadline = time() + 30;
                do {
                    $probe = $this->media->probe(MediaIdentity::idFromOriginalUrl($row['image']));
                    $archived = isset($probe->meta['storageKey']) && '' !== $probe->meta['storageKey'];
                    if (!$archived && time() < $deadline) {
                        sleep(1);
                    }
                } while (!$archived && time() < $deadline);
                if (!$archived) {
                    throw new \RuntimeException('Mediary must archive '.$code.' before Observe; a legacy reference-only asset needs media:sync-local --reset on Mediary after archive:true registration.');
                }
                $io->writeln('Observe '.$code.' through Mediary…');
                $result = $this->mediary->observe($row['image']);
                // Read the persisted result separately: this also verifies Mediary's durable/cache boundary.
                $persisted = $this->mediary->persisted($result['id']);
                if (!isset($persisted['prose']) && !isset($persisted['description'])) {
                    $io->writeln('Completing observation prose with observe_hires for '.$code.'…');
                    $high = $this->mediary->observe($row['image'], 'observe_hires');
                    $detailed = $this->mediary->persisted($high['id'], 'observe_hires');
                    $prose = self::prose($detailed);
                    $persisted = ['observe' => $persisted, 'observe_hires' => $detailed];
                } else {
                    $prose = self::prose($persisted);
                }
                $this->vectors->saveObservation($code, $result['id'], $row['image'], $prose, $persisted);
                $io->writeln('Saved '.$code.' (Mediary '.($result['cached'] ? 'cache hit' : 'new observation').').');
            } else {
                $io->writeln('Using saved observation '.$code.'.');
            }
            if (!$input->getOption('observe-only')) {
                $io->writeln(($this->vectors->vectorize($code) ? 'Embedded ' : 'Using saved vector ').$code.'.');
            }
        }
        $this->db->executeStatement('CHECKPOINT');
        $coverage = $this->vectors->coverage();
        $io->success(sprintf('%d sample finds processed; %d of %d catalog records have vectors.', count($rows), $coverage['indexed'], $coverage['total']));
        return Command::SUCCESS;
    }

    public static function prose(array $result): string
    {
        // The current Observe task returns prose; Mediary's older batch contract used description.
        foreach (['prose', 'description'] as $field) {
            if (isset($result[$field]) && is_string($result[$field]) && '' !== trim($result[$field])) {
                return trim($result[$field]);
            }
        }
        throw new \UnexpectedValueException('Persisted Observe result contains neither prose nor description.');
    }
}
