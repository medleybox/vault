<?php

namespace App\Controller;

use App\Entity\Entry;
use App\Provider\YouTube;
use App\Repository\EntryRepository;
use App\Service\{Import, Minio};

use GuzzleHttp\Psr7\Stream;
use giggsey\PSR7StreamResponse\PSR7StreamResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, ResponseHeaderBag};
use Symfony\Component\Routing\Annotation\Route;

class EntryController extends AbstractController
{
    public function __construct(Minio $minio, Import $import, EntryRepository $entryRepo)
    {
        $this->minio = $minio;
        $this->import = $import;
        $this->entryRepo = $entryRepo;
    }

    /**
     * @Route("/entry/steam/{uuid}/{name}", name="entry_steam", methods={"GET"})
     * @ParamConverter("uuid", class="\App\Entity\Entry", options={"mapping": {"uuid": "uuid"}})
     */
    public function steam(string $name, Entry $entry)
    {
        $path = $entry->getPath();
        $stream = $this->minio->stream($path);
        $metadata = stream_get_meta_data($stream);
        $filename = $metadata["uri"];
        $mime = mime_content_type($filename);

        $response = new PSR7StreamResponse(new Stream($stream), $mime);
        $response = $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'stream.mp3');

        return $response;
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
     * @Route("/entry/delete/{uuid}", name="entry_delete", methods={"DELETE"})
     * @ParamConverter("uuid", class="\App\Entity\Entry", options={"mapping": {"uuid": "uuid"}})
     */
    public function delete(Entry $entry)
    {
        $this->entryRepo->delete($entry);

        return $this->json(['delete' => true]);
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
