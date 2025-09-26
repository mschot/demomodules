<?php
/**
 * Model/BulkSalableQtyProvider.php
 */
namespace Schot\LiveStock\Model;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class BulkSalableQtyProvider
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Get salable quantities for multiple SKUs in bulk
     *
     * @param array $skus
     * @param int $stockId
     * @return array
     */
    public function getBulkSalableQty(array $skus, int $stockId = 1): array
    {
        if (empty($skus)) {
            return [];
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('inventory_stock_' . $stockId);

            $select = $connection->select()
                ->from($tableName, ['sku', 'quantity', 'is_salable'])
                ->where('sku IN (?)', $skus);

            $result = $connection->fetchAll($select);

            // Transform to match MSI format
            $stockData = [];
            foreach ($result as $row) {
                $stockData[$row['sku']] = [
                    [
                        'stock_name' => 'Default Stock',
                        'qty' => (float) $row['quantity'],
                        'manage_stock' => true,
                        'is_salable' => (bool) $row['is_salable']
                    ]
                ];
            }

            // Add missing SKUs
            foreach ($skus as $sku) {
                if (!isset($stockData[$sku])) {
                    $stockData[$sku] = [
                        [
                            'stock_name' => 'Default Stock',
                            'qty' => 0,
                            'manage_stock' => true,
                            'is_salable' => false
                        ]
                    ];
                }
            }

            return $stockData;

        } catch (\Exception $e) {
            $this->logger->error('Error getting bulk salable quantities: ' . $e->getMessage());
        }
        return [];
    }


}






