<?php

declare(strict_types=1);

namespace App\Command;

use App\Provider\YouTube;
use App\Service\{Import, Minio};
use App\Repository\EntryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:entry:check-stream',
    description: 'Check stream for all entries in vault and download from source if missing'
)]
class EntryCheckStreamCommand extends Command
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching entries from database');
        foreach ($this->repo->findBy([], ['id' => 'DESC']) as $entry) {
            $io->text("Checking '{$entry->getTitle()}'");

            // Check if webapp has this entry
            $webapp = $this->repo->hasWebapp($entry);
            if (false === $webapp) {
                $io->caution('Skipping as not in webapp');
                continue;
            }

            // Check if the stream returns
            $stream = $this->repo->hasStream($entry);
            if (true === $stream) {
                $io->success('Stream is valid');
                continue;
            }

            $io->text("Refreching media from source");
            $this->import->refreshSource($entry, true);

            $io->text("Check again if the stream returns valid");
            $stream = $this->repo->hasStream($entry);
            if (true === $stream) {
                $io->success('Stream is valid after refresh');
                continue;
            }

            $io->error("Stream wasn't vaild!");
        }

        return Command::SUCCESS;
    }
}
