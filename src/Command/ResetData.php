<?php

declare(strict_types=1);

namespace App\Command;

use App\Provider\YouTube;
use App\Service\ResetData as ResetDataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-data',
    description: 'Remove all data stored in minio and the vault database'
)]
class ResetData extends Command
{
    /**
     * @var \App\Service\ResetData
     */
    private $resetData;

    public function __construct(ResetDataService $resetData)
    {
        $this->resetData = $resetData;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->caution('Removing files and data from Vault');

        $io->text('Removing thumbnails');
        $this->resetData->removeThumbnails();

        $io->text('Removing metadata');
        $this->resetData->removeProviderData();

        $io->text('Removing entity');
        $this->resetData->removeEntities();

        return Command::SUCCESS;
    }
}
