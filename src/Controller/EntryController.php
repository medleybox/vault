<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Entry;
use App\Provider\{ProviderGuesser, YouTube};
use App\Repository\{EntryRepository, EntryMetadataRepository};
use App\Service\{Import, Minio, Thumbnail};
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\Response as PSR7Response;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use giggsey\PSR7StreamResponse\PSR7StreamResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, JsonResponse, Request, Response, ResponseHeaderBag};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class EntryController extends AbstractController
{
    /**
     * @var \App\Service\Minio
     */
    private $minio;

    /**
     * @var \App\Service\Import
     */
    private $import;

    /**
     * @var \App\Repository\EntryRepository
     */
    private $entryRepo;

    /**
     * @var \App\Repository\EntryMetadataRepository
     */
    private $entryMetaRepo;

    /**
     * @var \App\Service\Thumbnail
     */
    private $thumbnail;

    public function __construct(Minio $minio, Import $import, EntryRepository $entryRepo, EntryMetadataRepository $entryMetaRepo, Thumbnail $thumbnail)
    {
        $this->minio = $minio;
        $this->import = $import;
        $this->entryRepo = $entryRepo;
        $this->entryMetaRepo = $entryMetaRepo;
        $this->thumbnail = $thumbnail;
    }

    #[Route('/entry/list-all', name: 'entry_listAll', methods: ['GET'])]
    public function listAll(): Response
    {
        return $this->json($this->entryRepo->listAll());
    }

    #[Route('/entry/stream/{uuid}/{name}', name: 'entry_stream', methods: ['GET'])]
    #[ParamConverter('uuid', class: '\App\Entity\Entry', options: ['mapping' => ['uuid' => 'uuid']])]
    public function streamEntry(Entry $entry, string $name = ''): Response
    {
        $path = $entry->getPath();
        if (null === $path) {
            throw $this->createNotFoundException("Path not set for entry");
        }

        $stream = $this->minio->stream($path);
        if (false === $stream || null === $stream) {
            throw $this->createNotFoundException("File removed from minio {$path}");
        }

        try {
            $detector = new FinfoMimeTypeDetector();
            $mime = $detector->detectMimeTypeFromPath($path);

            $psr7 = Stream::create($stream);

            $response = new PSR7StreamResponse($psr7, $mime);
            $response = $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'stream.mp3');
        } catch (\Exception $e) {
            //
        }

        return $response;
    }

    #[Route('/entry/download/{uuid}', name: 'entry_download', methods: ['GET'])]
    #[ParamConverter('uuid', class: '\App\Entity\Entry', options: ['mapping' => ['uuid' => 'uuid']])]
    public function download(Entry $entry): Response
    {
        $path = $this->minio->read($entry->getPath());
        $filename = 'download.mp3';
        if (null !== $entry->getDownload()) {
            $filename = "{$entry->getDownload()}.mp3";
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Length', (string) $entry->getSize());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

        return $response;
    }

    #[Route('/entry/thumbnail/{uuid}.{ext}', name: 'entry_thumbnail', methods: ['GET'])]
    #[ParamConverter('uuid', class: '\App\Entity\Entry', options: ['mapping' => ['uuid' => 'uuid']])]
    public function thumbnail(Entry $entry): Response
    {
        return $this->thumbnail->render($entry);
    }

    #[Route('/entry/refresh-thumbnail/{uuid}', name: 'entry_refreshThumbnail', methods: ['GET'])]
    #[ParamConverter('uuid', class: '\App\Entity\Entry', options: ['mapping' => ['uuid' => 'uuid']])]
    public function refreshThumbnail(Entry $entry): JsonResponse
    {
        $this->thumbnail->refreshThumbnail($entry);

        return $this->json(['refresh' => true]);
    }

    #[Route('/entry/refresh-source/{uuid}', name: 'entry_refreshSource', methods: ['GET'])]
    #[ParamConverter('uuid', class: '\App\Entity\Entry', options: ['mapping' => ['uuid' => 'uuid']])]
    public function refreshSource(Entry $entry): JsonResponse
    {
        $refresh = $this->import->queueRefreshSource($entry->getUuid());

        return $this->json(['queue' => $refresh]);
    }

    #[Route('/entry/metadata/{uuid}', name: 'entry_metadata', methods: ['GET'])]
    #[ParamConverter('uuid', class: '\App\Entity\Entry', options: ['mapping' => ['uuid' => 'uuid']])]
    public function metadata(Entry $entry): JsonResponse
    {
        $metadata = $this->entryRepo->metadata($entry);

        return $this->json($metadata);
    }

    #[Route('/entry/wavedata/{uuid}', name: 'entry_wavedata', methods: ['GET'])]
    #[ParamConverter('uuid', class: '\App\Entity\Entry', options: ['mapping' => ['uuid' => 'uuid']])]
    public function wavedata(Entry $entry): JsonResponse
    {
        $wavedata = $this->entryRepo->wavedata($entry);

        return $this->json($wavedata);
    }

    #[Route('/entry/delete/{uuid}', name: 'entry_delete', methods: ['DELETE'])]
    #[ParamConverter('uuid', class: '\App\Entity\Entry', options: ['mapping' => ['uuid' => 'uuid']])]
    public function delete(Entry $entry): JsonResponse
    {
        $this->entryRepo->delete($entry);

        return $this->json(['delete' => true]);
    }

    #[Route('/entry/update-download/{uuid}', name: 'entry_updateDownload', methods: ['POST'])]
    #[ParamConverter('uuid', class: '\App\Entity\Entry', options: ['mapping' => ['uuid' => 'uuid']])]
    public function updateDownload(Request $request, Entry $entry): JsonResponse
    {
        $filename = $request->request->get('filename');
        $this->entryRepo->updateDownload($entry, $filename);

        return $this->json(['update' => true]);
    }

    #[Route('/entry/import', name: 'entry_import', methods: ['POST'])]
    public function import(Request $request, ProviderGuesser $guesser): JsonResponse
    {
        $url = $request->request->get('url');
        $uuid = $request->request->get('uuid');
        $provider = null;
        if (null === $uuid) {
            $provider = $guesser->providerFromUrl($url);
            if (null === $provider) {
                return $this->json(['error' => true, 'message' => 'Unable to find provider with no uuid']);
            }

            $uuid = Uuid::v4();
        }

        if ('string' === gettype($uuid)) {
            $uuid = Uuid::fromString($uuid);
        }

        $entry = $this->entryRepo->findViaUuid($uuid);
        if (null === $entry && null === $provider) {
            return $this->json(['error' => true, 'message' => 'Unable to find entry']);
        }

        if (null === $provider) {
            $provider = $entry->getMetadata()->getProviderInstance();
            if (null === $provider) {
                return $this->json(['error' => true, 'message' => 'Unable to find provider']);
            }
            $provider->setUrl($url);
        }

        $check = $this->entryRepo->checkForImported($provider);
        if (null !== $check) {
            return $this->json(['error' => true, 'message' => 'Entry already imported']);
        }

        $uuid = $entry->getUuid();
        $setup = $this->import->setUp($provider, $uuid);
        if (false === $setup) {
            return $this->json(['error' => true, 'message' => 'Unable to setup import']);
        }

        $queue = $this->import->queue();

        return $this->json(['error' => false, 'uuid' => $uuid, 'queue' => $queue]);
    }

    #[Route('/entry/check', name: 'entry_check', methods: ['POST'])]
    public function check(Request $request, ProviderGuesser $guesser): JsonResponse
    {
        $id = $request->request->get('id');
        if (null === $id) {
            return $this->json([
                'found' => false,
                'message' => 'No URL provided'
            ]);
        }

        $provider = $guesser->providerFromUrl($id);
        if (null === $provider) {
            return $this->json(['error' => true, 'message' => 'Unable to find provider']);
        }

        $check = $this->entryRepo->checkForImported($provider);
        if (null !== $check) {
            return $this->json(['found' => false, 'message' => 'Entry already imported']);
        }

        $seach = $this->import->seachForDownload($provider);
        if (true !== $seach) {
            return $this->json([
                'found' => false,
                'message' => 'Unable to find video'
            ]);
        }

        $metadata = $this->entryMetaRepo->findOneBy(['ref' => $provider->getId()]);
        if (null === $metadata) {
            $metadata = $provider->fetchMetadata();
            if (false === $metadata) {
                $reason = $provider->fetchMetadata();
                return $this->json([
                    'found' => false,
                    'message' => "Unable to find video metadata. Reason {$reason}"
                ]);
            }
        }

        $entry = $metadata->getEntry();
        if (null !== $entry && null !== $entry->getThumbnail()) {
            return $this->json([
                'found' => true,
                'uuid' => $entry->getUuid(),
                'title' => $entry->getTitle(),
                'thumbnail' => "/vault/entry/thumbnail/{$entry->getUuid()}.jpg"
            ]);
        }

        $uuid = Uuid::v4();
        $link = $provider->getThumbnailLink();
        $thumbnail = $this->thumbnail->generate($uuid, $link);
        if (null === $thumbnail) {
            return $this->json([
                'found' => false,
                'message' => 'Unable to generate and save thumbnail'
            ]);
        }

        // Save a partial import to keep metadata about this entry
        $this->entryRepo->createPartialImport($metadata, $provider, $uuid, $thumbnail);

        return $this->json([
            'found' => true,
            'uuid' => $uuid,
            'title' => $provider->getTitle(),
            'thumbnail' => "/vault/entry/thumbnail/{$uuid}.jpg"
        ]);
    }
}
