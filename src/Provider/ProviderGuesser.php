<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\EntryMetadata;
use App\Service\EntryDownloader;
use Symfony\Component\Process\Process;
use Exception;

final class ProviderGuesser
{
    /**
     * @var \App\Provider\SoundCloud
     */
    private $soundcloud;

    /**
     * @var \App\Provider\YouTube
     */
    private $youtube;

    public function __construct(SoundCloud $soundcloud, YouTube $youtube)
    {
        $this->soundcloud = $soundcloud;
        $this->youtube = $youtube;
    }

    public function providerFromUrl(string $url): ?ProviderInterface
    {
        $knownProviders = [
            $this->soundcloud,
            $this->youtube,
        ];
        foreach ($knownProviders as $provider) {
            try {
                $provider->setUrl($url);
            } catch (\Exception $e) {
                continue;
            }

            return $provider;
        }

        return null;
    }
}
