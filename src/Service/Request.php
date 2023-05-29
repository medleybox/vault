<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Request
{
    /**
     * Connect to the nginx container to proxy to webapp (default site)
     * @var string
     */
    const BASE_URI = 'https://nginx';

    /**
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    public $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client->withOptions([
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

    public function get($url): ?ResponseInterface
    {
        try {
            return $this->client->request(
                'GET',
                $url
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    public function head($url): ?ResponseInterface
    {
        try {
            return $this->client->request(
                'HEAD',
                $url
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    public function post($url, array $data = []): ?ResponseInterface
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
            return null;
        }
    }
}
