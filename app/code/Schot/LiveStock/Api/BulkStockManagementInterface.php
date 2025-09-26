<?php
namespace Schot\LiveStock\Api;

/**
 * Interface for bulk stock operations
 * @api
 */
interface BulkStockManagementInterface
{
    /**
     * Get stock information for multiple SKUs
     *
     * @param string[] $skus
     * @param int|null $stockId
     * @return array
     */
    public function getBulkStock(array $skus, ?int $stockId = null): array;
}