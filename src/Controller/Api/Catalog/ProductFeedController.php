<?php

namespace App\Controller\Api\Catalog;

use App\Application\Catalog\Feed\Handler\GetProductFeedHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductFeedController extends AbstractController
{
    public function __construct(
        private GetProductFeedHandler $handler,
    ) {
    }

    #[Route('/feed.yml', name: 'catalog_product_feed_yml', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        return new Response(
            $this->handler->handle(
                $request->query->get('supplierId'),
                $request->query->get('stockId')
            ),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/xml; charset=UTF-8',
            ]
        );
    }
}
