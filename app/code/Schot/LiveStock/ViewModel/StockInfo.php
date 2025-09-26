<?php

namespace Schot\LiveStock\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Catalog\Model\Product;
use Psr\Log\LoggerInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class StockInfo implements ArgumentInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param LoggerInterface $logger
     * @param StockRegistryInterface $stockRegistry
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        LoggerInterface $logger,
        StockRegistryInterface $stockRegistry,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->logger = $logger;
        $this->stockRegistry = $stockRegistry;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if live stock should be displayed for product
     *
     * @param Product $product
     * @return bool
     */
    public function shouldDisplayLiveStock(Product $product): bool
    {
        return (bool)$product->getData('show_live_stock');
    }

    /**
     * Get stock quantity for product
     *
     * @param Product $product
     * @return float
     */
    public function getStockQuantity(Product $product): float
    {
        // For configurable products, we don't check stock at the parent level
        if ($product->getTypeId() === 'configurable') {
            return 0;
        }

        try {
            // Use direct stock registry to get the actual qty value
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            return (float)$stockItem->getQty();
        } catch (\Exception $e) {
            $this->logger->debug('LiveStock: Could not get stock quantity for SKU ' . $product->getSku() . ' - ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get stock data for configurable product children
     *
     * @param Product $configurableProduct
     * @return array
     */
    public function getConfigurableChildrenStock(Product $configurableProduct): array
    {
        if ($configurableProduct->getTypeId() !== 'configurable') {
            return [];
        }

        try {
            // Get all child products
            $childProducts = $configurableProduct->getTypeInstance()->getUsedProducts($configurableProduct);
            $stockByProductId = [];

            foreach ($childProducts as $child) {
                try {
                    // Get direct stock quantity for each child product
                    $stockItem = $this->stockRegistry->getStockItem($child->getId());
                    $qty = (float)$stockItem->getQty();

                    $stockByProductId[$child->getId()] = [
                        'qty' => $qty,
                        'sku' => $child->getSku()
                    ];
                } catch (\Exception $childException) {
                    $this->logger->debug('LiveStock: Could not get stock for child SKU ' . $child->getSku());
                    // Continue to next child product
                }
            }

            return $stockByProductId;
        } catch (\Exception $e) {
            $this->logger->error('LiveStock: Error getting configurable children stock: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get livestock limit from configuration
     *
     * @return int
     */
    public function getLivestockLimit(): int
    {
        return (int)$this->scopeConfig->getValue(
            'schot_livestock/general/livestock_limit',
            ScopeInterface::SCOPE_STORE
        );
    }
}