<?php

namespace App\Controller;

use App\Entity\Entry;
use App\Provider\YouTube;
use App\Repository\EntryRepository;
use App\Service\{Import, Minio, Thumbnail};

use GuzzleHttp\Psr7\Stream;
use giggsey\PSR7StreamResponse\PSR7StreamResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, ResponseHeaderBag};
use Symfony\Component\Routing\Annotation\Route;

class EntryController extends AbstractController
{
    public function __construct(Minio $minio, Import $import, EntryRepository $entryRepo, Thumbnail $thumbnail)
    {
        $this->minio = $minio;
        $this->import = $import;
        $this->entryRepo = $entryRepo;
        $this->thumbnail = $thumbnail;
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
     * @Route("/entry/download/{uuid}", name="entry_download", methods={"GET"})
     * @ParamConverter("uuid", class="\App\Entity\Entry", options={"mapping": {"uuid": "uuid"}})
     */
    public function download(Entry $entry)
    {
        $contents = $this->minio->read($entry->getPath());

        return new Response($contents);
    }

    /**
     * @Route("/entry/thumbnail/{uuid}", name="entry_thumbnail", methods={"GET"})
     * @ParamConverter("uuid", class="\App\Entity\Entry", options={"mapping": {"uuid": "uuid"}})
     */
    public function thumbnail(Entry $entry)
    {
        return $this->thumbnail->render($entry);
    }

    /**
     * @Route("/entry/refresh-thumbnail/{uuid}", name="entry_refreshThumbnail", methods={"GET"})
     * @ParamConverter("uuid", class="\App\Entity\Entry", options={"mapping": {"uuid": "uuid"}})
     */
    public function refreshThumbnail(Entry $entry)
    {
        $this->entryRepo->refreshThumbnail($entry);

        return $this->json(['refresh' => true]);
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
