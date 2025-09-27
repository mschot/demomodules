<?php

namespace Schot\LiveStock\Plugin;

use Magento\PageCache\Model\Cache\Type as FullPageCache;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Psr\Log\LoggerInterface;

class FlushLiveStockCachePlugin
{
    /**
     * @var FullPageCache
     */
    private $fullPageCache;

    /**
     * @var ProductResource
     */
    private $productResource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param FullPageCache $fullPageCache
     * @param ProductResource $productResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        FullPageCache $fullPageCache,
        ProductResource $productResource,
        LoggerInterface $logger
    ) {
        $this->fullPageCache = $fullPageCache;
        $this->productResource = $productResource;
        $this->logger = $logger;
    }

    /**
     * Flush LiveStock cache tags after reservations are created
     *
     * @param \Magento\InventoryReservations\Model\AppendReservations $subject
     * @param void $result
     * @param ReservationInterface[] $reservations
     * @return void
     */
    public function afterExecute($subject, $result, array $reservations)
    {
        try {
            $skus = [];
            foreach ($reservations as $reservation) {
                $skus[] = $reservation->getSku();
            }

            if (empty($skus)) {
                return $result;
            }
            
            $productIds = $this->productResource->getProductsIdsBySkus($skus);

            // Generate LiveStock cache tags
            $cacheTags = [];
            foreach ($productIds as $productId) {
                $cacheTags[] = 'livestock_' . $productId;
            }

            if (!empty($cacheTags)) {
                // Clean cache for specific LiveStock tags
                $this->fullPageCache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $cacheTags);
                $this->logger->info('LiveStock cache flushed for tags: ' . implode(', ', $cacheTags));
            }

        } catch (\Exception $e) {
            $this->logger->error('Error flushing LiveStock cache: ' . $e->getMessage());
        }

        return $result;
    }
}