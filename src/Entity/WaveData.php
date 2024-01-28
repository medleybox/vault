<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WaveDataRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WaveDataRepository::class)]
class WaveData
{
    #[ORM\Id()]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\OneToOne(targetEntity: Entry::class, inversedBy: "waveData", cascade: ["persist", "remove"])]
    private $entry;

    #[ORM\Column(type: "json")]
    private $data = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): ?Entry
    {
        return $this->entry;
    }

    public function setEntry(?Entry $entry): self
    {
        $this->entry = $entry;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
