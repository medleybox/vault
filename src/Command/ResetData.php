<?php

namespace App\Command;

use App\Provider\YouTube;
use App\Service\ResetData as ResetDataService;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ResetData extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'app:reset-data';

    /**
     * @var \App\Service\ResetData
     */
    private $resetData;

    public function __construct(ResetDataService $resetData)
    {
        $this->resetData = $resetData;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Remove all data stored in minio and the vault database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->caution('Removing files and data from Vault');

        $this->resetData->removeThubmnails();

        $this->resetData->removeProviderData();

        $this->resetData->removeEntities();

        return 0;
    }
}
