<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Service\Import;
use App\Message\ImportJob;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ImportJobHandler implements MessageHandlerInterface
{
    /**
     * @var \App\Service\Import
     */
    private $import;

    public function __construct(Import $import)
    {
        $this->import = $import;
    }

    public function __invoke(ImportJob $message): void
    {
        $this->import->setUp($message->getProvider(), $message->getUuid());
        $this->import->start();
    }
}
