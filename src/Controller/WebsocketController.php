<?php

namespace App\Controller;

use App\Service\WebsocketClient;
use GuzzleHttp\Psr7\Stream;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, ResponseHeaderBag};
use Symfony\Component\Routing\Annotation\Route;

class WebsocketController extends AbstractController
{
    /**
     * @var \App\Service\WebsocketClient
     */
    private $wsClient;

    public function __construct(WebsocketClient $wsClient)
    {
        $this->wsClient = $wsClient;
    }

    /**
     * @Route("/websocket/refreshMediaList", name="ws_refreshMediaList", methods={"GET", "HEAD"})
     */
    public function refreshMediaList()
    {
        $this->wsClient->refreshMediaList();

        return new Response();
    }
}
