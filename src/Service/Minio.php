<?php

namespace App\Service;

use Aws\S3\S3Client;
use Symfony\Component\Finder\SplFileInfo;
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

    public function __construct($endpoint, $key, $bucket, $secret)
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

        $this->adapter = new AwsS3Adapter($this->client, $bucket, '');
        $this->filesystem = new Filesystem($this->adapter);
    }

    public function getFileStats(SplFileInfo $file)
    {
        $getID3 = new \getID3;
        $info = $getID3->analyze($file->getRealPath());

        return [
            'size' => $info['filesize'],
            'seconds' => $info['playtime_seconds']
        ];
    }

    public function has(string $path): bool
    {
        return $this->filesystem->has($path);
    }

    public function get(string $path): ?string
    {
        if (false === $this->filesystem->has($path)) {
            return null;
        }

        return $this->filesystem->read($path);
    }

    public function listContents($path, $recursive = false)
    {
        return $this->filesystem->listContents($path, $recursive);
    }

    public function upload($file, $dest)
    {
        $path = Import::TMP_DIR;
        $file = trim(preg_replace('/\s+/', ' ', $file));
        $stream = fopen("{$path}{$file}", 'r+');

        // If uploading a file with the same name, delete it first as it can't be overwritten
        if (true === $this->filesystem->has($dest)) {
            $this->filesystem->delete($dest);
        }

        $this->filesystem->writeStream($dest, $stream);
        fclose($stream);
        unlink("{$path}{$file}");

        return true;
    }

    public function stream($path)
    {
        return $this->filesystem->readStream($path);
    }

    public function read($path)
    {
        return $this->filesystem->read($path);
    }

    public function delete($path)
    {
        return $this->filesystem->delete($path);
    }
}
