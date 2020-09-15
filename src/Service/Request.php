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
            if (200 === $version->getStatusCode()) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    public function get($url, array $data = [])
    {
        return $this->guzzle->request(
            'GET',
            $url,
            ['base_uri' => SELF::BASE_URI]
        );
    }

    public function post($url, array $data = [])
    {
        return $this->guzzle->request(
            'POST',
            $url,
            [
                'base_uri' => SELF::BASE_URI,
                'form_params' => $data
            ]
        );
    }
}
