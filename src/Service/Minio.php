<?php

declare(strict_types=1);

namespace App\Service;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Symfony\Component\Finder\SplFileInfo;
use Psr\Log\LoggerInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;

/**
 * Copied and adapted from https://github.com/medleybox/import/blob/master/src/Service/Minio.php
 */
class Minio
{
    /**
     * @var \Aws\S3\S3Client
     */
    protected $client;

    /**
     * @var \League\Flysystem\AwsS3v3\AwsS3Adapter
     */
    protected $adapter;

    /**
     * @var \League\Flysystem\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    public function __construct(string $endpoint, string $key, string $bucket, string $secret, LoggerInterface $log)
    {
        $this->connect($endpoint, $key, $bucket, $secret);
        $this->log = $log;
    }

    /**
     * Setup the call with a connection to minio
     */
    public function connect(string $endpoint, string $key, string $bucket, string $secret): void
    {
        $this->client = new S3Client([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
            'http'    => [
                'connect_timeout' => 5
            ]
        ]);

        $this->adapter = new AwsS3Adapter($this->client, $bucket, '', [], false);
        $this->filesystem = new Filesystem($this->adapter);
    }

    /**
     * Test connection to minio intance by listing a directory
     */
    public function testConnection(): bool
    {
        try {
            $list = $this->listContents('youtube', false);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function getFileStats(SplFileInfo $file): array
    {
        $getID3 = new \getID3();
        $info = $getID3->analyze($file->getRealPath());
        $stats = [
            'size' => null,
            'seconds' => null,
        ];
        if (array_key_exists('filesize', $info)) {
            $stats['size'] = $info['filesize'];
        }
        if (array_key_exists('playtime_seconds', $info)) {
            $stats['seconds'] = $info['playtime_seconds'];
        }

        return $stats;
    }

    public function has(string $path): bool
    {
        return $this->filesystem->has($path);
    }

    public function get(string $path): ?string
    {
        if (false === $this->has($path)) {
            return null;
        }

        return $this->filesystem->read($path);
    }

    public function listContents($path, $recursive = false): array
    {
        return $this->filesystem->listContents($path, $recursive);
    }

    public function upload($file, $dest): bool
    {
        $path = Import::TMP_DIR;
        $file = trim(preg_replace('/\s+/', ' ', $file));
        $stream = fopen("{$path}{$file}", 'r+');

        $this->log->debug("[Minio] Starting upload of stream to {$dest}");

        // If uploading a file with the same name, delete it first as it can't be overwritten
        if (true === $this->has($dest)) {
            $this->log->debug("[Minio][upload] Replacing file in target minio {$dest}");
            $this->filesystem->delete($dest);
        }

        $this->filesystem->writeStream($dest, $stream);
        $this->log->info("[Minio] Completed upload via stream to {$dest}");
        fclose($stream);
        unlink("{$path}{$file}");

        return true;
    }

    public function uploadString(string $dest, string $contents): bool
    {
        // If uploading a file with the same name, delete it first as it can't be overwritten
        if (true === $this->has($dest)) {
            $this->log->debug("[Minio][uploadString] Replacing file in target minio {$dest}");
            $this->filesystem->delete($dest);
        }

        $this->log->debug("[Minio] Starting upload of string to {$dest}");
        $this->filesystem->write($dest, $contents);
        $this->log->debug("[Minio] Filesystem has check returned false");

        return true;
    }

    public function stream(string $path): mixed
    {
        if (false === $this->has($path)) {
            $this->log->debug("[Minio] Filesystem has check returned false before stream");

            return null;
        }

        try {
            return $this->filesystem->readStream($path);
        } catch (\League\Flysystem\FileNotFoundException $e) {
            $this->log->error("[Minio] Unable to find file");
            $this->log->debug("[Minio] {$e->getMessage()}");
        }

        return false;
    }

    public function read(string $path): ?string
    {
        try {
            $this->log->debug("[Minio] started read for {$path}");

            return $this->filesystem->read($path);
        } catch (\Exception $e) {
            $this->log->error("[Minio] Failed to read path {$path}");
        }

        return null;
    }

    public function delete(string $path): bool
    {
        $this->log->debug("[Minio] started delete of {$path}");

        return $this->filesystem->delete($path);
    }

    public function mirror(self $minio, string $path): bool
    {
        $stream = $this->stream($path);
        if (null === $stream) {
            $this->log->debug("[Minio] stream is null while trying to mirror");

            return false;
        }

        if (false === $stream) {
            $this->log->debug("[Minio] stream is false while trying to mirror");

            return false;
        }

        // If uploading a file with the same name, delete it first as it can't be overwritten
        if (true === $minio->has($path)) {
            $this->log->debug("[Minio][mirror] Replacing file in target minio {$path}");
            $minio->delete($path);
        }

        $write = $minio->filesystem->writeStream($path, $stream);
        $this->log->info("[Minio] Completed mirror via stream");

        return true;
    }
}
