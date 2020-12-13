<?php

namespace App\Provider;

interface ProviderInterface
{
    public function setUrl($url);
    public function getUrl();
    public function setId($id);
    public function getId();
    public function getDownloadLink();
    public function getThumbnailLink();
    public function fetchMetaData();
    public function getUrlFromMetadata();
    public function getTitle();
}
