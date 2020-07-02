<?php

namespace App\MessageHandler;

use App\Csv\CsvImporter;
use App\Message\CsvUploaded;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class CsvUploadedHandler implements MessageHandlerInterface
{
    private CsvImporter $csvImporter;

    public function __construct(CsvImporter $csvImporter)
    {
        $this->csvImporter = $csvImporter;
    }

    public function __invoke(CsvUploaded $message)
    {
        $this->csvImporter->importCsv($message->getContent(), $message->getImportId());
    }
}
