<?php

declare(strict_types=1);

namespace App\Service;

use App\Kernel;
use App\Entity\Entry;
use App\Repository\EntryRepository;
use App\Service\Minio;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Uid\Uuid;

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
     * Filename of generated thumnail in mino
     * @var string
     */
    private $filename;

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

    protected function setPath(Uuid $uuid): string
    {
        $this->filename = "{$uuid}.jpg";
        $this->path = Import::THUMBNAILS_MIMIO . "/{$this->filename}";

        return $this->path;
    }

    public function hasThumbnail(Uuid $uuid): bool
    {
        $this->setPath($uuid);

        return $this->minio->has($this->path);
    }

    public function render(Entry $entry): Response
    {
        $path = $entry->getThumbnail();
        $thumbnail = $this->minio->get($path);
        if (null === $thumbnail) {
            $path = $this->refreshThumbnail($entry);
            $thumbnail = $this->minio->get($path);
        }

        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(86400 * 30);
        $response->headers->set('Content-Type', 'image/jpg');
        $response->setContent($thumbnail);

        // Thumbnail is still null so set some headers and return image
        if (null === $thumbnail) {
            // Load 404 not found image here
            $response = new Response(null, 404);
            $response->headers->set('Content-Type', 'image/jpg');
        }

        return $response;
    }

    public function refreshThumbnail(Entry $entry): ?string
    {
        if (null === $entry->getMetadata() || 0 === count((array) $entry->getMetadata()->getData())) {
            $this->repo->fetchMetadata($entry);
        }

        $providor = $entry->getMetadata()->getProviderInstance();
        $link = $providor->getThumbnailLink();

        // Unable to find link to image url
        if (null === $link) {
            return null;
        }

        return $this->generate($entry->getUuid(), $link);
    }

    public function generate(Uuid $uuid, string $link): ?string
    {
        $this->setPath($uuid);
        $this->log->debug(
            "[Thumbnail] Downloading from {$link}" .
            "to {$this->getPath()}"
        );
        $file = (new HttpClient())->create()->request('GET', $link);

        try {
            $fs = new Filesystem();
            $fs->mkdir(Import::THUMBNAILS_MIMIO, 0700);
            $fs->dumpFile(Kernel::APP_TMPDIR . $this->filename, $file->getContent());
        } catch (IOExceptionInterface $e) {
            $this->log->error('[Thumbnail] Unable to save thumbnail to tmp storage', [$file, $this->filename, $this->path]);
            return null;
        }

        $this->log->debug("[Thumbnail] Uploading thumbnail to minio", [$this->filename, $this->path]);
        try {
            $this->minio->upload($this->filename, $this->path);
        } catch (\Exception $e) {
            $this->log->error('[Thumbnail] Unable to save thumbnail to object storage', [$file, $this->filename, $this->path]);
            return null;
        }

        $entry = $this->repo->findOneBy(['uuid' => $uuid->toBinary()]);
        if (null !== $entry) {
            $entry->setThumbnail($this->path);
            $this->repo->save($entry);
        }

        return $this->path;
    }
}
