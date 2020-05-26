<?php

namespace App\Message;

use App\Provider\ProviderInterface;

class ImportJob
{
    /**
     * The provider to run the import with
     * @var \App\Provider\ProviderInterface
     */
    private $provider;

    /**
     * UUID for this import
     * @var string
     */
    private $uuid;

    public function __construct(ProviderInterface $provider, string $uuid)
    {
        $this->provider = $provider;
        $this->uuid = $uuid;
    }

    public function getProvider()
    {
        return $this->provider;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
