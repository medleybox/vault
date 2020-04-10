<?php
declare(strict_types=1);

namespace App\Service;

class ResetData
{
    /**
     * @var \App\Service\Minio
     */
    private $minio;

    public function __construct(Minio $minio)
    {
        $this->minio = $minio;
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
