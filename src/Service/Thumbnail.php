<?php

namespace App\Service;

use App\Service\Minio;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

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
     * Path of generated thumnail in mino
     * @var string
     */
    private $path;

    public function __construct(Minio $minio, LoggerInterface $log)
    {
        $this->minio = $minio;
        $this->log = $log;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function generate(string $uuid, string $link): string
    {
        $filename = "{$uuid}.jpg";
        $this->path = Import::THUMBNAILS_MIMIO . "/{$filename}";

        $this->log->debug("[Thumbnail] Downloading from {$link} and uploading to {$this->path}");
        $client = (new Client())->request('GET', $link, ['sink' => Import::TMP_DIR . $filename]);

        $this->log->debug('[Thumbnail] Uploading thumbnail to minio', [$filename, $this->path]);
        $this->minio->upload($filename, $this->path);

        return $this->path;
    }
}
