<?php

namespace App\Provider;

use Madcoda\Youtube\Youtube as YouTubeApi;
use Exception;

final class YouTube implements ProviderInterface
{
    private $url;

    /**
     * The ID for the YouTube video
     * @var string
     */
    private $id;

    /**
     * https://github.com/madcoda/php-youtube-api
     * @var \Madcoda\Youtube\Youtube
     */
    private $api;

    /**
     * Metadata from the YouTube API relating to this video
     * @var array
     */
    private $metadata = [];

    public function __construct($url)
    {
        $this->api = new YouTubeApi([
            'key' => $_ENV['API_GOOGLE']
        ]);
        $this->setUrl($url);
    }

    public function setUrl($url)
    {
        $this->url = $url;
        $this->setIdFromUrl();

        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getDownloadLink()
    {
        return "https://www.youtube.com/watch?v={$this->id}";
    }

    public function getThumbnailLink()
    {
        if ($this->metadata === []) {
            $this->fetchMetaData();
        }

        return $this->metadata['thumbnail'];
    }

    private function setIdFromUrl()
    {
        $this->id = $this->getIdFromUrl();
    }

    private function getIdFromUrl(): string
    {
        $match = null;
        preg_match('/[a-zA-Z0-9\-_]{11}/m', $this->url, $match);
        if ([] !== $match && 1 === count($match)) {
            return $match[0];
        }

        $params = null;
        parse_str(parse_url($this->url, PHP_URL_QUERY), $params);
        print_r($params);

        if (!array_key_exists('v', $params)) {
            throw new Exception("Unable to find video id in link", 1);
        }

        return $params['v'];
    }

    public function fetchMetaData()
    {
        // Check if the metadata has been fetched
        if ([] !== $this->metadata) {
            return $this->metadata;
        }

        $thumbnail = null;
        $fetch = $this->api->getVideoInfo($this->id);

        if (isset($fetch->snippet->thumbnails->maxres)) {
            $thumbnail = $fetch->snippet->thumbnails->maxres->url;
        }

        if (null !== $fetch && isset($fetch->snippet->thumbnails->medium)) {
            $thumbnail = $fetch->snippet->thumbnails->medium->url;
        }

        // Fallback to the default thumbnail
        if (null === $fetch) {
            $thumbnail = $fetch->snippet->thumbnails->default->url;
        }

        $this->metadata = [
            'title' => $fetch->snippet->title,
            'artist' => $fetch->snippet->channelTitle,
            'thumbnail' => $thumbnail
        ];

        return $this->metadata;
    }
}
