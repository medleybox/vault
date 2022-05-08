<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\{EntryRepository, EntryMetadataRepository};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ResetData
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

    /**
     * @var \App\Repository\EntryMetadataRepository
     */
    private $meta;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    public function __construct(Minio $minio, EntityManagerInterface $em, EntryRepository $entry, EntryMetadataRepository $meta, LoggerInterface $log)
    {
        $this->minio = $minio;
        $this->em = $em;
        $this->entry = $entry;
        $this->meta = $meta;
        $this->log = $log;
    }

    public function removeEntities(): bool
    {
        foreach ($this->entry->findAll() as $entity) {
            $this->log->debug("Removed entry: '{$entity->getTitle()}'");
            $this->em->remove($entity);
        }

        foreach ($this->meta->findAll() as $meta) {
            $this->log->debug("Removed metadata: '{$meta->getRef()}'");
            $this->em->remove($meta);
        }

        $this->em->flush();

        return true;
    }

    private function removeAllFilesInFolder($path): array
    {
        $this->log->debug("Removing files from {$path}");
        $files = $this->minio->listContents($path, true);
        foreach ($files as $file) {
            $this->minio->delete($file['path']);
            $this->log->debug("Deleted: {$file['path']}");
        }

        $this->log->debug("Done!");

        return $files;
    }

    public function removeThumbnails(): bool
    {
        $this->removeAllFilesInFolder(Import::THUMBNAILS_MIMIO);

        return true;
    }

    public function removeProviderData(): bool
    {
        $providers = ['youtube'];
        foreach ($providers as $provider) {
            $this->removeAllFilesInFolder($provider);
        }

        return true;
    }
}
