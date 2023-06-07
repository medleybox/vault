<?php

declare(strict_types=1);

namespace App\Service;

use App\Kernel;
use App\Provider\YouTube;
use App\Repository\{EntryRepository, EntryMetadataRepository};
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\{Reader, Statement};
use Symfony\Component\Uid\Uuid;
use Exception;

class ImportData
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var \App\Repository\EntryRepository
     */
    private $entry;

    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var \App\Service\Import
     */
    private $import;

    /**
     * @var \App\Service\Thumbnail
     */
    private $thumbnail;

    public function __construct(EntityManagerInterface $em, EntryRepository $entry, Minio $minio, Import $import, Thumbnail $thumbnail)
    {
        $this->em = $em;
        $this->entry = $entry;
        $this->minio = $minio;
        $this->import = $import;
        $this->thumbnail = $thumbnail;
    }

    private function sortImports($a, $b): bool
    {
        return $a['timestamp'] <= $b['timestamp'];
    }

    public function getAvalibleImports(): array
    {
        $names = [];
        $files = $this->minio->listContents('export/')->toArray();
        usort($files, [$this, 'sortImports']);

        return $files;
    }

    public function import(string $filename): int
    {
        $imported = 0;
        $path = "export/${filename}";
        $data = $this->minio->read($path);
        if (null === $data) {
            // throw
        }

        $csv = Reader::createFromString($data);
        $csv->setHeaderOffset(0);
        // Sort resocrds within the csv by imported so they're imported in order
        $stmt = (new Statement())
            ->offset(0)
            ->orderBy(function ($a, $b) {
                return $b['imported'] >= $a['imported'] ? 0 : 1;
            })
        ;
        $records = $stmt->process($csv);

        foreach ($records as $record) {
            try {
                $this->importRecord($record);
                $imported++;
            } catch (Exception $e) {
                continue;
            }
        }

        return $imported;
    }

    private function importRecord(array $record): bool
    {
        $imported = (new \DateTime())->createFromFormat(Kernel::APP_TIMEFORMAT, $record['imported']);

        //TODO - Replace with provider guesser
        $provider = (new YouTube())->setUrl($record['ref']);

        // First check for import
        $entry = $this->entry->findViaProvider($provider);
        if (null !== $entry) {
            throw new Exception('Entry has already been imported');
        }

        $metadata = $provider->fetchMetadata();

        $uuid = Uuid::fromString($record['uuid']);
        $link = $provider->getThumbnailLink();
        $thumbnail = $this->thumbnail->generate($uuid, $link);
        $entry = $this->entry->createPartialImport($metadata, $provider, $uuid, $thumbnail);

        $this->em->flush();

        try {
            $this->import->setUp($provider, $uuid);
        } catch (\Exception $e) {
            dump($e->getMessage());

            return false;
        }

        return $this->import->queue();
    }
}
