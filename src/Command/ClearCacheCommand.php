<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Import;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clear-cache',
    description: 'Clear the cache and temp files',
)]
class ClearCacheCommand extends Command
{
    /**
     * @var \App\Service\Import
     */
    private $import;

    public function __construct(Import $import)
    {
        $this->import = $import;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->import->clearTempFiles();

        return Command::SUCCESS;
    }
}
