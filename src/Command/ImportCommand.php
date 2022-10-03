<?php

declare(strict_types=1);

namespace App\Command;

use App\Provider\ProviderGuesser;
use App\Service\Import;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputOption, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Exception;

#[AsCommand(
    name: 'app:import',
    description: 'Download and import tracks into the vault'
)]
class ImportCommand extends Command
{
    /**
     * @var \App\Service\Import
     */
    private $import;

    public function __construct(Import $import, ProviderGuesser $guesser)
    {
        $this->import = $import;
        $this->guesser = $guesser;
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
        $provider = $this->guesser->providerFromUrl($input->getArgument('url'));

        if (null === $provider) {
            $io->error('Unable to guess media source provider');

            return Command::FAILURE;
        }

        try {
            $this->import->setUp($provider);
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
