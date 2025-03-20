<?php

namespace App\MessageHandler;

use App\Csv\CsvImporter;
use App\Message\CsvUploaded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
final readonly class CsvUploadedHandler
{
    public function __construct(
        private CsvImporter $csvImporter,
    ) {
    }

    public function __invoke(CsvUploaded $message): void
    {
        $this->csvImporter->importCsv($message->content, $message->importId, $message->sendNotification);
    }
}
