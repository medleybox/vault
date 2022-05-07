<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WebsocketServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:websocket:start',
    description: 'Start the websocket server'
)]
class WebsocketStartCommand extends Command
{
    /**
     * @var \App\Service\WebsocketServer
     */
    private $ws;

    public function __construct(WebsocketServer $ws)
    {
        $this->ws = $ws;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = '0.0.0.0';
        $port = 8089;

        $io = new SymfonyStyle($input, $output);
        $io->info(["host: ${host}","port: ${port}"]);

        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    $this->ws
                )
            ),
            $port
        );
        $server->run();
        $io->success('Initializing websocket server');

        return Command::SUCCESS;
    }
}
