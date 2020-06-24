<?php

namespace App\Repository;

use App\Entity\{Entry, EntryMetadata};
use App\Service\{Minio, Thumbnail};
use App\Provider\YouTube;

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

    public function fetchMetadata(Entry $entry)
    {
        // For the time being, it will only be youtube that will have no metadata
        $providor = new YouTube();
        $metadata = $providor->search($entry->getTitle());

        $entry->setMetadata($metadata);
        $this->_em->persist($metadata);
        $this->_em->flush();
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

    public function save(Entry $entry)
    {
        // Do webhook to webapp here to update that database
        $this->_em->flush();
    }

    public function delete(Entry $entry)
    {
        $this->minio->delete($entry->getPath());

        $this->_em->remove($entry);
        $this->_em->flush();

        return true;
    }
}
