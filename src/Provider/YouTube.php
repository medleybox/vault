<?php

namespace App\Provider;

use App\Entity\EntryMetadata;
use Madcoda\Youtube\Youtube as YouTubeApi;
use Exception;

final class YouTube implements ProviderInterface
{
    /**
     * URL of import
     * @var string
     */
    private $url = null;

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
     * @var \App\Entity\EntryMetadata
     */
    private $metadata = null;

    public function __construct($url = null)
    {
        $this->api = new YouTubeApi([
            'key' => $_ENV['API_GOOGLE']
        ]);
        if (null !== $url) {
            $this->setUrl($url);
        }
    }

    public function toString()
    {
        return get_class($this);
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
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

    public function setMetadata(EntryMetadata $metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Used to import the video
     * @return string
     */
    public function getDownloadLink(): string
    {
        return "https://www.youtube.com/watch?v={$this->id}";
    }

    public function getTitle()
    {
        if (null === $this->metadata) {
            $this->fetchMetaData();
        }

        $data = $this->metadata->getData();
        if (is_array($data)) {
            return $data['title'];
        }

        return $data->snippet->title;
    }

    private function tryFallbackMetadata()
    {
        // https://stackoverflow.com/a/68492807
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.youtube.com/youtubei/v1/player?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"context":{"client":{"hl":"en","clientName":"WEB","clientVersion":"2.20210721.00.00","clientFormFactor":"UNKNOWN_FORM_FACTOR","clientScreen":"WATCH","mainAppWebInfo":{"graftUrl":"/watch?v=' . $this->getId() . '",}},"user":{"lockedSafetyMode":false},"request":{"useSsl":true,"internalExperimentFlags":[],"consistencyTokenJars":[]}},"videoId":"' . $this->getId() . '","playbackContext":{"contentPlaybackContext":{"vis":0,"splay":false,"autoCaptionsDefaultOn":false,"autonavState":"STATE_NONE","html5Preference":"HTML5_PREF_WANTS","lactMilliseconds":"-1"}},"racyCheckOk":false,"contentCheckOk":false}');
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
            return false;
        }
        $json = json_decode($result, true);
        curl_close($ch);

        return $json;
    }

    public function getThumbnailLink(): ?string
    {
        // This is a URL to fallback to if a thumbnail link isn't found
        $defaultThumbnail = "https://img.youtube.com/vi/{$this->getId()}/default.jpg";

        // Fetch metadata from YouTube API
        if (null === $this->metadata) {
            $this->fetchMetaData();
        }

        // Unable to load metadata from API
        if (null === $this->metadata) {
            return $defaultThumbnail;
        }

        $data = $this->metadata->getData();
        if (is_array($data)) {
            $thumbnails = $data['thumbnail']['thumbnails'];
            return $thumbnails[count($thumbnails) - 1]['url'];
        }

        // Check if the metadata stored has snippet data
        if (false === property_exists($data, 'snippet')) {
            return $defaultThumbnail;
        }

        $thumbnail = null;
        if (isset($data->snippet->thumbnails->maxres)) {
            $thumbnail = $data->snippet->thumbnails->maxres->url;
        }
        if (null !== $data && isset($data->snippet->thumbnails->medium)) {
            $thumbnail = $data->snippet->thumbnails->medium->url;
        }

        // Fallback to the default thumbnail
        if (null === $thumbnail) {
            $thumbnail = $data->snippet->thumbnails->default->url;
        }

        // Fallback to the default thumbnail
        if (null === $thumbnail) {
            $thumbnail = $defaultThumbnail;
        }

        return $thumbnail;
    }

    public function fetchMetaData()
    {
        // Check if the metadata has been fetched
        if (null !== $this->metadata && (array) $this->metadata->getData() !== []) {
            return $this->metadata;
        }

        try {
            $data = $this->api->getVideoInfo($this->id);
        } catch (\Exception $e) {
            $data = $this->tryFallbackMetadata();
            if (null === $data) {
                return false;
            }

            $data = $data["videoDetails"];
            $data['isFallback'] = true;
        }

        // Remove data that isn't required so shouldn't get stored
        unset(
            $data->contentDetails,
            $data->player,
            $data->tags,
            $data->localized,
            $data->status,
            $data->snippet->tags
        );

        if (null === $this->metadata) {
            $this->metadata = (new EntryMetadata());
        }

        $this->metadata->setRef($this->id)
            ->setData($data)
            ->setProvider(self::class)
        ;

        return $this->metadata;
    }

    public function getUrlFromMetadata()
    {
        if (null === $this->metadata) {
            $this->fetchMetaData();
        }

        $data = $this->metadata->getData();

        return $data->id;
    }

    public function search($title)
    {
        try {
            $search = $this->api->searchVideos($title, 1);
        } catch (\Exception $e) {
            return false;
        }

        $this->id = $search[0]->id->videoId;
        $this->setUrl($this->getDownloadLink());

        return $this->fetchMetaData();
    }

    public function findRef($title): ?string
    {
        try {
            $search = $this->api->searchVideos($title, 1);
        } catch (\Exception $e) {
            return null;
        }

        if (false == (bool) $search) {
            return null;
        }

        return $search[0]->id->videoId;
    }

    private function setIdFromUrl()
    {
        $this->setId($this->getIdFromUrl());
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
        if (!array_key_exists('v', $params)) {
            throw new Exception("Unable to find video id in link", 1);
        }

        return $params['v'];
    }
}
