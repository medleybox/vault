<?php

declare(strict_types=1);

namespace App\Command;

use App\Provider\SoundCloud;
use App\Service\Import;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputOption, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Exception;

#[AsCommand(
    name: 'app:import:soundcloud',
    description: 'Download and import Soundcloud tracks into the vault'
)]
class ImportSoundCloudCommand extends Command
{
    /**
     * @var \App\Service\Import
     */
    private $import;

    public function __construct(Import $import, SoundCloud $sc)
    {
        $this->import = $import;
        $this->sc = $sc;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED, 'SoundCloud track url')
            ->addOption(
                'force-start',
                null,
                InputOption::VALUE_OPTIONAL,
                "Don't queue import job"
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $soundcloud = $this->sc->setUrl($input->getArgument('url'));

        try {
            $this->import->setUp($soundcloud);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        // Check if we queue
        $forceStart = $input->getOption('force-start');

        // Process via message broker
        if (false === $forceStart && true === $this->import->queue()) {
            $io->success('Import job sent for processing!');

            return Command::SUCCESS;
        }

        $io->text('Running via shell...');

        // Process now
        if (true === $this->import->start()) {
            $io->success('Import Complete!');

            return Command::SUCCESS;
        }
        $io->error('Unable to process import!');

        return Command::FAILURE;
    }
}
