<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\{Audiowaveform, Import, Minio};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Kernel;

class ApiController extends AbstractController
{
    #[Route('/api/version', name: 'api_version', methods: ['GET'])]
    public function version(Audiowaveform $af): JsonResponse
    {
        return $this->json([
            'php' => PHP_VERSION,
            'symfony' => Kernel::VERSION,
            'audiowaveform' => $af->getVersion()
        ]);
    }

    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function stats(Minio $minio): JsonResponse
    {
        $music = 0;
        foreach ($minio->listContents('youtube/') as $object) {
            $music += $object->fileSize();
        }

        $thumbnails = 0;
        foreach ($minio->listContents(Import::THUMBNAILS_MIMIO) as $object) {
            $thumbnails += $object->fileSize();
        }

        return $this->json([
            'music' => $music,
            'thumbnails' => $thumbnails
        ]);
    }
}
