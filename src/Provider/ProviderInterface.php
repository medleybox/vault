<?php

namespace App\Provider;

interface ProviderInterface
{
    public function toString();
    public function setUrl(string $url);
    public function getUrl();
    public function setId($id);
    public function getId();
    public function getDownloadLink();
    public function getTitle();
    public function getThumbnailLink();
    public function fetchMetaData();
    public function getUrlFromMetadata();
}
