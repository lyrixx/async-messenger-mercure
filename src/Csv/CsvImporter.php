<?php

namespace App\Csv;

use App\Entity\FirstNameStat;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class CsvImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private HubInterface $mercurePublisher,
        #[Autowire(service: 'monolog.logger.request')]
        private Logger $logger,
        #[Autowire(service: 'deabug.log_processor')]
        private ?DebugProcessor $processor = null,
    ) {
    }

    public function importCsv(string $content, string $importId, bool $sendNotification): void
    {
        $sqlConnection = $this->em->getConnection();

        try {
            $sqlConnection->beginTransaction();

            $sqlConnection->executeQuery('TRUNCATE first_name_stat');

            $this->doHandle($content, $importId, $sendNotification);

            $sqlConnection->commit();
        } catch (\Throwable $e) {
            $sqlConnection->rollback();

            throw $e;
        }
    }

    private function doHandle(string $content, string $importId, bool $sendNotification): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), "async-csv-{$importId}-");
        file_put_contents($tmpFile, $content);

        $csv = new \SplFileObject($tmpFile);
        $csv->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $csv->setCsvControl(';');

        $batchSize = 100;
        $lineCount = $this->countLines($tmpFile);

        if ($sendNotification) {
            $this->publishProgress($importId, 'message', \sprintf('Import of CSV with %d lines started.', $lineCount));
        }

        foreach ($csv as $lineNumber => $data) {
            if (0 === $lineNumber || !$data) {
                continue;
            }

            if (!\is_array($data) || 4 !== \count($data)) {
                throw new \RuntimeException(\sprintf('Invalid CSV format on line %d.', $lineNumber));
            }

            [$gender, $firstName, $yearOfBirth, $count] = $data;

            if ('XXXX' === $yearOfBirth) {
                $yearOfBirth = null;
            }

            $firstNameStat = new FirstNameStat($gender, $firstName, $yearOfBirth, $count);

            $this->em->persist($firstNameStat);

            if (0 === $lineNumber % $batchSize) {
                $this->em->flush();

                if ($sendNotification) {
                    $this->publishProgress($importId, 'progress', [
                        'current' => $lineNumber,
                        'total' => $lineCount,
                    ]);
                }

                $this->reset();
            }
        }

        $this->em->flush();
        $this->reset();

        if ($sendNotification) {
            $this->publishProgress($importId, 'progress', [
                'current' => $lineNumber ?? 0,
                'total' => $lineCount,
            ]);

            $this->publishProgress($importId, 'message', \sprintf('Import of CSV with %d lines finished.', $lineCount));
        }
    }

    /**
     * @param string|array{current: int, total: int} $data
     */
    private function publishProgress(string $importId, string $type, string|array $data): void
    {
        $update = new Update(
            "csv:{$importId}",
            json_encode(['type' => $type, 'data' => $data], \JSON_THROW_ON_ERROR),
        );

        $this->mercurePublisher->publish($update);
    }

    private function countLines(string $filePath): int
    {
        $count = 0;
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Could not open file for reading.');
        }
        while (!feof($handle)) {
            fgets($handle);
            ++$count;
        }
        fclose($handle);

        return $count;
    }

    // Avoid memory leak in dev
    // Wait for https://github.com/symfony/symfony/pull/60017
    private function reset(): void
    {
        $this->logger->reset();
        $this->processor?->reset();
    }
}
