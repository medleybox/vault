<?php

namespace App\Service;

use App\Entity\{Entry, WaveData};
use App\Provider\ProviderInterface;
use App\Repository\{EntryRepository, EntryMetadataRepository};
use App\Message\ImportJob;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\{Finder, SplFileInfo};
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\{Uuid, UuidV4};

final class Import
{
    /**
     * Full path to program used to download media
     * @var string
     */
    const DOWNLOADER = '/usr/bin/yt-dlp';

    /**
     * Download library used by download program
     * @var string
     */
    const DOWNLOADER_EXTERNAL = 'aria2c';

    /**
     * Arguments for download library
     * @var string
     */
    const DOWNLOADER_EXTERNAL_ARGS = 'aria2c:-j 3 -x 3 -s 3';

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
     * Download and convert must complete within 600 seconds (10 minutes)
     * @var int
     */
    const DOWNLOAD_TIMEOUT = 600;

    /**
     * The provider to run the import with
     * @var \App\Provider\ProviderInterface
     */
    private $provider;

    /**
     * UUID for this import. Gets generated by the setUp function
     * @var \Symfony\Component\Uid\Uuid
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
     * @var \App\Entity\WaveData
     */
    private $wave = null;

    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var \App\Repository\EntryRepository
     */
    private $entryRepo;

    /**
     * @var \App\Repository\EntryMetadataRepository
     */
    private $entryMetaRepo;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    /**
     * @var \Symfony\Component\Messenger\MessageBusInterface
     */
    private $bus;

    /**
     * @var \App\Service\Request
     */
    private $request;

    /**
     * @var \App\Service\Thumbnail
     */
    private $thumbnail;

    /**
     * @var \App\Service\WebsocketClient
     */
    private $wsClient;

     /**
     * @var \App\Service\Audiowaveform
     */
    private $audiowaveform;

    public function __construct(Minio $minio, EntryRepository $entryRepo, EntryMetadataRepository $entryMetaRepo, LoggerInterface $log, MessageBusInterface $bus, Request $request, Thumbnail $thumbnail, WebsocketClient $wsClient, Audiowaveform $audiowaveform)
    {
        $this->minio = $minio;
        $this->entryRepo = $entryRepo;
        $this->entryMetaRepo = $entryMetaRepo;
        $this->log = $log;
        $this->bus = $bus;
        $this->request = $request;
        $this->thumbnail = $thumbnail;
        $this->wsClient = $wsClient;
        $this->audiowaveform = $audiowaveform;
    }

    private function log(string $msg, string $stage)
    {
        $this->log->info($msg);
        $msg = preg_split('/\r\n|\r|\n/', $msg);
        $this->wsClient->importLogOutput($msg, $stage);

        return true;
    }

