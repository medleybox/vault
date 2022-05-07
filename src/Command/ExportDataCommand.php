<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ExportData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputDefinition, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export:data',
    description: 'Migrate data stored in minio storage'
)]
class ExportDataCommand extends Command
{
    /**
     * @var \App\Service\ExportData
     */
    private $export;

    /**
     * @var \App\Service\Minio
     */
    private $minio = null;

    public function __construct(ExportData $export)
    {
        $this->export = $export;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDefinition(
            new InputDefinition([
                new InputOption('endpoint', null, InputOption::VALUE_OPTIONAL, 'Minio endpoint to upload files'),
                new InputOption('accesskey', 'a', InputOption::VALUE_OPTIONAL, 'Minio access key'),
                new InputOption('bucket', 'b', InputOption::VALUE_OPTIONAL, 'Bucket name'),
            ])
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $endpoint = $io->ask('endpoint', $input->getOption('endpoint'));
        $accesskey = $io->ask('accesskey', $input->getOption('accesskey'));
        $bucket = $io->ask('bucket', $input->getOption('bucket'));

        $io->askHidden('secret', function ($value) use ($endpoint, $accesskey, $bucket) {
            if (null === $value || '' === trim($value)) {
                throw new \Exception('The secret value cannot be empty');
            }

            $this->minio = $this->export->checkMinioConncetion($endpoint, $accesskey, $bucket, $value);

            if (null === $this->minio) {
                throw new \Exception('Unable to connect to endpoint. Try again');
            }

            return true;
        });
        $export = $this->export->exportMinioData($this->minio, $io);
        $io->newLine();
        $io->success('Completed export to minio');

        return Command::SUCCESS;
    }
}
