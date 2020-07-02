<?php

namespace App\Csv;

use App\Entity\FirstNameStat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;

final class CsvImporter
{
    private EntityManagerInterface $em;
    private PublisherInterface $mercurePublisher;

    public function __construct(EntityManagerInterface $em, PublisherInterface $mercurePublisher)
    {
        $this->em = $em;
        $this->mercurePublisher = $mercurePublisher;
    }

    public function importCsv(string $content, string $importId)
    {
        $sqlConnection = $this->em->getConnection();

        // Disable logger, because it leaks memory
        $logger = $sqlConnection->getConfiguration()->getSQLLogger();
        $sqlConnection->getConfiguration()->setSQLLogger(null);

        try {
            $sqlConnection->beginTransaction();

            $sqlConnection->exec('TRUNCATE first_name_stat');

            $this->doHandle($content, $importId);

            $sqlConnection->commit();
        } catch (\Throwable $e) {
            $sqlConnection->rollback();

            throw $e;
        } finally {
            $sqlConnection->getConfiguration()->setSQLLogger($logger);
        }
    }

    private function doHandle(string $content, string $importId)
    {
        $tmpFile = tempnam(sys_get_temp_dir(), "async-csv-$importId-");
        file_put_contents($tmpFile, $content);

        $csv = new \SplFileObject($tmpFile);
        $csv->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $csv->setCsvControl(';');

        $batchSize = 100;
        $lineCount = $this->countLines($tmpFile);

        $this->publishProgress($importId, 'message', sprintf('Import of CSV with %d lines started.', $lineCount));

        foreach ($csv as $lineNumber => $data) {
            if (0 === $lineNumber || !$data) {
                continue;
            }

            [$gender, $firstName, $yearOfBirth, $count] = $data;

            if ('XXXX' === $yearOfBirth) {
                $yearOfBirth = null;
            }

            $firstNameStat = new FirstNameStat($gender, $firstName, $yearOfBirth, $count);

            $this->em->persist($firstNameStat);

            if (0 === $lineNumber % $batchSize) {
                $this->em->flush();
                $this->em->clear();

                $this->publishProgress($importId, 'progress', [
                    'current' => $lineNumber,
                    'total' => $lineCount,
                ]);
            }
        }

        $this->em->flush();
        $this->em->clear();

        $this->publishProgress($importId, 'progress', [
            'current' => $lineNumber,
            'total' => $lineCount,
        ]);

        $this->publishProgress($importId, 'message', sprintf('Import of CSV with %d lines finished.', $lineCount));
    }

    private function publishProgress(string $importId, string $type, $data = null)
    {
        $update = new Update(
            "csv:$importId",
            json_encode(['type' => $type, 'data' => $data]),
        );

        ($this->mercurePublisher)($update);
    }

    private function countLines(string $filePath): int
    {
        $count = 0;
        $handle = fopen($filePath, 'r');
        while (!feof($handle)) {
            fgets($handle);
            ++$count;
        }
        fclose($handle);

        return $count;
    }
}
