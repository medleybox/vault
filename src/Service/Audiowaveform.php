<?php

namespace App\Service;

use App\Entity\WaveData;
use App\Repository\WaveDataRepository;
use Symfony\Component\Finder\{Finder, SplFileInfo};
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class Audiowaveform
{
    /**
     * Audiowaveform process must complete within 3 minutes
     * @var int
     */
    const AF_TIMEOUT = 180;

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
     * @var \App\Repository\WaveDataRepository
     */
    private $waveDataRepo;

    public function __construct(WaveDataRepository $waveDataRepo)
    {
        $this->waveDataRepo = $waveDataRepo;
    }

    public function generate(string $uuid, SplFileInfo $input): ?WaveData
    {
        $output = Import::TMP_DIR . "{$uuid}-audiowaveform.json";
        $args = [
            '/usr/local/bin/audiowaveform',
            '-i', $input->getRealPath(),
            '-o', $output,
            '--pixels-per-second', self::AF_PIXELS_PER_SECOND,
            '-b', self::AF_BITS,
        ];
        $process = new Process($args, Import::TMP_DIR);

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
            $newPeaks[] = $peak / $maxPeak;
        }
        $json['data'] = $newPeaks;

        $waveData = (new WaveData())->setData($json);
        $this->waveDataRepo->save($waveData);
        $filesystem->remove([$output]);

        return $waveData;
    }
}
