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

final class EntryDownloader
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
     * Download and convert must complete within 600 seconds (10 minutes)
     * @var int
     */
    const DOWNLOAD_TIMEOUT = 600;

    public function __construct()
    {
        // $this->minio = $minio;
    }

    public function getMetadata(string $link)
    {
        $process = new Process([self::DOWNLOADER, '--dump-json', $link]);
        $process->run();

        if (false === $process->isSuccessful()) {
            return null;
        }

        return json_decode($process->getOutput(), true);
    }
}
