<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\EntryMetadata;
use App\Service\EntryDownloader;
use Symfony\Component\Process\Process;
use Exception;

final class SoundCloud extends BaseProvider implements ProviderInterface
{
    /**
     * @var string
     */
    const REGEX = '/(www\.)?soundcloud\.com\/(?\'user\'.*)\/(?\'track\'.*)/m';

    /**
     * @var string
     */
    const BASE_URI = 'https://soundcloud.com';

    /**
     * The user that uploaded the media to soundcloud
     * @var string
     */
    public $user;

    /**
     * @var \App\Service\EntryDownloader
     */
    public $downloader;

    public function __construct(EntryDownloader $downloader = null)
    {
        $this->downloader = $downloader;
    }

    public function setUrl(string $url)
    {
        $this->url = $url;
        $this->setIdFromUrl();

        return $this;
    }

    private function setUser(string $user): self
    {
        $this->user = $user;

        return $this;
    }

    private function getUser(): ?string
    {
        return $this->user;
    }

    /**
     * Used to import the video
     * @return string
     */
    public function getDownloadLink(): string
    {
        $this->setIdFromUrl();
        return self::BASE_URI . "/{$this->getUser()}/{$this->getId()}";
    }

    public function search($title)
    {
        // todo
    }

    public function findRef($title): ?string
    {
        // todo
        return null;
    }

    public function getTitle()
    {
        return $this->fetchMetaData()->get('fulltitle');
    }

    public function getThumbnailLink(): ?string
    {
        $link = $this->fetchMetaData()->get('thumbnail');
        if (null !== $link) {
            return $link;
        }
        // move to EntryDownloader
        //$this->downloader

        return null;
    }

    public function fetchMetaData()
    {
        // Check if the metadata has been fetched
        if (null !== $this->metadata && (array) $this->metadata->getData() !== []) {
            return $this->metadata;
        }

        $metadata = $this->downloader->getMetadata($this->getDownloadLink());
        unset(
            $metadata['thumbnails'],
            $metadata['http_headers'],
            $metadata['formats'],
        );

        if (null === $this->metadata) {
            $this->metadata = (new EntryMetadata());
        }

        $this->metadata->setRef($this->id)
            ->setData($metadata)
            ->setProvider(self::class)
        ;

        return $this->metadata;
    }

    public function getUrlFromMetadata()
    {
        // todo
    }

    private function setIdFromUrl()
    {
        $check = $this->getIdFromUrl();
        $this->setId($check['track']);
        $this->setUser($check['user']);
    }

    private function getIdFromUrl(): array
    {
        if (str_contains($this->url, '/sets/')) {
            throw new Exception("Searching sets currently not supported", 1);
        }

        preg_match(self::REGEX, $this->url, $match);
        if (array_key_exists('user', $match) && array_key_exists('track', $match)) {
            $set = explode('/', $match['user']);
            if (count($set) < 2) {
                return [
                    'user' => $match['user'],
                    'track' => $match['track'],
                ];
            }

            $user = $set[0];
            $track = $set[1];
            $splitTrack = explode('?in=', $track);

            if (array_key_exists(0, $splitTrack)) {
                $track = $splitTrack[0];
            }

            return [
                'user' => $user,
                'track' => $track,
            ];
        }

        throw new Exception("Unable to find track from link", 1);
    }
}
