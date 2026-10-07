<?php

namespace App\Controller\Api\Catalog;

use App\Application\Catalog\Product\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    public function __construct(
        private ProductService $productService,
    ) {}

    #[Route('/products', name: 'catalog_products_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $stockId = $request->query->get('stock_id');
        $categoryId = $request->query->get('category_id');
        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = min(100, max(1, (int) $request->query->get('limit', '10')));

        $result = $this->productService->findProducts($stockId, $categoryId, $page, $limit);

        return new JsonResponse($result, JsonResponse::HTTP_OK);
    }

    #[Route('/products/{slug}', name: 'catalog_products_get', methods: ['GET'])]
    public function get(string $slug): JsonResponse
    {
        $product = $this->productService->findBySlug($slug);

        if (!$product) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Product not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($product, JsonResponse::HTTP_OK);
    }

    #[Route('/categories/exchange-products/{id}', name: 'catalog_categories_exchange_products', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function updateCategory(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !array_key_exists('category_id', $data)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'category_id is required',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $updated = $this->productService->updateCategory($id, $data['category_id']);

        if (!$updated) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Product not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'success' => true,
            'id' => $id,
            'category_id' => $data['category_id'],
        ], JsonResponse::HTTP_OK);
    }

    #[Route('/products/{id}', name: 'catalog_products_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $updated = $this->productService->updateDimensions(
            id: $id,
            weight: isset($data['weight']) ? (float) $data['weight'] : null,
            width: isset($data['width']) ? (float) $data['width'] : null,
            height: isset($data['height']) ? (float) $data['height'] : null,
            length: isset($data['length']) ? (float) $data['length'] : null,
        );

        if (!$updated) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Product not found',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'success' => true,
            'id' => $id,
            'weight' => $data['weight'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'length' => $data['length'] ?? null,
        ], JsonResponse::HTTP_OK);
    }
}
