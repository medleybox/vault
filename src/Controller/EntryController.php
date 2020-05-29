<?php

namespace App\Controller;

use App\Service\Import;
use App\Provider\YouTube;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;

class EntryController extends AbstractController
{
    public function __construct(Import $import)
    {
        $this->import = $import;
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
