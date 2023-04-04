<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AvatarRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\{Uuid, UuidV4};

#[ORM\Entity(repositoryClass: AvatarRepository::class)]
class Avatar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $mime = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private $uuid;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function setMime(string $mime): self
    {
        $this->mime = $mime;

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

    public function getUuid(): ?UuidV4
    {
        return $this->uuid;
    }

    public function setUuid(UuidV4 $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }
}
