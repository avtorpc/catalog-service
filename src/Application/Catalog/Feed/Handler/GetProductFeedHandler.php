<?php

namespace App\Application\Catalog\Feed\Handler;

use App\Infrastructure\Catalog\Persistence\CatalogVersionRepository;
use App\Infrastructure\Catalog\Persistence\ProductFeedRepository;
use App\Shared\Exception\BadRequestException;
use App\Shared\Exception\NotFoundException;
use App\Shared\Time\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final class GetProductFeedHandler
{
    public function __construct(
        private CatalogVersionRepository $versionRepository,
        private ProductFeedRepository $feedRepository,
        private Environment $twig,
        private ClockInterface $clock,
        #[Autowire('%catalog.feed.vendor_name%')]
        private string $vendorName,
        #[Autowire('%catalog.feed.site_url%')]
        private string $siteUrl,
        #[Autowire('%catalog.section.link_prefix%')]
        private string $sectionLinkPrefix,
        #[Autowire('%catalog.product.link_prefix%')]
        private string $productLinkPrefix,
    ) {
    }

    public function handle(?string $supplierId, ?string $stockId): string
    {
        $this->validateUuid($supplierId, 'supplierId');
        $this->validateUuid($stockId, 'stockId');

        $activeTable = $this->versionRepository->getActiveCategoryTableName();
        if ($activeTable === null) {
            throw new NotFoundException('Active catalog category table not found');
        }

        $currencies = $this->feedRepository->findActiveCurrencies($supplierId, $stockId);

        return $this->twig->render('catalog/product_feed.yml.twig', [
            'date_time_feed' => $this->clock->now()->format('c'),
            'vendor_name' => $this->vendorName,
            'site_url' => rtrim($this->siteUrl, '/'),
            'section_link_prefix' => trim($this->sectionLinkPrefix, '/'),
            'product_link_prefix' => trim($this->productLinkPrefix, '/'),
            'currencies' => $currencies,
            'categories' => $this->feedRepository->findActiveCategories($activeTable),
            'offers' => $this->feedRepository->findActiveProducts($supplierId, $stockId),
        ]);
    }

    private function validateUuid(?string $value, string $fieldName): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $value)) {
            throw new BadRequestException("{$fieldName} must be a valid UUID");
        }
    }
}
