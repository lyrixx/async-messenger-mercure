<?php

namespace App\MessageHandler;

use App\Entity\FirstNameStat;
use App\Message\CsvUploaded;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class CsvUploadedHandler implements MessageHandlerInterface
{
    private string $tmpDir;
    private EntityManagerInterface $em;
    private PublisherInterface $mercurePublisher;

    public function __construct(string $tmpDir, EntityManagerInterface $em, PublisherInterface $mercurePublisher)
    {
        $this->tmpDir = $tmpDir;
        $this->em = $em;
        $this->mercurePublisher = $mercurePublisher;
    }

    public function __invoke(CsvUploaded $message)
    {
        $sqlConnection = $this->em->getConnection();

        // Disable logger, because it leaks memory
        $logger = $sqlConnection->getConfiguration()->getSQLLogger();
        $sqlConnection->getConfiguration()->setSQLLogger(null);

        try {
            $sqlConnection->beginTransaction();

            $sqlConnection->exec('TRUNCATE first_name_stat');

            $this->doHandle($message->getFilename(), $message->getImportId());

            $sqlConnection->commit();
        } catch (\Throwable $e) {
            $sqlConnection->rollback();

            throw $e;
        } finally {
            $sqlConnection->getConfiguration()->setSQLLogger($logger);
        }
    }

    private function doHandle(string $filename, string $importId)
    {
        $filePath = sprintf('%s/%s', $this->tmpDir, $filename);

        $csv = new \SplFileObject($filePath);
        $csv->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $csv->setCsvControl(';');

        $batchSize = 100;
        $lineCount = $this->countLines($filePath);

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
