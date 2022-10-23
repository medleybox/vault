<?php

declare(strict_types=1);

namespace App\Message;

use App\Provider\ProviderInterface;
use Symfony\Component\Uid\UuidV4;

class ImportJob
{
    /**
     * The provider to run the import with
     * @var \App\Provider\ProviderInterface
     */
    private $provider;

    /**
     * UUID for this import
     * @var UuidV4
     */
    private $uuid;

    public function __construct(ProviderInterface $provider, UuidV4 $uuid)
    {
        $this->provider = $provider;
        $this->uuid = $uuid;
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }

    public function getUuid(): UuidV4
    {
        return $this->uuid;
    }
}
