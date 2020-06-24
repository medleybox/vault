<?php

namespace App\Repository;

use App\Entity\{Entry, EntryMetadata};
use App\Service\{Minio, Thumbnail};

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

    /**
     * @var \App\Service\Thumbnail
     */
    private $thumbnail;

    public function __construct(ManagerRegistry $registry, Minio $minio, Thumbnail $thumbnail)
    {
        $this->minio = $minio;
        $this->thumbnail = $thumbnail;
        parent::__construct($registry, Entry::class);
    }

    public function refreshThumbnail(Entry $entry)
    {

    }

    public function createFromCompletedImport(ArrayCollection $data, EntryMetadata $metadata)
    {
        // Do some baisc validation on fields that are required in the database
        foreach(['uuid', 'path', 'title', 'thumbnail', 'size', 'seconds'] as $key) {
            if (!$data->containsKey($key)) {
                throw new Exception("Missing field {$key}");
            }
        }

        $entry = (new Entry())
            ->setImported(new DateTime('now'))
            ->setUuid($data['uuid'])
            ->setPath($data['path'])
            ->setTitle($data['title'])
            ->setThumbnail($data['thumbnail'])
            ->setSize($data['size'])
            ->setSeconds($data['seconds'])
            ->setMetadata($metadata)
        ;
        $this->_em->persist($metadata);
        $this->_em->persist($entry);
        $this->_em->flush();

        return $entry;
    }

    public function delete(Entry $entry)
    {
        $this->minio->delete($entry->getPath());

        $this->_em->remove($entry);
        $this->_em->flush();

        return true;
    }
}
