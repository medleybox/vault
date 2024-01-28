<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EntryMetadataRepository;
use App\Provider\ProviderInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntryMetadataRepository::class)]
class EntryMetadata
{
    #[ORM\Id()]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "string", length: 255, unique: true)]
    private $ref;

    #[ORM\Column(type: "string", length: 255)]
    private $provider;

    #[ORM\Column(type: "json_document")]
    private $data;

    #[ORM\OneToOne(targetEntity: Entry::class, mappedBy: "metadata", cascade: ["persist", "remove"])]
    private $entry;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getProviderInstance(): ?ProviderInterface
    {
        if (null === $this->ref || '' === $this->ref) {
            return null;
        }

        return (new $this->provider(null))->setId($this->getRef())->setMetadata($this);
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getEntry(): ?Entry
    {
        return $this->entry;
    }

    public function setEntry(?Entry $entry): self
    {
        $this->entry = $entry;

        // set (or unset) the owning side of the relation if necessary
        $newMetadata = null === $entry ? null : $this;
        if ($entry->getMetadata() !== $newMetadata) {
            $entry->setMetadata($newMetadata);
        }

        return $this;
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->getData())) {
            return $this->getData()[$key];
        }

        return '-';
    }
}
