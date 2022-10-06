<?php

namespace App\Provider;

use App\Entity\EntryMetadata;
use \Exception;

class BaseProvider
{
    /**
     * The ID for the media
     * @var string
     */
    public $id;

    /**
     * URL of import
     * @var string
     */
    public $url = null;

    /**
     * Metadata from the API relating to this media
     * @var \App\Entity\EntryMetadata
     */
    public $metadata = null;

    public function __construct()
    {
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return get_class($this);
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setUrl(string $url)
    {
        $this->url = $url;
        // add to interface - $this->setIdFromUrl();

        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setMetadata(EntryMetadata $metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getMetadata(): EntryMetadata
    {
        return $this->metadata;
    }

    // private function setIdFromUrl()
    // {
    //     $this->setId($this->getIdFromUrl());
    // }
}
