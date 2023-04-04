<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Avatar;
use App\Service\UserAvatar;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\HttpKernel\Kernel;

class AvatarController extends AbstractController
{
    #[Route('/avatar/new', name: 'avatar_new', methods: ['POST'])]
    public function newAvatar(UserAvatar $avatar, Request $request)
    {
        $file = $request->files->get('file');
        $mime = $request->request->get('mime');
        if (null === $file) {
            return $this->json(['error']);
        }
        $hash = $avatar->generate($file, $mime);

        return $this->json([
            'complete' => true,
            'hash' => $hash
        ]);
    }

    #[Route('/avatar/r/{uuid}', name: 'avatar_render', methods: ['GET'], requirements: ['uuid' => Requirement::UUID_V4])]
    #[ParamConverter('uuid', class: '\App\Entity\Avatar', options: ['mapping' => ['uuid' => 'uuid']])]
    public function renderAvatar(Avatar $entry, UserAvatar $avatar): Response
    {
        return $avatar->render($entry);
    }
}
