<?php

declare(strict_types=1);

namespace App\Service;

use App\Kernel;
use App\Provider\YouTube;
use App\Repository\{EntryRepository, EntryMetadataRepository};
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Ramsey\Uuid\Uuid;

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

    public function getAvalibleImports(): array
    {
        $names = [];
        $files = $this->minio->listContents('export/');

        usort($files, function ($a, $b) {
            return $a['timestamp'] <= $b['timestamp'];
        });

        return $files;
    }

    public function import(string $filename): int
    {
        $imported = 0;
        $path = "export/${filename}";
        $data = $this->minio->read($path);
        if (false === $data) {
            // throw
        }

        $csv = Reader::createFromString($data);
        $csv->setHeaderOffset(0);
        foreach ($csv->getRecords() as $record) {
            $this->importRecord($record);
            $imported++;
        }

        return $imported;
    }

    private function importRecord(array $record): bool
    {
        $imported = (new \DateTime())->createFromFormat(Kernel::APP_TIMEFORMAT, $record['imported']);

        $provider = new YouTube($record['ref']);
        $metadata = $provider->fetchMetadata();

        $uuid = $record['uuid'];
        $link = $provider->getThumbnailLink();
        $thumbnail = $this->thumbnail->generate($uuid, $link);
        $entry = $this->entry->createPartialImport($metadata, $provider, $uuid, $thumbnail);

        $entry->setImported($imported);
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
