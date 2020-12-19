<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\{EntryRepository, EntryMetadataRepository};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use League\Csv\Writer;
use Ramsey\Uuid\Uuid;

class ExportData
{
    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var \App\Repository\EntryRepository
     */
    private $entry;

    private $headers = [
        'uuid',
        'title',
        'ref',
    ];

    public function __construct(Minio $minio, EntityManagerInterface $em, EntryRepository $entry)
    {
        $this->minio = $minio;
        $this->em = $em;
        $this->entry = $entry;
    }

    public function export()
    {
        $entries = $this->getEntries();
        $csv = $this->createCsv($entries);
        $this->upload($csv);

        return true;
    }

    private function getEntries()
    {
        $data = new ArrayCollection();
        foreach ($this->entry->findAll() as $entity) {
            $ref = null;
            if (null !== $entity->getMetadata()) {
                $ref = $entity->getMetadata()->getRef();
            }

            $data->add([
                $entity->getUuid(),
                $entity->getTitle(),
                $ref,
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
