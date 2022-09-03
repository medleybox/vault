<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Minio;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-minio-connection',
    description: 'Check connection to minio service',
)]
class CheckMinioConnectionCommand extends Command
{
    /**
     * @var \App\Service\Minio
     */
    private $minio;

    public function __construct(Minio $minio)
    {
        $this->minio = $minio;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $this->minio->testConnection();
            $io->success('Connected to minio service!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
        }

        $io->error('Failed to connect to the minio service');

        return Command::FAILURE;
    }
}
