<?php

namespace App\Command;

use App\Service\WebsocketClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WebsocketTestCommand extends Command
{
    /**
     * @var \App\Service\WebsocketClient
     */
    private $client;

    /**
     * @var \Symfony\Component\Console\Style\SymfonyStyle
     */
    private $io;

    protected static $defaultName = 'app:websocket:test';

    protected static $defaultDescription = 'Test websocket server';

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

        $this->client->testing();

        $this->io->success('Disconnected from ws server');

        return Command::SUCCESS;
    }
}
