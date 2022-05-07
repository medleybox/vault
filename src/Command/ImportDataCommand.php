<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImportData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Exception;

#[AsCommand(
    name: 'app:import:data',
    description: 'Import Entry entities from CSV'
)]
class ImportDataCommand extends Command
{
    /**
     * @var \App\Service\ImportData
     */
    private $import;

    public function __construct(ImportData $import)
    {
        $this->import = $import;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $options = [];
        $files = $this->import->getAvalibleImports();
        foreach ($files as $file) {
            $date = (new \DateTime())->setTimestamp($file['timestamp'])->format('H:i:s d-m-Y');
            $options[$file['basename']] = "{$date} {$file['basename']}";
        }

        $question = new ChoiceQuestion('Please select a file to import', $options);
        $question->setErrorMessage('%s not a invalid choice.');

        $helper = $this->getHelper('question');
        $file = $helper->ask($input, $output, $question);

        $output->writeln("You have just selected: ${file} for import");

        $import = $this->import->import($file);
        $output->writeln("${import} entries queued for import");

        return Command::SUCCESS;
    }
}
