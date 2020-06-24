<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EntryRepository")
 */
class Entry
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=36)
     */
    private $uuid;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $path;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $thumbnail;

    /**
     * @ORM\Column(type="integer")
     */
    private $size;

    /**
     * @ORM\Column(type="float")
     */
    private $seconds;

    /**
     * @ORM\Column(type="datetime")
     */
    private $imported;

    /**
     * @ORM\OneToOne(targetEntity=EntryMetadata::class, inversedBy="entry", cascade={"persist", "remove"})
     */
    private $metadata;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
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
}
