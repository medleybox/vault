<?php

namespace App\Command;

use App\Service\WebsocketClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

    protected static $defaultName = 'app:websocket:ping';

    protected static $defaultDescription = 'Ping websocket server';

    public function __construct(WebsocketClient $client)
    {
        $this->client = $client;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription(self::$defaultDescription);
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
