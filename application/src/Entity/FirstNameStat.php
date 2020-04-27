<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FirstNameStatRepository")
 */
class FirstNameStat
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="integer")
     */
    private int $gender;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $firstName;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?string $yearOfBirth;

    /**
     * @ORM\Column(type="integer")
     */
    private int $count;

    public function __construct(int $gender, string $firstName, ?string $yearOfBirth, int $count)
    {
        $this->gender = $gender;
        $this->firstName = $firstName;
        $this->yearOfBirth = $yearOfBirth;
        $this->count = $count;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getGender(): int
    {
        return $this->gender;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getYearOfBirth(): ?int
    {
        return $this->yearOfBirth;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
