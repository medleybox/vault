<?php

namespace App\Service;

use App\Entity\Entry;
use App\Repository\EntryRepository;
use App\Service\Minio;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class Thumbnail
{
    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    /**
     * @var \App\Repository\EntryRepository
     */
    private $repo;

    /**
     * Path of generated thumnail in mino
     * @var string
     */
    private $path;

    public function __construct(Minio $minio, LoggerInterface $log, EntryRepository $repo)
    {
        $this->minio = $minio;
        $this->log = $log;
        $this->repo = $repo;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function render(Entry $entry): Response
    {
        $path = $entry->getThumbnail();
        $thumbnail = $this->minio->get($path);
        if (null === $thumbnail) {
            $path = $this->refreshThumbnail($entry);
            $thumbnail = $this->minio->get($path);
        }

        if (null === $thumbnail) {
            // Load 404 not found image here
        }

        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(86400 * 30);
        $response->headers->set('Content-Type', 'image/jpg');
        $response->setContent($thumbnail);

        return $response;
    }

    public function refreshThumbnail(Entry $entry)
    {
        if (null === $entry->getMetadata() || 0 === count((array) $entry->getMetadata()->getData())) {
            $this->repo->fetchMetadata($entry);
        }

        $providor = $entry->getMetadata()->getProviderInstance();
        $link = $providor->getThumbnailLink();

        return $this->generate($entry->getUuid(), $link);
    }

    public function generate(string $uuid, string $link): ?string
    {
        $filename = "{$uuid}.jpg";
        $this->path = Import::THUMBNAILS_MIMIO . "/{$filename}";

        $this->log->debug("[Thumbnail] Downloading from {$link} and uploading to {$this->path}");
        $file = (new HttpClient())->create()->request('GET', $link);

        try {
            $fs = new Filesystem();
            $fs->mkdir(Import::THUMBNAILS_MIMIO, 0700);
            $fs->dumpFile(Import::TMP_DIR . $filename, $file->getContent());
        } catch (IOExceptionInterface $e) {
            $this->log->error('[Thumbnail] Unable to save thumbnail', [$file, $filename, $this->path]);
            return null;
        }

        $this->log->debug('[Thumbnail] Uploading thumbnail to minio', [$filename, $this->path]);
        $this->minio->upload($filename, $this->path);

        return $this->path;
    }
}
