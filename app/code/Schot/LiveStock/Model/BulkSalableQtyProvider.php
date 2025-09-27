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

        $stock = $this->getStock($skus, $stockId);
        $reservations = $this->getReservations($skus, $stockId);
        $stockData = $this->combineStockAndReservations($stock, $reservations);            
        return $stockData;
        
        return [];
    }

    private function combineStockAndReservations($stock, $reservations)
    {
        $stockData = [];
        foreach ($stock as $row) {
            $sku = $row['sku'];
            $indexedQty = (int) $row['quantity'];
            $reservationQty = $reservations[$sku] ?? 0;
            $salableQty = $indexedQty + $reservationQty;

            $stockData[$sku] = [                    
                'stock_name' => 'Default Stock',
                'qty' => $salableQty, 
                'indexed_qty' => $indexedQty,  
                'reservation_qty' => $reservationQty,  
                'manage_stock' => true,
                'is_salable' => (bool) $row['is_salable']                    
            ];
        }

        return $stockData;
    }

    private function getStock($skus, $stockId)
    {
        $connection = $this->resourceConnection->getConnection();
        $stockTableName = $connection->getTableName('inventory_stock_' . $stockId);
        $stockSelect = $connection->select()
            ->from($stockTableName, ['sku', 'quantity', 'is_salable'])
            ->where('sku IN (?)', $skus);

        $result = $connection->fetchAll($stockSelect);

        $skusFound = array_column($result, 'sku');
        $skusMissing = array_diff($skus, $skusFound);
        foreach ($skusMissing as $sku) {
            $result[] = [
                'sku' => $sku,
                'quantity' => 0,
                'is_salable' => false
            ];
        }

        return $result;    
    }

    private function getReservations($skus, $stockId)
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('inventory_reservation');
        $select = $connection->select()
            ->from($tableName, ['sku', 'reservation_qty' => 'SUM(quantity)'])
            ->where('sku IN (?)', $skus)
            ->where('stock_id = ?', $stockId)
            ->group('sku');

        $reservationResult = $connection->fetchAll($select);

        $reservations = [];
        foreach ($reservationResult as $row) {
            $reservations[$row['sku']] = (int) $row['reservation_qty'];
        }

        return $reservations;
    }
     


}






