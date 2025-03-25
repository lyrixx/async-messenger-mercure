<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Messenger\Monitor\History\Model\ProcessedMessage as BaseProcessedMessage;
use Zenstruck\Messenger\Monitor\History\Model\Results;

#[ORM\Entity(readOnly: true)]
#[ORM\Table('processed_messages')]
class ProcessedMessage extends BaseProcessedMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    public function __construct(Envelope $envelope, Results $results, ?\Throwable $exception = null)
    {
        parent::__construct($envelope, $results, $exception);

        $this->id = Uuid::v7()->toString();
    }

    public function id(): string
    {
        return $this->id;
    }
}