    public function seachForDownload($provider)
    {
        $args = [self::DOWNLOADER, '--print-json', '--no-check-formats', '--get-thumbnail', $provider->getDownloadLink()];
        $this->log->debug('youtube search', $args);
        $process = new Process($args, self::TMP_DIR, null, null, self::DOWNLOAD_TIMEOUT);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Setup service for first import
     */
    public function setUp(ProviderInterface $provider, ?string $uuid = null): bool
    {
        // First check for import
        $entry = $this->entryRepo->findViaProvider($provider);
        if (null !== $entry && null === $uuid) {
            throw new \Exception('Entry has already been imported');
        }

        // Check if the entry had been marked as imported
        if (null !== $entry && null !== $entry->getImported() && null !== $entry->getPath()) {
            throw new \Exception('Entry has already been imported in database');
        }

        $search = $this->seachForDownload($provider);
        if (true !== $search) {
            throw new \Exception('Unable to find entry after search');
        }

        $this->provider = $provider;
        $this->uuid = Uuid::v4();
        if (null !== $uuid) {
            $this->uuid = Uuid::fromString($uuid);
        }

        return true;
    }

    public function queue(): bool
    {
        try {
            $this->log("Starting queue for job {$this->uuid}", 'queue');
            // Create a new import job and dispatch it to run in the background.
            $this->bus->dispatch(new ImportJob($this->provider, $this->uuid));
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function start(): bool
    {
        // Check that this service has been setup before continuing
        if (null === $this->provider || null === $this->uuid) {
            throw new \Exception('You need to call setUp() on this service first!');
        }

        $this->log('Attempting to download and convert from source', 'start');
        if (false === $this->attemptDownload()) {
            $this->log->error('Unable to download file ', [$this->provider->getDownloadLink()]);

            return false;
        }

        $this->log('Checking for download in a permitted format', 'checkForDownload');
        if (null === $this->checkForDownload($this->uuid)) {
            return false;
        }

        $this->log('Running process functions on media', 'process');
        $this->process();

        $this->log('Uploading file to minio', 'upload');
        $this->upload();

        $this->log('Importing into database and webapp', 'import');
        $this->import();

        $this->log("Completed import of {$this->uuid}", 'finish');

        return true;
    }

    /**
     * Atempt to download and convert to .ogg if required
     */
    protected function attemptDownload()
    {
        // '--downloader', self::DOWNLOADER_EXTERNAL, '--external-downloader-args', self::DOWNLOADER_EXTERNAL
        $url = $this->provider->getDownloadLink();
        $args = [self::DOWNLOADER, '--newline', '--youtube-skip-dash-manifest', '-N 4', '-x', '-o', "{$this->uuid}.%(ext)s", $url];

        $this->log("Attempting to download {$url}", 'attemptDownload');
        $this->log->debug('youtube-dl args', $args);

        $process = new Process($args, self::TMP_DIR, null, null, self::DOWNLOAD_TIMEOUT);
        if (null !== $this->log) {
            $process->start();
            foreach ($process as $type => $data) {
                $this->wsClient->importOutput($data);
                if ($process::OUT === $type) {
                    $this->log->debug($data);
                } else { // $process::ERR === $type
                    $this->log->error($data);
                }
            }
            $this->log('completed', 'attemptDownload');

            return $process->isSuccessful();
        }

        $process->run();
        $this->log('completed', 'attemptDownload');

        return $process->isSuccessful();
    }

    public function checkForDownload(string $uuid, $search = ".*"): ?SplFileInfo
    {
        $name = $uuid . $search;
        $search = [$name];
        $this->log("Looking for files with name {$name}", 'checkForDownload');
        $finder = new Finder();
        $finder->files()
            ->in(self::TMP_DIR)
            ->name($search)
        ;

        if (!$finder->hasResults()) {
            $this->log->error('No download found!');

            return null;
        }

        foreach ($finder as $file) {
            $this->file = $file;
            $this->log("Found download {$this->file->getRelativePathname()}", 'checkForDownload');

            return $this->file;
        }

        return null;
    }

     /**
     * Download media file from minio to tmp path
     */
    private function minioToTmp(Entry $entry): ?SplFileInfo
    {
        $file = $this->checkForDownload($entry->getUuid());
        if (null != $file) {
            return $file;
        }

        $download = $this->download($entry);
        if (null === $download) {
            return null;
        }

        $file = $this->checkForDownload($entry->getUuid());
        if (null === $file) {
            return null;
        }

        return $file;
    }

    private function convert($entry, $file)
    {
        $args = ['/usr/bin/ffmpeg', '-i', $file->getPathname(), '-c:a', 'libvorbis', '-b:a', '64k', self::TMP_DIR . "{$entry->getUuid()}.ogg"];
        $this->log->debug("ffmpeg args to convert '{$entry->getTitle()}' to .ogg", $args);

        try {
            $process = new Process($args, self::TMP_DIR);
            $process->setTimeout(300);
            $process->run();
        } catch (\Exception $e) {
            $this->log->error("ffmpeg: {$e->getMessage()}");

            return false;
        }

        return true;
    }

    public function generateEntryWaveData(Entry $entry, bool $convert = true): ?Entry
    {
        $file = $this->minioToTmp($entry);
        if (null === $file) {
            $this->log->error("Unable to download mediafile - {$entry->getUuid()}");
            if (false === $this->refreshSource($entry)) {
                return null;
            }

            $file = $this->minioToTmp($entry);
            if (null === $file) {
                return null;
            }
        }

        $ogg = $this->checkForDownload($entry->getUuid(), '.ogg');
        if (null === $ogg) {
            $this->log->info('Need to convert to ogg before running audiowaveform');
            if (true === $convert) {
                $this->convert($entry, $file);
                $ogg = $this->checkForDownload($entry->getUuid(), '.ogg');
            }

            if (null === $ogg) {
                $this->log->error('Unable to convert file to .ogg');

                return null;
            }
        }

        $wave = $this->audiowaveform->generate($entry->getUuid(), $ogg);
        if (null === $wave) {
            $this->log->error("Unable to generate wavedata - {$entry->getUuid()}");

            return null;
        }

        $this->log->info("Generated wavedata for '{$entry->getTitle()}'");
        $entry->setWaveData(null);
        $entry->setWaveData($wave);
        $this->clearTempFiles();

        return $entry;
    }

    public function generateThumbnail()
    {
        $link = $this->provider->getThumbnailLink();
        $this->thumbnail->generate($this->uuid, $link);

        return $this;
    }

    private function calculateFileStats()
    {
        $this->stats = $this->minio->getFileStats($this->file);

        return $this;
    }

    private function generateWaves()
    {
        $waveData = $this->audiowaveform->generate($this->uuid, $this->file);
        if (null === $waveData) {
            $this->log->error('Unable to generate wavedata', [$waveData, $this->file]);
        }
        $this->wave = $waveData;

        return $this;
    }

    public function clearTempFiles()
    {
        $finder = (new Finder())
            ->files()
            ->in(self::TMP_DIR)
        ;

        $remove = [];
        foreach ($finder as $file) {
            $this->log->debug("Clearing temp file: {$file->getRelativePathname()}");
            $remove[] = $file->getPathname();
        }

        $filesystem = new Filesystem();
        $filesystem->remove($remove);

        return $this;
    }

    /**
     * Run functions after downloading media from source
     */
    protected function process(): bool
    {
        $this->generateThumbnail()
            ->calculateFileStats()
        ;

        // Make sure that the metadata has been fetched
        $metadata = $this->provider->fetchMetadata();
        if (false === $metadata) {
            return false;
        }

        $this->generateWaves();

        return true;
    }

    private function getProviderNamespace()
    {
        $class = get_class($this->provider);
        $explode = explode('\\', $class);
        $name = array_pop($explode);

        return strtolower($name);
    }

    protected function upload()
    {
        $this->upload = "{$this->getProviderNamespace()}/{$this->file->getFilename()}";
        $this->log->debug('upload()', [$this->upload, $this->file]);
        $this->minio->upload($this->file->getFilename(), $this->upload);

        return true;
    }

    protected function download(Entry $entry): ?string
    {
        $download = $entry->getPath();
        if (null === $download) {
            return null;
        }

        $stream = $this->minio->stream($download);
        if (false === $stream) {
            return null;
        }

        $path = self::TMP_DIR . basename($download);
        $this->log->debug("download: {$download} -> {$path}");

        try {
            file_put_contents($path, $stream);
        } catch (\Exception $e) {
            $this->log->error("Unable to download: {$download}");

            return null;
        }

        return $path;
    }

    public function refreshSource(Entry $entry, $upload = false): bool
    {
        $this->log->info('Attempting to refresh from source');
        $this->uuid = $entry->getUuid();
        $this->provider = $entry->getMetadata()->getProviderInstance();

        if (false === $this->attemptDownload()) {
            $this->log->error('Unable to download file ', [$this->provider->getDownloadLink()]);

            return false;
        }

        if (null === $this->checkForDownload($this->uuid)) {
            return false;
        }

        if (true === $upload) {
            $this->process();
            $this->upload();

            // Update entry with latest data after process and upload
            $entry->setPath($this->upload)
                ->setSize($this->stats['size'])
                ->setSeconds($this->stats['seconds'])
            ;
            $this->entryRepo->save($entry);
        }

        return true;
    }

    protected function import()
    {
        $metadata = $this->entryMetaRepo->findOneBy(['ref' => $this->provider->getId()]);
        if (null === $metadata) {
            $metadata = $this->provider->fetchMetadata();
        }

        $data = new ArrayCollection([
            'uuid' => $this->uuid,
            'path' => $this->upload,
            'title' => $this->provider->getTitle(),
            'thumbnail' => $this->thumbnail->getPath(),
            'size' => $this->stats['size'],
            'seconds' => $this->stats['seconds']
        ]);

        $entry = $this->entryRepo->createFromCompletedImport($data, $metadata, $this->wave);
        $this->webhook($entry);

        return true;
    }

    /**
     * Function to notify webapp of import
     */
    public function webhook(Entry $entry, $status = 'complete')
    {
        $update = [
            'uuid' => $entry->getUuid(),
            'path' => $entry->getPath(),
            'provider' => $entry->getMetadata()->getProvider(),
            'title' => $entry->getTitle(),
            'size' => $entry->getSize(),
            'seconds' => $entry->getSeconds(),
            'metadata' => $entry->getMetadata()->getData()
        ];
        $this->log->debug("Webhook !!!", $update);
        $this->request->post("/media-file/update", $update);

        return true;
    }
}
