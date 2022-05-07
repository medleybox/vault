<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ExportData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export',
    description: 'Export Entry entities to CSV'
)]
class ExportCommand extends Command
{
    /**
     * @var \App\Service\ExportData
     */
    private $export;

    public function __construct(ExportData $export)
    {
        $this->export = $export;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->export->export();

        $output->writeln("Exported ${file}.csv");

        return Command::SUCCESS;
    }
}
