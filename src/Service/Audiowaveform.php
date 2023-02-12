<?php

declare(strict_types=1);

namespace App\Service;

use App\Kernel;
use App\Entity\WaveData;
use App\Repository\WaveDataRepository;
use Symfony\Component\Finder\{Finder, SplFileInfo};
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class Audiowaveform
{
    /**
     * Path to audiowaveform binary
     * @var string
     */
    const AUDIOWAVEFORM = '/usr/local/bin/audiowaveform';

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

    public function getVersion(): string
    {
        $process = new Process([self::AUDIOWAVEFORM, '--version'], Kernel::APP_TMPDIR);
        $process->run();
        preg_match('/([0-9]{1,}\.)+[0-9]{1,}/', $process->getOutput(), $output);

        return $output[0];
    }

    public function generate(string $uuid, SplFileInfo $input): ?WaveData
    {
        $output = Kernel::APP_TMPDIR . "{$uuid}-audiowaveform.json";
        $args = [
            self::AUDIOWAVEFORM,
            '-i', $input->getRealPath(),
            '-o', $output,
            '--pixels-per-second', self::AF_PIXELS_PER_SECOND,
            '-b', self::AF_BITS,
        ];
        $process = new Process($args, Kernel::APP_TMPDIR);

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
