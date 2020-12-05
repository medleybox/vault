<?php

namespace App\Repository;

use App\Entity\{Entry, EntryMetadata};
use App\Service\{Minio, Thumbnail};
use App\Provider\{ProviderInterface, YouTube};

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\DBALException;
use \DateTime;
use \Exception;

class EntryRepository extends ServiceEntityRepository
{
    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var EntryMetadataRepository
     */
     private $meta;

    public function __construct(ManagerRegistry $registry, Minio $minio, EntryMetadataRepository $meta)
    {
        $this->minio = $minio;
        $this->meta = $meta;
        parent::__construct($registry, Entry::class);
    }

    public function findViaProvider(ProviderInterface $provider): ?Entry
    {
        $check = $this->meta->findOneBy(['ref' => $provider->getId()]);
        if (null !== $check) {
            return $check->getEntry();
        }

        return null;
    }

    /**
     * Search for entry that had been marked as imported. Returns null if nothing found
     * @param  ProviderInterface $provider
     * @return \App\Entity\Entry|null
     */
    public function checkForImported(ProviderInterface $provider): ?Entry
    {
        $check = $this->meta->findOneBy(['ref' => $provider->getId()]);
        if (null === $check) {
            return null;
        }

        $entry = $check->getEntry();
        if (null !== $entry && null !== $entry->getImported()) {
            return $entry;
        }

        return null;
    }

    public function fetchMetadata(Entry $entry)
    {
        // For the time being, it will only be youtube that will have metadata
        $providor = new YouTube();
        $metadata = $providor->search($entry->getTitle());

        $entry->setMetadata($metadata);
        $this->_em->persist($metadata);
        $this->_em->flush();

        return $metadata;
    }

    /**
     * Create a new enity with minimal info. Used when initially fetching import metdata
     * @param  EntryMetadata $metadata
     * @param  string        $title
     * @param  string        $thumbnail
     * @return Entry
     */
    public function createPartialImport(EntryMetadata $metadata, ProviderInterface $provider, string $uuid, string $thumbnail): Entry
    {
        $entry = (new Entry())
            ->setUuid($uuid)
            ->setTitle($provider->getTitle())
            ->setThumbnail($thumbnail)
            ->setMetadata($metadata)
        ;
        try {
            if (null === $metadata->getId()) {
                $this->_em->persist($metadata);
            }
        } catch (DBALException $e) {
            // Maybe this is still an issue?
        }

        $this->_em->persist($entry);
        $this->_em->flush();

        return $entry;
    }

    public function createFromCompletedImport(ArrayCollection $data, EntryMetadata $metadata)
    {
        // Do some baisc validation on fields that are required in the database
        foreach(['uuid', 'path', 'title', 'thumbnail', 'size', 'seconds'] as $key) {
            if (!$data->containsKey($key)) {
                throw new Exception("Missing field {$key}");
            }
        }

        $entry = $this->findOneBy(['uuid' => $data['uuid']]);
        if (null === $entry) {
            $entry = (new Entry())
                ->setTitle($data['title'])
                ->setUuid($data['uuid'])
            ;
        }

        $entry->setImported(new DateTime('now'))
            ->setPath($data['path'])
            ->setThumbnail($data['thumbnail'])
            ->setSize($data['size'])
            ->setSeconds($data['seconds'])
        ;

        if (null === $entry->getMetadata()) {
            $entry->setMetadata($metadata);
        }

        try {
            if (null === $metadata->getId()) {
                $this->_em->persist($metadata);
            }

            if (null === $entry->getId()) {
                $this->_em->persist($entry);
            }
        } catch (DBALException $e) {
            return $entry;
        }

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
