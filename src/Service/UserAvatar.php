<?php

declare(strict_types=1);

namespace App\Service;

use App\Kernel;
use App\Entity\Avatar;
use App\Repository\AvatarRepository;
use App\Service\Minio;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Uid\Uuid;

class UserAvatar
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
     * Filename of generated thumnail in mino
     * @var string
     */
    private $filename;

    /**
     * Path of generated thumnail in mino
     * @var string
     */
    private $path;

    public function __construct(Minio $minio, LoggerInterface $log, private AvatarRepository $repo)
    {
        $this->minio = $minio;
        $this->log = $log;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    protected function setPath(): string
    {
        $this->path = Minio::FOLDER_AVATAR . "/{$this->filename}";

        return $this->path;
    }

    public function hasUpload(string $filename): bool
    {
        $this->filename = $filename;
        $this->setPath();

        return $this->minio->has($this->path);
    }

    public function render(Avatar $entry): Response
    {
        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(86400 * 30);
        $response->headers->set('Content-Type', $entry->getMime());
        $contents = $this->minio->get($entry->getPath());
        $response->setContent($contents);

        // contents null so set some headers and return image
        if (null === $contents) {
            // Load 404 not found image here
            $response = new Response(null, 404);
            $response->headers->set('Content-Type', 'image/jpg');
        }

        return $response;
    }

    public function generate(UploadedFile $file, string $mime): ?Uuid
    {
        $uuid = Uuid::v4();
        $ext = (new MimeTypes())->getExtensions($mime)[0];
        $this->filename = "{$uuid}.{$ext}";
        $this->setPath();

        $this->log->debug("[UserAvatar] Uploading avatar to minio", [$this->filename, $this->path]);
        try {
            $this->minio->uploadString($this->getPath(), $file->getContent());
        } catch (\Exception $e) {
            $this->log->error('[UserAvatar] Unable to save avatar to minio', [$file, $this->filename, $this->path]);
            return null;
        }

        $avatar = new Avatar();
        $avatar->setUuid($uuid);
        $avatar->setMime($mime);
        $avatar->setPath($this->path);
        $this->repo->save($avatar);

        return $uuid;
    }
}
