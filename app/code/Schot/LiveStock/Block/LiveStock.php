<?php

namespace Schot\LiveStock\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;

class LiveStock extends Template implements IdentityInterface
{
    /**
     * @var Product
     */
    protected $_product = null;
    
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }
    
    /**
     * Set product
     *
     * @param Product $product
     * @return $this
     */
    public function setProduct(Product $product)
    {
        $this->_product = $product;
        return $this;
    }

    /**
     * Get current product
     *
     * @return Product|null
     */
    public function getProduct()
    {
        if (!$this->_product) {
            // Try to get product from registry first
            $this->_product = $this->registry->registry('current_product');
            
            if (!$this->_product) {
                // Try to get product from parent block
                $parentBlock = $this->getParentBlock();
                if ($parentBlock && method_exists($parentBlock, 'getProduct')) {
                    $this->_product = $parentBlock->getProduct();
                }
            }
        }
        return $this->_product;
    }

    /**
     * Return cache identifiers for produced content
     *
     * @return array
     */
    public function getIdentities()
    {
        $product = $this->getProduct();
        return $product ? ["livestock_" . $product->getId()] : []; 
    }

    /**
     * @return float
     */
    public function getStockQtyLeft()
    {
        return 0;
    }
}