<?php

declare(strict_types=1);

namespace App\Service;

use App\Kernel;
use App\Repository\{EntryRepository, EntryMetadataRepository};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use League\Csv\Writer;
use Ramsey\Uuid\Uuid;

class ExportData
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
     * Headers of the output CSV
     * @var array
     */
    private $headers = [
        'uuid',
        'title',
        'ref',
        'imported',
    ];

    public function __construct(EntityManagerInterface $em, EntryRepository $entry, Minio $minio)
    {
        $this->em = $em;
        $this->entry = $entry;
        $this->minio = $minio;
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
            $ref = null;
            if (null !== $entity->getMetadata()) {
                $ref = $entity->getMetadata()->getRef();
            }

            $imported = (new \DateTime())->format(Kernel::APP_TIMEFORMAT);
            if (null !== $entity->getImported()) {
                $imported = $entity->getImported()->format(Kernel::APP_TIMEFORMAT);
            }

            $data->add([
                $entity->getUuid(),
                $entity->getTitle(),
                $ref,
                $imported,
            ]);
        }

        return $data;
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
