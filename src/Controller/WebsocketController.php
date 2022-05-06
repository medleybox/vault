<?php

namespace App\Controller;

use App\Service\WebsocketClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/websocket/refreshMediaList', name: 'ws_refreshMediaList', methods: ['GET', 'HEAD'])]
    public function refreshMediaList(): Response
    {
        $this->wsClient->refreshMediaList();

        return new Response();
    }
}
