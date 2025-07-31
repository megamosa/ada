<?php
/**
 * MagoArab OrderEnhancer Config Source Models
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Customer Name Priority Source Model
 */
class CustomerNamePriority implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'billing_first', 'label' => __('Billing Address First')],
            ['value' => 'shipping_first', 'label' => __('Shipping Address First')],
            ['value' => 'customer_first', 'label' => __('Customer Data First')],
            ['value' => 'billing_only', 'label' => __('Billing Address Only')],
            ['value' => 'shipping_only', 'label' => __('Shipping Address Only')]
        ];
    }
}

/**
 * CSV Delimiter Source Model
 */
class CsvDelimiter implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => ',', 'label' => __('Comma (,)')],
            ['value' => ';', 'label' => __('Semicolon (;)')],
            ['value' => '\t', 'label' => __('Tab')],
            ['value' => '|', 'label' => __('Pipe (|)')]
        ];
    }
}

/**
 * Date Format Source Model
 */
class DateFormat implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'Y-m-d H:i:s', 'label' => __('YYYY-MM-DD HH:MM:SS (2024-12-25 14:30:00)')],
            ['value' => 'd/m/Y H:i', 'label' => __('DD/MM/YYYY HH:MM (25/12/2024 14:30)')],
            ['value' => 'm/d/Y H:i', 'label' => __('MM/DD/YYYY HH:MM (12/25/2024 14:30)')],
            ['value' => 'd-m-Y H:i', 'label' => __('DD-MM-YYYY HH:MM (25-12-2024 14:30)')],
            ['value' => 'M j, Y g:i A', 'label' => __('Mon DD, YYYY H:MM AM/PM (Dec 25, 2024 2:30 PM)')]
        ];
    }
}

/**
 * Export Format Source Model
 */
class ExportFormat implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'csv', 'label' => __('CSV (Comma Separated Values)')],
            ['value' => 'xlsx', 'label' => __('Excel (XLSX)')],
            ['value' => 'xml', 'label' => __('XML')]
        ];
    }
}

/**
 * Item Details Format Source Model
 */
class ItemDetailsFormat implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'name_sku', 'label' => __('Name and SKU')],
            ['value' => 'name_only', 'label' => __('Product Name Only')],
            ['value' => 'sku_only', 'label' => __('SKU Only')],
            ['value' => 'detailed', 'label' => __('Detailed (Name, SKU, Qty, Price)')]
        ];
    }
}