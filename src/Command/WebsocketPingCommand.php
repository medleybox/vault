<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WebsocketClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:websocket:ping',
    description: 'Ping websocket server'
)]
class WebsocketPingCommand extends Command
{
    /**
     * @var \App\Service\WebsocketClient
     */
    private $client;

    /**
     * @var \Symfony\Component\Console\Style\SymfonyStyle
     */
    private $io;

    public function __construct(WebsocketClient $client)
    {
        $this->client = $client;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->success('Connecting to ws server');

        $this->io->text('Ping:');
        try {
            while (true) {
                $this->client->ping();
                $this->io->write('.');
                sleep(30);
            }
        } catch (\Exception $e) {
            return Command::SUCCESS;
        }
    }
}
