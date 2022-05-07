<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Request;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-webapp-connection',
    description: 'Check connection to webapp service',
)]
class CheckWebappConnectionCommand extends Command
{
    /**
     * @var \App\Service\Request
     */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $this->request->checkConnectivity();
            $io->success('Connected to webapp service!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
        }

        $io->error('Failed to connect to the webapp service');

        return Command::FAILURE;
    }
}
