<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Entry, EntryMetadata};
use App\Service\{Minio, Request, Thumbnail, WebsocketClient};
use App\Provider\{ProviderInterface, YouTube};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\Uid\Uuid;
use DateTime;
use Exception;

class EntryRepository extends ServiceEntityRepository
{
    /**
     * @var \Doctrine\Persistence\ManagerRegistry
     */
    protected $registry;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $_em;

    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var EntryMetadataRepository
     */
     private $meta;

    /**
     * @var \App\Service\Request
     */
    private $request;

        /**
     * @var \App\Service\WebsocketClient
     */
    private $wsClient;

    public function __construct(ManagerRegistry $registry, Minio $minio, EntryMetadataRepository $meta, Request $request, WebsocketClient $wsClient)
    {
        $this->registry = $registry;
        $this->minio = $minio;
        $this->meta = $meta;
        $this->request = $request;
        $this->wsClient = $wsClient;
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

    public function findViaUuid(Uuid $uuid): ?Entry
    {
        $entry = $this->findOneBy(['uuid' => $uuid]);
        if (null !== $entry) {
            return $entry;
        }

        return null;
    }

    public function listAll(): array
    {
        $qb = $this->_em->createQueryBuilder()
            ->from(Entry::class, 'e', 'e.uuid')
            ->select('partial e.{id, uuid, thumbnail, title}')
            ->leftJoin('e.waveData', 'w')
        ;

        return $qb->getQuery()->getArrayResult();
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

    public function hasWebapp(Entry $entry): ?bool
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

    public function hasStream(Entry $entry): ?bool
    {
        $path = $entry->getPath();
        if (null === $path) {
            return null;
        }

        $stream = $this->minio->stream($path);
        if (is_resource($stream)) {
            return true;
        }

        return false;
    }

    public function metadata(Entry $entry): array
    {
        $metadata = $entry->getMetadata();

        return [
            'meta' => $metadata->getRef(),
            'imported' => $entry->getImported(),
            'provider' => $metadata->getProvider()
        ];
    }

    public function wavedata(Entry $entry): ?array
    {
        $wavedata = null;
        if (null !== $entry->getWaveData()) {
            $wavedata = $entry->getWaveData()->getData();
        }

        return $wavedata;
    }

    public function updateDownload(Entry $entry, string $filename): bool
    {
        $entry->setDownload($filename);
        $this->_em->flush();

        return true;
    }

    public function fetchMetadata(Entry $entry): ?EntryMetadata
    {
        // For the time being, it will only be youtube that will have metadata
        $providor = new YouTube();

        $ref = $providor->findRef($entry->getTitle());
        if (null === $ref) {
            return null;
        }

        $metadata = $this->meta->findOneBy(['ref' => $ref]);
        if (false == $metadata) {
            $metadata = $providor->search($entry->getTitle());
        }

        if (null === $metadata || false === $metadata) {
            return null;
        }

        if ((array) $metadata->getData() === []) {
            $providor->setMetadata($metadata);
            $providor->search($entry->getTitle());
        }

        $entry->setMetadata($metadata);
        $this->_em->persist($metadata);
        $this->_em->flush();

        return $metadata;
    }

    /**
     * @param  EntryMetadata     $metadata
     * @param  ProviderInterface $provider
     * @param  Uuid              $uuid
     * @param  string            $thumbnail
     * @return Entry
     */
    public function createPartialImport(EntryMetadata $metadata, ProviderInterface $provider, Uuid $uuid, string $thumbnail, ?\DateTimeInterface $imported = null): Entry
    {
        $entry = $this->findOneBy(['uuid' => $uuid->toBinary()]);
        if (null === $entry) {
            $entry = (new Entry())->setUuid($uuid);
        }

        $entry->setTitle($provider->getTitle())
            ->setThumbnail($thumbnail)
            ->setMetadata($metadata)
        ;

        if (null !== $imported) {
            $entry->setImported($imported);
            $this->createWebappEntry($entry);
        }

        if (null === $metadata->getId()) {
            $this->_em->persist($metadata);
        }

        try {
            if (null === $entry->getId()) {
                $this->_em->persist($entry);
            }
            $this->_em->flush();
        } catch (DBALException $e) {
            if (!$this->_em->isOpen()) {
                $this->registry->resetManager();
            }
            try {
                if (null === $entry->getId()) {
                    $this->_em->persist($entry);
                }
                $this->_em->flush();
            } catch (DBALException $e) {
                if (!$this->_em->isOpen()) {
                    $this->registry->resetManager();
                }
            }
        }

        return $entry;
    }

    /**
     * Update webapp with new entry via webhook
     * @param Entry $entry
     */
    private function createWebappEntry(Entry $entry): bool
    {
        $update = [
            'uuid' => $entry->getUuid(),
            'provider' => $entry->getMetadata()->getProvider(),
            'title' => $entry->getTitle(),
            'metadata' => $entry->getMetadata()->getData()
        ];
        $this->request->post("/media-file/update", $update);

        return (bool) true;
    }

    public function createFromCompletedImport(ArrayCollection $data, EntryMetadata $metadata, $wave = null): Entry
    {
        // Do some baisc validation on fields that are required in the database
        foreach (['uuid', 'path', 'title', 'thumbnail', 'size', 'seconds'] as $key) {
            if (!$data->containsKey($key)) {
                throw new Exception("Missing field {$key}");
            }
        }

        $entry = $metadata->getEntry();
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
            ->setWaveData($wave)
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
            $this->_em->flush();
        } catch (DBALException $e) {
            throw new Exception($e->getMessage());
        }

        $this->_em->flush();

        return $entry;
    }

    public function save(Entry $entry, bool $refresh = true): void
    {
        if (null === $entry->getId()) {
            $this->_em->persist($entry);
        }
        $this->_em->flush();

        // Do webhook to webapp here to update that database
        if (true === $refresh) {
            $this->wsClient->refreshMediaList();
        }
    }

    public function delete(Entry $entry): bool
    {
        $this->minio->delete($entry->getPath());

        $this->_em->remove($entry);
        $this->_em->flush();
        $this->wsClient->refreshMediaList();

        return true;
    }
}
