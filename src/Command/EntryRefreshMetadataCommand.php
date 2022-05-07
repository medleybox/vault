<?php

declare(strict_types=1);

namespace App\Command;

use App\Provider\YouTube;
use App\Service\Import;
use App\Repository\EntryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:entry:refresh-metadata',
    description: 'Refresh metadata for all entries in vault'
)]
class EntryRefreshMetadataCommand extends Command
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
            $io->text("Refreshing '{$entry->getTitle()}'");
            $this->repo->fetchMetadata($entry);
            $this->import->webhook($entry);
        }

        return Command::SUCCESS;
    }
}
