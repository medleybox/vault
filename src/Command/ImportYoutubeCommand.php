<?php

namespace App\Command;

use App\Provider\YouTube;
use App\Service\Import;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Exception;

class ImportYoutubeCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'app:import:youtube';

    /**
     * @var \App\Service\Import
     */
    private $import;

    public function __construct(Import $import)
    {
        $this->import = $import;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Download and import YouTube video into the vault')
            ->addArgument('url', InputArgument::REQUIRED, 'YouTube url or video id')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $youtube = new YouTube($input->getArgument('url'));

        try {
            $this->import->setUp($youtube);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }

        // Process via message broker
        if (true === $this->import->queue()) {
            $io->success('Import job sent for processing!');

            return 0;
        }

        // Process now
        if (true === $this->import->start()) {
            $io->success('Import Complete!');

            return 0;
        }
        $io->error('Unable to process import!');

        return 1;
    }
}
