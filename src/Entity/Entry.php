<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\{Uuid, UuidV4};

#[ORM\Entity(repositoryClass: EntryRepository::class)]
class Entry
{
    #[ORM\Id()]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private $uuid;

    #[ORM\Column(type: "string", nullable: true)]
    private $path;

    #[ORM\Column(type: "string", length: 255)]
    private $title;

    #[ORM\Column(type: "string", length: 255)]
    private $thumbnail;

    #[ORM\Column(type: "integer", nullable: true)]
    private $size;

    #[ORM\Column(type: "float", nullable: true)]
    private $seconds;

    #[ORM\Column(type: "datetime", nullable: true)]
    private $imported;

    #[ORM\OneToOne(targetEntity: EntryMetadata::class, inversedBy: "entry", cascade: ["persist", "remove"])]
    private $metadata;

    #[ORM\OneToOne(targetEntity: WaveData::class, mappedBy: "entry", cascade: ["persist", "remove"])]
    private $waveData;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $download = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?UuidV4
    {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(string $thumbnail): self
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getSeconds(): ?float
    {
        return $this->seconds;
    }

    public function setSeconds(float $seconds): self
    {
        $this->seconds = $seconds;

        return $this;
    }

    public function getImported(): ?\DateTimeInterface
    {
        return $this->imported;
    }

    public function setImported(\DateTimeInterface $imported): self
    {
        $this->imported = $imported;

        return $this;
    }

    public function getMetadata(): ?EntryMetadata
    {
        return $this->metadata;
    }

    public function setMetadata(?EntryMetadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getWaveData(): ?WaveData
    {
        return $this->waveData;
    }

    public function setWaveData(?WaveData $waveData): self
    {
        // unset the owning side of the relation if necessary
        if ($waveData === null && $this->waveData !== null) {
            $this->waveData->setEntry(null);
        }

        // set the owning side of the relation if necessary
        if ($waveData !== null && $waveData->getEntry() !== $this) {
            $waveData->setEntry($this);
        }

        $this->waveData = $waveData;

        return $this;
    }

    public function getDownload(): ?string
    {
        return $this->download;
    }

    public function setDownload(?string $download): self
    {
        $this->download = $download;

        return $this;
    }
}
