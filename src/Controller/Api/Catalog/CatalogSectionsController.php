<?php

namespace App\Controller\Api\Catalog;

use App\Application\Catalog\Sections\Handler\GetCatalogSectionHandler;
use App\Application\Catalog\Sections\Handler\GetCatalogSectionsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogSectionsController extends AbstractController
{
    public function __construct(
        private GetCatalogSectionsHandler $sectionsHandler,
        private GetCatalogSectionHandler $sectionHandler,
    ) {
    }

    #[Route('/section', name: 'catalog_sections_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse(
            $this->sectionsHandler->handle($request->query->get('level'))
        );
    }

    #[Route('/section/{slug}', name: 'catalog_section_get', requirements: ['slug' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function get(string $slug): JsonResponse
    {
        return new JsonResponse(
            $this->sectionHandler->handle($slug)
        );
    }
}
