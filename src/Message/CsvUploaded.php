<?php

namespace App\Message;

final readonly class CsvUploaded
{
    public function __construct(
        public string $importId,
        public string $content,
        public bool $sendNotification,
    ) {
    }
}
