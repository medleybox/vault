<?php

declare(strict_types=1);

namespace App\Message;

use App\Provider\ProviderInterface;
use Symfony\Component\Uid\Uuid;

class ImportJob
{
    /**
     * The provider to run the import with
     * @var \App\Provider\ProviderInterface
     */
    private $provider;

    /**
     * UUID for this import
     * @var Uuid
     */
    private $uuid;

    public function __construct(ProviderInterface $provider, Uuid $uuid)
    {
        $this->provider = $provider;
        $this->uuid = $uuid;
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }
}
