<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Service\Import;
use App\Message\RefreshJob;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class RefreshJobHandler implements MessageHandlerInterface
{
    /**
     * @var \App\Service\Import
     */
    private $import;

    public function __construct(Import $import)
    {
        $this->import = $import;
    }

    public function __invoke(RefreshJob $message): void
    {
        $this->import->refreshSource($message->getUuid(), true);
    }
}
