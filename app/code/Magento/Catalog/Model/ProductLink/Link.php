<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Catalog\Model\ProductLink;

/**
 * @codeCoverageIgnore
 */
class Link extends \Magento\Framework\Model\AbstractExtensibleModel implements
    \Magento\Catalog\Api\Data\ProductLinkInterface
{
    /**#@+
     * Constants
     */
    const KEY_PRODUCT_SKU = 'product_sku';
    const KEY_LINK_TYPE = 'link_type';
    const KEY_LINKED_PRODUCT_SKU = 'linked_product_sku';
    const KEY_LINKED_PRODUCT_TYPE = 'linked_product_type';
    const KEY_POSITION = 'position';
    /**#@-*/

    /**
     * Retrieves a value from the data array if set, or null otherwise.
     *
     * @param string $key
     * @return mixed|null
     */
    protected function _get($key)
    {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    /**
     * Return Data Object data in array format.
     *
     * @return array
     * @todo refactor with converter for AbstractExtensibleModel
     */
    public function __toArray()
    {
        $data = $this->_data;
        $hasToArray = function ($model) {
            return is_object($model) && method_exists($model, '__toArray') && is_callable([$model, '__toArray']);
        };
        foreach ($data as $key => $value) {
            if ($hasToArray($value)) {
                $data[$key] = $value->__toArray();
            } elseif (is_array($value)) {
                foreach ($value as $nestedKey => $nestedValue) {
                    if ($hasToArray($nestedValue)) {
                        $value[$nestedKey] = $nestedValue->__toArray();
                    }
                }
                $data[$key] = $value;
            }
        }
        return $data;
    }

    /**
     * Get product SKU
     *
     * @identifier
     * @return string
     */
    public function getProductSku()
    {
        return $this->_get(self::KEY_PRODUCT_SKU);
    }

    /**
     * Get link type
     *
     * @identifier
     * @return string
     */
    public function getLinkType()
    {
        return $this->_get(self::KEY_LINK_TYPE);
    }

    /**
     * Get linked product sku
     *
     * @identifier
     * @return string
     */
    public function getLinkedProductSku()
    {
        return $this->_get(self::KEY_LINKED_PRODUCT_SKU);
    }

    /**
     * Get linked product type (simple, virtual, etc)
     *
     * @return string
     */
    public function getLinkedProductType()
    {
        return $this->_get(self::KEY_LINKED_PRODUCT_TYPE);
    }

    /**
     * Get product position
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->_get(self::KEY_POSITION);
    }

    /**
     * Set product SKU
     *
     * @param string $productSku
     * @return $this
     */
    public function setProductSku($productSku)
    {
        return $this->setData(self::KEY_PRODUCT_SKU, $productSku);
    }

    /**
     * Set link type
     *
     * @param string $linkType
     * @return $this
     */
    public function setLinkType($linkType)
    {
        return $this->setData(self::KEY_LINK_TYPE, $linkType);
    }

    /**
     * Set linked product sku
     *
     * @param string $linkedProductSku
     * @return $this
     */
    public function setLinkedProductSku($linkedProductSku)
    {
        return $this->setData(self::KEY_LINKED_PRODUCT_SKU, $linkedProductSku);
    }

    /**
     * Set linked product type (simple, virtual, etc)
     *
     * @param string $linkedProductType
     * @return $this
     */
    public function setLinkedProductType($linkedProductType)
    {
        return $this->setData(self::KEY_LINKED_PRODUCT_TYPE, $linkedProductType);
    }

    /**
     * Set linked item position
     *
     * @param int $position
     * @return $this
     */
    public function setPosition($position)
    {
        return $this->setData(self::KEY_POSITION, $position);
    }
}
