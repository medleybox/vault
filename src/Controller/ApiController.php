<?php

namespace App\Controller;

use App\Service\{Import, Minio};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Kernel;

class ApiController extends AbstractController
{
    /**
     * @Route("/api/version", name="api_version")
     */
    public function version(): Response
    {
        return $this->json([
            'php' => PHP_VERSION,
            'symfony' => Kernel::VERSION
        ]);
    }

    /**
     * @Route("/api/stats", name="api_stats")
     */
    public function stats(Minio $minio): Response
    {
        $music = 0;
        foreach ($minio->listContents('youtube/') as $object) {
            $music += $object['size'];
        }

        $thumbnails = 0;
        foreach ($minio->listContents(Import::THUMBNAILS_MIMIO) as $object) {
            $thumbnails += $object['size'];
        }

        return $this->json([
            'music' => $music,
            'thumbnails' => $thumbnails
        ]);
    }
}
