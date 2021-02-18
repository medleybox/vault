<?php

namespace App\Service;

use Symfony\Component\Form\Form;
use Xigen\Bundle\GuzzleBundle\Service\GuzzleClient;

class Request
{
    const BASE_URI = 'http://webapp:8080';

    /*
     * @var \Xigen\Bundle\GuzzleBundle\Service\GuzzleClient
     */
    public $guzzle;

    public function __construct(GuzzleClient $guzzle)
    {
        $this->guzzle = $guzzle;
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
            return $this->guzzle->request(
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
            return $this->guzzle->request(
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
            return $this->guzzle->request(
                'POST',
                $url,
                [
                    'base_uri' => self::BASE_URI,
                    'form_params' => $data
                ]
            );
        } catch (\Exception $e) {
            return false;
        }
    }
}
