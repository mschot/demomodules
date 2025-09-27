<?php
namespace Schot\LiveStock\Model;

use Schot\LiveStock\Api\BulkStockManagementInterface;
use Schot\LiveStock\Model\BulkSalableQtyProvider;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;

class BulkStockManagement implements BulkStockManagementInterface
{
    /**
     * @var BulkSalableQtyProvider
     */
    private $bulkSalableQtyProvider;

    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @param BulkSalableQtyProvider $bulkSalableQtyProvider
     * @param DefaultStockProviderInterface $defaultStockProvider
     */
    public function __construct(
        BulkSalableQtyProvider $bulkSalableQtyProvider,
        DefaultStockProviderInterface $defaultStockProvider
    ) {
        $this->bulkSalableQtyProvider = $bulkSalableQtyProvider;
        $this->defaultStockProvider = $defaultStockProvider;
    }

    /**
     * @inheritdoc
     */
    public function getBulkStock(array $skus, ?int $stockId = null): array
    {
        if ($stockId === null) {
            $stockId = $this->defaultStockProvider->getId();
        }

        return $this->bulkSalableQtyProvider->getBulkSalableQty($skus, $stockId);
    }
}
