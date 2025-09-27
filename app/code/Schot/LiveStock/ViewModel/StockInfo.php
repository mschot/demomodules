<?php

namespace Schot\LiveStock\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Catalog\Model\Product;
use Psr\Log\LoggerInterface;
use Schot\LiveStock\Api\BulkStockManagementInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class StockInfo implements ArgumentInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var BulkStockManagementInterface
     */
    private $bulkStockManagement;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param LoggerInterface $logger
     * @param BulkStockManagementInterface $bulkStockManagement
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        LoggerInterface $logger,
        BulkStockManagementInterface $bulkStockManagement,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->logger = $logger;
        $this->bulkStockManagement = $bulkStockManagement;
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

        $sku = $product->getSku();
        try {                        
            $stockData = $this->bulkStockManagement->getBulkStock([$sku]);            
        } catch (\Exception $e) {
            $this->logger->debug('LiveStock: Could not get stock quantity for SKU ' . $product->getSku() . ' - ' . $e->getMessage());
            return 0;
        }

        if (empty($stockData[$sku])) {
            return 0;
        }

        return (int)$stockData[$sku]['qty'];
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

        $childProducts = $configurableProduct->getTypeInstance()->getUsedProducts($configurableProduct);            
        foreach ($childProducts as $child) {                
            $productIdToSku[$child->getId()] = $child->getSku();
        }

        try {                    
            $childSkus = array_values($productIdToSku);    
            $bulkStockData = $this->bulkStockManagement->getBulkStock($childSkus);                                    
        } catch (\Exception $e) {
            $this->logger->error('LiveStock: Error getting configurable children stock: ' . $e->getMessage());
            return [];
        }

        return $this->mapStockDataToIds($bulkStockData, $productIdToSku);
    }
    
    private function mapStockDataToIds($stockData, $productIdToSku) 
    {
        $stockByProductId = [];    
        foreach ($productIdToSku as $productId => $sku) {
            if (empty($stockData[$sku])) {
                $stockByProductId[$productId] = [
                    'qty' => 0,
                    'sku' => $sku
                ];                    
                continue;
            } 

            $stockByProductId[$productId] = [
                'qty' => (int)$stockData[$sku]['qty'],
                'sku' => $sku
            ];
        }

        return $stockByProductId;
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