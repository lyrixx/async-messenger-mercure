<?php

namespace App\Message;

final class CsvUploaded
{
    private string $filename;
    private string $importId;

    public function __construct(string $filename, string $importId)
    {
        $this->filename = $filename;
        $this->importId = $importId;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getImportId(): string
    {
        return $this->importId;
    }
}
