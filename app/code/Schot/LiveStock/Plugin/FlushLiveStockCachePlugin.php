<?php

namespace Schot\LiveStock\Plugin;

use Exception;
use Magento\PageCache\Model\Cache\Type as FullPageCache;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
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
     * @var ConfigurableResource
     */
    private $configurableResource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param FullPageCache $fullPageCache
     * @param ProductResource $productResource
     * @param ConfigurableResource $configurableResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        FullPageCache $fullPageCache,
        ProductResource $productResource,
        ConfigurableResource $configurableResource,
        LoggerInterface $logger
    ) {
        $this->fullPageCache = $fullPageCache;
        $this->productResource = $productResource;
        $this->configurableResource = $configurableResource;
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
            $cacheTags = $this->getCacheTagsForReservations($reservations);            

            if (!empty($cacheTags)) {
            
                $this->fullPageCache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $cacheTags);
                $this->logger->info('LiveStock cache flushed for tags: ' . implode(', ', $cacheTags));
            }

        } catch (\Exception $e) {
            $this->logger->error('Error flushing LiveStock cache: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Ensure we fluch configurables
     * 
     * 
     * @param mixed $reservations 
     * @return string[] 
     */
    private function getCacheTagsForReservations($reservations) 
    {
        $skus = array_map(function($reservation) {
            return $reservation->getSku();
        }, $reservations);
        $productIds = $this->productResource->getProductsIdsBySkus($skus);

        $allProductIds = $productIds;
        $parentIds = $this->configurableResource->getParentIdsByChild($productIds);
        $allProductIds = array_merge($productIds, $parentIds);
        $allProductIds = array_unique($allProductIds);

        $cacheTags = [];
        foreach ($allProductIds as $productId) {
            $cacheTags[] = 'livestock_' . $productId;
        }

        return $cacheTags;
    }
}