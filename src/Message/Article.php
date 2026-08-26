<?php

namespace App\Message;

final class Article
{
    public function __construct(
        public readonly int $articleId,
    ) {
    }
}
