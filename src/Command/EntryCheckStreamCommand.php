<?php

declare(strict_types=1);

namespace App\Command;

use App\Provider\YouTube;
use App\Service\{Import, Minio};
use App\Repository\EntryRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EntryCheckStreamCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'app:entry:check-stream';

    /**
     * @var string
     */
    protected static $defaultDescription = 'Check stream for all entries in vault and download from source if missing';

    /**
     * @var \App\Repository\EntryRepository
     */
    private $repo;

    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var \App\Service\Import
     */
    private $import;

    public function __construct(EntryRepository $repo, Minio $minio, Import $import)
    {
        $this->repo = $repo;
        $this->minio = $minio;
        $this->import = $import;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        foreach ($this->repo->findBy([], ['id' => 'DESC']) as $entry) {
            dump($entry->getTitle());

            // Check if webapp has this entry
            $webapp = $this->repo->hasWebapp($entry);
            if (false === $webapp) {
                dump('Skipping as not in webapp');
                continue;
            }

            // Check if the stream returns
            $stream = $this->repo->hasStream($entry);
            if (true === $stream) {
                dump('Stream is valid');
                continue;
            }

            $this->import->refreshSource($entry, true);

            // Check again if the stream returns valid
            $stream = $this->repo->hasStream($entry);
            if (true === $stream) {
                dump('Stream is valid');
                continue;
            }

            dump("Stream wasn't vaild!!!!!!!!!!!");
            exit();
        }

        return 0;
    }
}
