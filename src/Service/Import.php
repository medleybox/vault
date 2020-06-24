<?php

namespace App\Service;

use App\Entity\Entry;
use App\Provider\ProviderInterface;
use App\Repository\EntryRepository;
use App\Message\ImportJob;

use Doctrine\Common\Collections\ArrayCollection;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Messenger\MessageBusInterface;

final class Import
{
    /**
     * Full path to directory where imports are downloaded
     * @var string
     */
    const TMP_DIR = '/var/www/var/tmp/';

    /**
     * Mimio folder for storing thumbnails
     * @var string
     */
    const THUMBNAILS_MIMIO = 'thumbnails';

    /**
     * Permitted file extensions that will be downloaded and then uploaded to minio
     * @var array
     */
    const EXTENSIONS = ['*.opus', '*.ogg'];

    /**
     * Downloads must complete within 300 seconds (5 minutes)
     * @var int
     */
    const DOWNLOAD_TIMEOUT = 300;

    /**
     * The provider to run the import with
     * @var \App\Provider\ProviderInterface
     */
    private $provider;

    /**
     * If invoking this service on the command line, provide the SymfonyStlye to use
     * @var null|\Symfony\Component\Console\Style\SymfonyStyle
     */
    private $io = null;

    /**
     * UUID for this import. Gets generated by the setUp function
     * @var string
     */
    private $uuid;

    /**
     * File downloaded via youtube-dl
     * @var \Symfony\Component\Finder\SplFileInfo
     */
    private $file;

    /**
     * File stats generated by
     * @var array
     */
    private $stats;

    /**
     * Path relitive to minio storage
     * @var string
     */
    private $upload;

    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var \App\Repository\EntryRepository
     */
    private $entryRepo;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    /**
     * @var \App\Service\Request
     */
    private $request;

    /**
     * @var \App\Service\Thumbnail
     */
    private $thumbnail;

    public function __construct(Minio $minio, EntryRepository $entryRepo, LoggerInterface $log, MessageBusInterface $bus, Request $request, Thumbnail $thumbnail)
    {
        $this->minio = $minio;
        $this->entryRepo = $entryRepo;
        $this->log = $log;
        $this->bus = $bus;
        $this->request = $request;
        $this->thumbnail = $thumbnail;
    }

    public function setUp(ProviderInterface $provider, string $uuid = null, SymfonyStyle $io = null): bool
    {
        $this->provider = $provider;
        $this->uuid = $uuid;
        if (null === $uuid) {
            $this->uuid = Uuid::uuid4()->toString();
        }
        if (null !== $io) {
            $this->io = $io;
        }

        return true;
    }

    public function queue(): string
    {
        // Create a new import job and dispatch it to run in the background.
        $this->bus->dispatch(new ImportJob($this->provider, $this->uuid));

        return $this->uuid;
    }

    public function start(): bool
    {
        // Check that this service has been setup before continuing
        if (null === $this->provider || null === $this->uuid) {
            throw new \Exception('You need to call setUp() on this service first!');
        }

        $this->log->info('Attempting to download and convert from source');
        if (false === $this->attemptDownload()) {
            $this->log->error('Unable to download file');

            return false;
        }

        $this->log->info('Checking for download in a permitted format');
        if (false === $this->checkForDownload()) {
            return false;
        }

        $this->log->info('Running process functions');
        $this->process();

        $this->log->info('Uploading file to minio');
        $this->upload();

        $this->log->info('Importing into database');
        $this->import();

        return true;
    }

    protected function attemptDownload()
    {
        $url = $this->provider->getDownloadLink();
        $args = ['youtube-dl', '--youtube-skip-dash-manifest', '-o', "{$this->uuid}.%(ext)s", '-x', $url];
        $process = new Process($args, self::TMP_DIR, null, null, self::DOWNLOAD_TIMEOUT);

        if (null !== $this->io) {
            $process->start();
            foreach ($process as $type => $data) {
                if ($process::OUT === $type) {
                    $this->io->write($data);
                } else { // $process::ERR === $type
                    $this->io->getErrorStyle()->warning($data);
                }
            }

            return $process->isSuccessful();
        }

        $process->run();

        return $process->isSuccessful();
    }

    protected function checkForDownload()
    {
        $name = [$this->uuid . '.*'];
        $this->log->debug("Looking for files with name", $name);
        $finder = new Finder();
        $finder->files()
            ->in(self::TMP_DIR)
            ->name($name)
        ;

        if (!$finder->hasResults()) {
            $this->log->error('No download found!');

            return false;
        }

        foreach ($finder as $file) {
            $this->file = $file;
            $this->log->info("Found download {$this->file->getRelativePathname()}");

            return true;
        }
    }

    public function generateThumbnail()
    {
        $link = $this->provider->getThumbnailLink();
        $this->thumbnail->generate($this->uuid, $link);

        return true;
    }

    private function calculateFileStats()
    {
        $this->stats = $this->minio->getFileStats($this->file);
    }

    protected function process()
    {
        // Make sure that the metadata has been fetched
        $this->provider->fetchMetadata();
        $this->generateThumbnail();
        $this->calculateFileStats();
    }

    private function getProvidorNamespace()
    {
        $class = get_class($this->provider);
        $explode = explode('\\', $class);
        $name = array_pop($explode);

        return strtolower($name);
    }

    protected function upload()
    {
        $this->upload = "{$this->getProvidorNamespace()}/{$this->file->getFilename()}";
        $this->minio->upload($this->file->getFilename(), $this->upload);

        return true;
    }

    protected function import()
    {
        $metadata = $this->provider->fetchMetadata();
        $data = new ArrayCollection([
            'uuid' => $this->uuid,
            'path' => $this->upload,
            'title' => $this->provider->getTitle(),
            'thumbnail' => $this->thumbnail->getPath(),
            'size' => $this->stats['size'],
            'seconds' => $this->stats['seconds']
        ]);

        $this->entry = $this->entryRepo->createFromCompletedImport($data, $metadata);
        $this->webhock($this->entry);

        return true;
    }

    /**
     * Function to notify webapp of import
     */
    public function webhock(Entry $entry, $status = 'complete')
    {
        $this->log->debug("Webhock !!!");
        $this->request->post("/media-file/update", [
            'uuid' => $entry->getUuid(),
            'path' => $entry->getPath(),
            'provider' => $entry->getMetadata()->getProvider(),
            'title' => $entry->getTitle(),
            'size' => $entry->getSize(),
            'seconds' => $entry->getSeconds(),
            'metadata' => $entry->getMetadata()->getData()
        ]);

        return true;
    }
}
