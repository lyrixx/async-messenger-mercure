<?php

namespace App\MessageHandler;

use App\Message\Article;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ArticleHandler
{
    public function __invoke(Article $message): void
    {
        dump($message);
        // do something with your message
    }
}
