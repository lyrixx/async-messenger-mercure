<?php

namespace App\Message;

final class CsvUploaded
{
    private string $importId;
    private string $content;

    public function __construct(string $importId, string $content)
    {
        $this->importId = $importId;
        $this->content = $content;
    }

    public function getImportId(): string
    {
        return $this->importId;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
