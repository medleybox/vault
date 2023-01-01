<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\UuidV4;

class RefreshJob
{
    /**
     * UUID for this import
     * @var UuidV4
     */
    private $uuid;

    public function __construct(UuidV4 $uuid)
    {
        $this->uuid = $uuid;
    }

    public function getUuid(): UuidV4
    {
        return $this->uuid;
    }
}
