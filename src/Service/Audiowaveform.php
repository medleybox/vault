<?php

namespace App\Service;

use App\Entity\WaveData;
use App\Provider\ProviderInterface;
use App\Repository\WaveDataRepository;
use App\Message\ImportJob;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\{Finder, SplFileInfo};
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Messenger\MessageBusInterface;

final class Audiowaveform
{
    /**
     * Audiowaveform process must complete within 30 seconds
     * @var int
     */
    const AF_TIMEOUT = 30;

    /**
     * Bits (8 or 16)
     * @var int
     */
    const AF_BITS = 16;

    /**
     * Zoom level (pixels per second)
     * @var int
     */
    const AF_PIXELS_PER_SECOND = 5;

    /**
     * File downloaded via youtube-dl
     * @var \Symfony\Component\Finder\SplFileInfo
     */
    private $file;

    /**
     * @var \App\Repository\WaveDataRepository
     */
    private $waveDataRepo;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    public function __construct(WaveDataRepository $waveDataRepo, LoggerInterface $log)
    {
        $this->waveDataRepo = $waveDataRepo;
        $this->log = $log;
    }

    public function generate(String $uuid, SplFileInfo $input): ?WaveData
    {
        $output = Import::TMP_DIR . "{$uuid}-audiowaveform.json";
        $process = new Process([
            '/usr/local/bin/audiowaveform',
            '-i', $input->getRealPath(),
            '-o', $output,
            '--pixels-per-second', self::AF_PIXELS_PER_SECOND,
            '-b', self::AF_BITS,
        ], Import::TMP_DIR);

        $process->setTimeout(self::AF_TIMEOUT);
        $process->run();

        $filesystem = new Filesystem();
        if (false === $filesystem->exists($output)) {
            return null;
        }

        // Grab output and normalize
        $data = file_get_contents($output);
        $json = json_decode($data, true);

        $peaks = $json['data'];
        $maxPeak = floatval(max($peaks));
        $newPeaks = [];
        foreach ($peaks as $peak) {
            $newPeaks[] = $peak/$maxPeak;
        }
        $json['data'] = $newPeaks;

        $waveData = (new WaveData())->setData($json);
        $this->waveDataRepo->save($waveData);
        $filesystem->remove([$output]);

        return $waveData;
    }
}
