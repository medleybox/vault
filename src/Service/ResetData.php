<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\EntryRepository;
use Doctrine\ORM\EntityManagerInterface;

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

    public function __construct(Minio $minio, EntityManagerInterface $em, EntryRepository $entry)
    {
        $this->minio = $minio;
        $this->em = $em;
        $this->entry = $entry;
    }

    public function removeEntities()
    {
        foreach ($this->entry->findAll() as $entity) {
            dump($entity);
            $this->em->remove($entity);
        }
        $this->em->flush();

        return true;
    }

    private function removeAllFilesInFolder($path): array
    {
        dump("Removing files from {$path}");
        $files = $this->minio->listContents($path, true);
        foreach ($files as $file) {
            $this->minio->delete($file['path']);
        }

        return $files;
    }

    public function removeThubmnails(): bool
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
