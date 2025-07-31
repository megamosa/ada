<?php
/**
 * MagoArab OrderEnhancer Helper
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    /**
     * Configuration paths
     */
    const XML_PATH_ENABLE_EXCEL_EXPORT = 'order_enhancer/general/enable_excel_export';
    const XML_PATH_ENABLE_GOVERNORATE_FILTER = 'order_enhancer/general/enable_governorate_filter';
    const XML_PATH_ENABLE_PRODUCT_COLUMNS = 'order_enhancer/general/enable_product_columns';
    const XML_PATH_ENABLE_CUSTOMER_EMAIL = 'order_enhancer/general/enable_customer_email';
    const XML_PATH_CONSOLIDATE_ORDERS = 'order_enhancer/general/consolidate_orders';
    const XML_PATH_UTF8_ENCODING = 'order_enhancer/general/utf8_encoding';

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * Check if Excel export enhancement is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isExcelExportEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_EXCEL_EXPORT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if governorate filter is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isGovernorateFilterEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_GOVERNORATE_FILTER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if product columns are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isProductColumnsEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_PRODUCT_COLUMNS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if customer email column is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCustomerEmailEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_CUSTOMER_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if order consolidation is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isOrderConsolidationEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CONSOLIDATE_ORDERS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if UTF-8 encoding is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUtf8EncodingEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_UTF8_ENCODING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get required columns configuration
     *
     * @return array
     */
    public function getRequiredColumns()
    {
        return [
            'Order Date' => ['Order Date', 'created_at', 'Created At'],
            'Customer Name' => ['Customer Name', 'customer_name', 'enhanced_customer_name', 'billing_customer_name'],
            'Customer Email' => ['Customer Email', 'customer_email', 'Customer Email Address'],
            'Phone Number' => ['Phone Number', 'billing_telephone', 'Customer Phone'],
            'Alternative Phone' => ['Alternative Phone', 'alternative_phone'],
            'Order Comments' => ['Order Comments', 'customer_note'],
            'Order Status' => ['Order Status', 'status', 'Status'],
            'Governorate' => ['Region/Governorate/Province', 'Governorate', 'billing_region'],
            'City' => ['City', 'billing_city'],
            'Street Address' => ['Street Address', 'billing_street'],
            'Total Quantity Ordered' => ['Total Quantity Ordered', 'total_qty_ordered'],
            'Item Details' => ['Item Details', 'item_details'],
            'Item Price' => ['Item Price', 'item_prices'],
            'Subtotal' => ['Subtotal', 'subtotal', 'items_subtotal'],
            'Shipping Amount' => ['Shipping Amount', 'shipping_and_handling', 'Shipping and Handling'],
            'Discount Amount' => ['Discount Amount', 'discount_amount'],
            'Grand Total' => ['Grand Total', 'grand_total']
        ];
    }

    /**
     * Get export file configuration
     *
     * @return array
     */
    public function getExportConfig()
    {
        return [
            'encoding' => 'UTF-8',
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '"',
            'add_bom' => true
        ];
    }

    /**
     * Log debug information if enabled
     *
     * @param string $message
     * @param array $context
     */
    public function logDebug($message, array $context = [])
    {
        if ($this->scopeConfig->isSetFlag('order_enhancer/debug/enable_logging')) {
            $this->_logger->debug('MagoArab OrderEnhancer: ' . $message, $context);
        }
    }

    /**
     * Get customer name priority settings
     *
     * @return array
     */
    public function getCustomerNamePriority()
    {
        return [
            'billing_address',
            'shipping_address',
            'customer_data',
            'guest_fallback'
        ];
    }

    /**
     * Validate required configuration
     *
     * @return array
     */
    public function validateConfiguration()
    {
        $errors = [];
        
        if (!$this->isExcelExportEnabled()) {
            $errors[] = __('Excel export enhancement is disabled');
        }
        
        return $errors;
    }
}