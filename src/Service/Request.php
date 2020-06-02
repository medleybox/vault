<?php

namespace App\Service;

use Symfony\Component\Form\Form;

use Xigen\Bundle\GuzzleBundle\Service\GuzzleClient;

class Request
{
    const BASE_URI = 'http://webapp';

    /*
     * @var \Xigen\Bundle\GuzzleBundle\Service\GuzzleClient
     */
    public $guzzle;

    public function __construct(GuzzleClient $guzzle)
    {
        $this->guzzle = $guzzle;
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
