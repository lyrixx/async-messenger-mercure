<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Repository\FirstNameStatRepository::class)]
final readonly class FirstNameStat
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public string $id;

    public function __construct(
        #[ORM\Column()]
        public int $gender,
        #[ORM\Column()]
        public string $firstName,
        #[ORM\Column(nullable: true)]
        public ?string $yearOfBirth,
        #[ORM\Column()]
        public int $count,
    ) {
        $this->id = (new Ulid())->toString();
    }
}
