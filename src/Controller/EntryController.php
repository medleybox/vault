<?php

namespace App\Controller;

use App\Service\{Import, Minio};
use App\Provider\YouTube;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;

class EntryController extends AbstractController
{
    public function __construct(Import $import, Minio $minio)
    {
        $this->import = $import;
        $this->minio = $minio;
    }

    /**
     * @Route("/entry/thumbnail/{uuid}", name="entry_thumbnail", methods={"GET"})
     */
    public function thumbnail(string $uuid)
    {
        if (null === $uuid) {
            exit();
        }

        $path = Import::THUMBNAILS_MIMIO . "/{$uuid}.jpg";
        $thumbnail = $this->minio->get($path);

        $response = new Response();
        $response->headers->set('Content-Type', 'image/jpg');
        $response->setContent($thumbnail);

        return $response;
    }

    /**
     * @Route("/entry/import", name="entry_import", methods={"POST"})
     */
    public function import(Request $request)
    {
        $id = $request->request->get('id');
        if (null === $id) {
            exit();
        }

        $this->import->setUp(new YouTube($id));
        $uuid = $this->import->queue();

        return $this->json(['uuid' => $uuid]);
    }
}
