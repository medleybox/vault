<?php

namespace App\Command;

use App\Service\WebsocketServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WebsocketStartCommand extends Command
{
    protected static $defaultName = 'app:websocket:start';
    protected static $defaultDescription = 'Start the websocket server';

    public function __construct(WebsocketServer $ws)
    {
        $this->ws = $ws;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription(self::$defaultDescription);
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
        $io->success('Initializing websocket server');
        $server->run();

        return Command::SUCCESS;
    }
}
