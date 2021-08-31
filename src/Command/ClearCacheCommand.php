<?php

namespace App\Command;

use App\Service\Import;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearCacheCommand extends Command
{
    protected static $defaultName = 'app:clear-cache';

    public function __construct(Import $import)
    {
        $this->import = $import;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Clear the cache and temp files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->import->clearTempFiles();

        return 1;
    }
}
