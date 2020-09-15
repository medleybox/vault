<?php

namespace App\Command;

use App\Service\Request;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckWebappConnectionCommand extends Command
{
    protected static $defaultName = 'app:check-webapp-connection';

    /**
     * @var \App\Service\Request
     */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Check the connection to webapp');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (true === $this->request->checkConnectivity()) {
            $io->success('Connected to webapp service!');

            return 0;
        }

        $io->error('Failed to connect to the webapp service');

        return 1;
    }
}
