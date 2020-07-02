<?php

namespace App\Message;

final class CsvUploaded
{
    private string $importId;
    private string $content;
    private bool $sendNotification;

    public function __construct(string $importId, string $content, bool $sendNotification)
    {
        $this->importId = $importId;
        $this->content = $content;
        $this->sendNotification = $sendNotification;
    }

    public function getImportId(): string
    {
        return $this->importId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function shouldSendNotification(): bool
    {
        return $this->sendNotification;
    }
}
