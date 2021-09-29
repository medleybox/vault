<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Request
{
    const BASE_URI = 'http://webapp:9501';

    /*
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    public $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function checkConnectivity(): bool
    {
        try {
            $version = $this->get('/api/version');
            if (false !== $version && 200 === $version->getStatusCode()) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    public function get($url)
    {
        try {
            return $this->client->request(
                'GET',
                $url,
                ['base_uri' => self::BASE_URI]
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    public function head($url)
    {
        try {
            return $this->client->request(
                'HEAD',
                $url,
                ['base_uri' => self::BASE_URI]
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    public function post($url, array $data = [])
    {
        try {
            return $this->client->request(
                'POST',
                $url,
                [
                    'base_uri' => self::BASE_URI,
                    'body' => $data
                ]
            );
        } catch (\Exception $e) {
            return false;
        }
    }
}
