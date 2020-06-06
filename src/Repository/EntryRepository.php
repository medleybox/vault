<?php

namespace App\Repository;

use App\Entity\Entry;
use App\Service\Minio;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use DateTime;
use Exception;

class EntryRepository extends ServiceEntityRepository
{
    /**
     * @var \App\Service\Minio
     */
    private $minio;

    public function __construct(ManagerRegistry $registry, Minio $minio)
    {
        $this->minio = $minio;
        parent::__construct($registry, Entry::class);
    }

    public function createFromCompletedImport(ArrayCollection $data)
    {
        // Do some baisc validation on fields that are required in the database
        foreach(['uuid', 'path', 'provider', 'title', 'thumbnail', 'size', 'seconds'] as $key) {
            if (!$data->containsKey($key)) {
                throw new Exception("Missing field {$key}");
            }
        }

        $entry = (new Entry())
            ->setImported(new DateTime('now'))
            ->setUuid($data['uuid'])
            ->setPath($data['path'])
            ->setProvider($data['provider'])
            ->setTitle($data['title'])
            ->setThumbnail($data['thumbnail'])
            ->setSize($data['size'])
            ->setSeconds($data['seconds'])
        ;
        $this->persist($entry);

        return $entry;
    }

    public function delete(Entry $entry)
    {
        $this->minio->delete($entry->getPath());

        $this->_em->remove($entry);
        $this->_em->flush();

        return true;
    }

    private function persist(Entry $entry) {
        $this->_em->persist($entry);
        $this->_em->flush();

        return true;
    }
}
