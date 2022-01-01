<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Request
{
    // Connect to the nginx container to proxy to webapp (default site)
    const BASE_URI = 'https://nginx';

    /*
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    public $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $this->client = $client->withOptions([
            'base_uri' => self::BASE_URI,
            'verify_host' => false,
            'verify_peer' => false
        ]);
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
                $url
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
                $url
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
                    'body' => $data
                ]
            );
        } catch (\Exception $e) {
            return false;
        }
    }
}
