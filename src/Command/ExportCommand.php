<?php

namespace App\Command;

use App\Service\ExportData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ExportCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'app:export';

    /**
     * @var \App\Service\ExportData
     */
    private $export;

    public function __construct(ExportData $export)
    {
        $this->export = $export;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Export Entry entities to CSV');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->export->export();

        return Command::SUCCESS;
    }
}
