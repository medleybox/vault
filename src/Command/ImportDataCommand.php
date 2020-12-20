<?php

namespace App\Command;

use App\Service\ImportData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Exception;

class ImportDataCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'app:import:data';

    /**
     * @var \App\Service\ImportData
     */
    private $import;

    public function __construct(ImportData $import)
    {
        $this->import = $import;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Download and import YouTube video into the vault');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // $options = [];
        // $files = $this->import->getAvalibleImports();
        // foreach($files as $file) {
        //     $date = (new \DateTime())->setTimestamp($file['timestamp'])->format('H:i:s d-m-Y');
        //     $options[$file['basename']] = "{$date} {$file['basename']}";
        // }
        //
        // $question = new ChoiceQuestion('Please select a file to import', $options);
        // $question->setErrorMessage('%s not a invalid choice.');
        //
        // $helper = $this->getHelper('question');
        // $file = $helper->ask($input, $output, $question);
        //
        // $output->writeln("You have just selected: ${file} for import");

        $file = '71e3d50a-d99b-411f-8fe3-e625ec5baab8.csv';

        $import = $this->import->import($file);
        $output->writeln("${import} entries queued for import");


        return Command::SUCCESS;
    }
}
