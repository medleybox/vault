<?php

namespace App\Command;

use App\Provider\YouTube;
use App\Service\Import;
use App\Repository\EntryRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EntryRefreshMetadataCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'app:entry:refresh-metadata';

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

    protected function configure()
    {
        $this->setDescription('Refresh metadata for all entries in vault');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        foreach ($this->repo->findBy([], ['id' => 'DESC']) as $entry) {
            dump($entry);
            $this->repo->fetchMetadata($entry);
            $this->import->webhock($entry);
        }

        return 1;
    }
}
