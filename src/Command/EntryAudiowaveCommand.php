<?php

declare(strict_types=1);

namespace App\Command;

use App\Provider\YouTube;
use App\Repository\EntryRepository;
use App\Service\Import;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputOption, InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:entry:audiowave',
    description: 'Generate AudioWave data for all entries in vault',
)]
class EntryAudiowaveCommand extends Command
{
    /**
     * @var \App\Repository\EntryRepository
     */
    private $repo;

    /**
     * @var \App\Service\Import
     */
    private $import;

    public function __construct(EntryRepository $repo, Import $import)
    {
        $this->repo = $repo;
        $this->import = $import;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('uuid', InputArgument::OPTIONAL, 'UUID of entity to generate')
            ->addOption(
                'cache',
                null,
                InputOption::VALUE_NEGATABLE,
                'Force generating wavedata for all entries?',
                1
            )
            ->addOption(
                'convert',
                null,
                InputOption::VALUE_NEGATABLE,
                'Allow entries to be converted?',
                1
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $convert = ($input->getOption('convert') == true);

        // Check if only required to generate for one entry
        if ($uuid = $input->getArgument('uuid')) {
            $entry = $this->repo->findOneBy(['uuid' => $uuid]);
            $io->text("Running audiowaveform on '{$entry->getTitle()}'");
            $entry = $this->import->generateEntryWaveData($entry, $convert);

            return Command::SUCCESS;
        }

        $io->text("Running audiowaveform on entries");
        foreach ($this->repo->findBy([], ['id' => 'DESC']) as $entry) {
            if (null !== $entry->getWaveData() && $input->getOption('cache') == true) {
                $io->text("Skipping '{$entry->getTitle()}' as wavedata already generated");
                continue;
            }
            $entry = $this->import->generateEntryWaveData($entry, $convert);
            if (null === $entry) {
                continue;
            }

            $this->repo->save($entry);
            $io->info("Generated wavedata for '{$entry->getTitle()}'");
        }

        $this->import->clearTempFiles();

        return Command::SUCCESS;
    }
}
