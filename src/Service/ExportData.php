<?php

declare(strict_types=1);

namespace App\Service;

use App\Kernel;
use App\Entity\Entry;
use App\Repository\{EntryRepository, EntryMetadataRepository};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use League\Csv\Writer;
use Ramsey\Uuid\Uuid;

class ExportData
{
    /**
     * @var \App\Repository\EntryRepository
     */
    private $entry;

    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var \App\Service\Request
     */
    private $request;

    /**
     * Headers of the output CSV
     * @var array
     */
    private $headers = [
        'uuid',
        'title',
        'ref',
        'imported',
    ];

    public function __construct(EntryRepository $entry, Minio $minio, Request $request)
    {
        $this->entry = $entry;
        $this->minio = $minio;
        $this->request = $request;
    }

    public function export(): string
    {
        $csv = $this->createCsv($this->getEntries());
        $name = $this->upload($csv);

        return $name;
    }

    private function getEntries()
    {
        $data = new ArrayCollection();
        foreach ($this->entry->findAll() as $entity) {
            $uuid = $entity->getUuid();
            $data->containsKey($uuid);
            if ($data->containsKey($uuid)) {
                dump('Duplicate entry found, skipping ....');
                dump($uuid, $entity);
                continue;
            }

            $ref = null;
            if (null !== $entity->getMetadata()) {
                $ref = $entity->getMetadata()->getRef();
            }

            $check = $this->checkForMediaFile($entity);
            if (false === $check) {
                dump('Unable to find entry in webapp, skipping ....');
                dump($uuid);
                continue;
            }

            $imported = (new \DateTime())->format(Kernel::APP_TIMEFORMAT);
            if (null !== $entity->getImported()) {
                $imported = $entity->getImported()->format(Kernel::APP_TIMEFORMAT);
            }

            $data->set($uuid, [
                $uuid,
                $entity->getTitle(),
                $ref,
                $imported,
            ]);
        }

        return $data;
    }

    private function checkForMediaFile(Entry $entry): bool
    {
        try {
            $check = $this->request->head("/media-file/metadata/{$entry->getUuid()}");
            if (false !== $check && 200 === $check->getStatusCode()) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    private function createCsv(ArrayCollection $data)
    {
        $csv = Writer::createFromString('');
        $csv->insertOne($this->headers);
        $csv->insertAll($data);

        return $csv->getContent();
    }

    private function upload($csv)
    {
        $name = Uuid::uuid4()->toString();
        $path = "export/${name}.csv";
        $this->minio->uploadString($path, $csv);

        return $name;
    }
}
