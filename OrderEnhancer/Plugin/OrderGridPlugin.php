<?php
/**
 * MagoArab OrderEnhancer Order Grid Plugin
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Plugin;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use MagoArab\OrderEnhancer\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;

class OrderGridPlugin
{
    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param HelperData $helperData
     * @param LoggerInterface $logger
     */
    public function __construct(
        HelperData $helperData,
        LoggerInterface $logger
    ) {
        $this->helperData = $helperData;
        $this->logger = $logger;
    }

    /**
     * Before load to add required columns
     *
     * @param Collection $subject
     * @param bool $printQuery
     * @param bool $logQuery
     * @return array
     */
    public function beforeLoad(Collection $subject, $printQuery = false, $logQuery = false)
    {
        if (!$subject->isLoaded()) {
            $this->addCustomColumns($subject);
        }
        
        return [$printQuery, $logQuery];
    }

    /**
     * Add custom columns to order grid
     *
     * @param Collection $collection
     */
    protected function addCustomColumns(Collection $collection)
    {
        try {
            $select = $collection->getSelect();
            
            // Join with sales_order table for additional order data
            $select->joinLeft(
                ['so' => $collection->getTable('sales_order')],
                'so.entity_id = main_table.entity_id',
                [
                    'customer_note' => 'so.customer_note',
                    'discount_amount' => 'so.discount_amount',
                    'total_qty_ordered' => 'so.total_qty_ordered',
                    'customer_email' => 'so.customer_email',
                    'customer_firstname' => 'so.customer_firstname',
                    'customer_lastname' => 'so.customer_lastname'
                ]
            );

            // Add customer name with FIXED priority: billing > shipping > customer data
            $customerNameExpression = new \Zend_Db_Expr('
                COALESCE(
                    NULLIF(TRIM(CONCAT(
                        COALESCE((SELECT firstname FROM ' . $collection->getTable('sales_order_address') . ' 
                         WHERE parent_id = main_table.entity_id AND address_type = "billing" LIMIT 1), ""), 
                        " ", 
                        COALESCE((SELECT lastname FROM ' . $collection->getTable('sales_order_address') . ' 
                         WHERE parent_id = main_table.entity_id AND address_type = "billing" LIMIT 1), "")
                    )), ""),
                    NULLIF(TRIM(CONCAT(
                        COALESCE((SELECT firstname FROM ' . $collection->getTable('sales_order_address') . ' 
                         WHERE parent_id = main_table.entity_id AND address_type = "shipping" LIMIT 1), ""), 
                        " ", 
                        COALESCE((SELECT lastname FROM ' . $collection->getTable('sales_order_address') . ' 
                         WHERE parent_id = main_table.entity_id AND address_type = "shipping" LIMIT 1), "")
                    )), ""),
                    NULLIF(TRIM(CONCAT(COALESCE(so.customer_firstname, ""), " ", COALESCE(so.customer_lastname, ""))), ""),
                    "Guest Customer"
                )
            ');

            $select->columns(['enhanced_customer_name' => $customerNameExpression]);

            // Add billing phone with fallback to shipping
            $phoneExpression = new \Zend_Db_Expr('
                COALESCE(
                    (SELECT telephone FROM ' . $collection->getTable('sales_order_address') . ' 
                     WHERE parent_id = main_table.entity_id AND address_type = "billing" AND telephone IS NOT NULL AND telephone != "" LIMIT 1),
                    (SELECT telephone FROM ' . $collection->getTable('sales_order_address') . ' 
                     WHERE parent_id = main_table.entity_id AND address_type = "shipping" AND telephone IS NOT NULL AND telephone != "" LIMIT 1),
                    ""
                )
            ');

            $select->columns(['billing_telephone' => $phoneExpression]);

            // Add alternative phone from customer attributes
            $altPhoneExpression = new \Zend_Db_Expr('
                COALESCE(
                    (SELECT eav_val.value FROM ' . $collection->getTable('customer_entity') . ' ce
                     JOIN ' . $collection->getTable('eav_attribute') . ' eav_attr 
                       ON eav_attr.attribute_code = "custom_field_1" AND eav_attr.entity_type_id = 1
                     JOIN ' . $collection->getTable('customer_entity_varchar') . ' eav_val 
                       ON eav_val.entity_id = ce.entity_id AND eav_val.attribute_id = eav_attr.attribute_id
                     WHERE ce.entity_id = so.customer_id LIMIT 1),
                    ""
                )
            ');

            $select->columns(['alternative_phone' => $altPhoneExpression]);

            // Add billing address details with fallbacks
            $regionExpression = new \Zend_Db_Expr('
                COALESCE(
                    (SELECT region FROM ' . $collection->getTable('sales_order_address') . ' 
                     WHERE parent_id = main_table.entity_id AND address_type = "billing" AND region IS NOT NULL AND region != "" LIMIT 1),
                    (SELECT region FROM ' . $collection->getTable('sales_order_address') . ' 
                     WHERE parent_id = main_table.entity_id AND address_type = "shipping" AND region IS NOT NULL AND region != "" LIMIT 1),
                    ""
                )
            ');

            $cityExpression = new \Zend_Db_Expr('
                COALESCE(
                    (SELECT city FROM ' . $collection->getTable('sales_order_address') . ' 
                     WHERE parent_id = main_table.entity_id AND address_type = "billing" AND city IS NOT NULL AND city != "" LIMIT 1),
                    (SELECT city FROM ' . $collection->getTable('sales_order_address') . ' 
                     WHERE parent_id = main_table.entity_id AND address_type = "shipping" AND city IS NOT NULL AND city != "" LIMIT 1),
                    ""
                )
            ');

            $streetExpression = new \Zend_Db_Expr('
                COALESCE(
                    (SELECT street FROM ' . $collection->getTable('sales_order_address') . ' 
                     WHERE parent_id = main_table.entity_id AND address_type = "billing" AND street IS NOT NULL AND street != "" LIMIT 1),
                    (SELECT street FROM ' . $collection->getTable('sales_order_address') . ' 
                     WHERE parent_id = main_table.entity_id AND address_type = "shipping" AND street IS NOT NULL AND street != "" LIMIT 1),
                    ""
                )
            ');

            $select->columns([
                'billing_region' => $regionExpression,
                'billing_city' => $cityExpression,
                'billing_street' => $streetExpression
            ]);

            // Add consolidated item details - Fixed query
            $itemDetailsExpression = new \Zend_Db_Expr('
                (SELECT GROUP_CONCAT(
                    CONCAT(
                        COALESCE(soi.name, ""), 
                        " (SKU: ", COALESCE(soi.sku, ""), 
                        ", Qty: ", COALESCE(soi.qty_ordered, 0), ")"
                    ) SEPARATOR " | "
                ) FROM ' . $collection->getTable('sales_order_item') . ' soi
                 WHERE soi.order_id = main_table.entity_id 
                   AND (soi.parent_item_id IS NULL OR soi.product_type = "simple")
                )
            ');

            $itemPricesExpression = new \Zend_Db_Expr('
                (SELECT GROUP_CONCAT(
                    COALESCE(ROUND(soi.price, 2), 0) SEPARATOR ", "
                ) FROM ' . $collection->getTable('sales_order_item') . ' soi
                 WHERE soi.order_id = main_table.entity_id 
                   AND (soi.parent_item_id IS NULL OR soi.product_type = "simple")
                )
            ');

            $itemSubtotalExpression = new \Zend_Db_Expr('
                (SELECT COALESCE(SUM(soi.row_total), 0) 
                 FROM ' . $collection->getTable('sales_order_item') . ' soi
                 WHERE soi.order_id = main_table.entity_id 
                   AND (soi.parent_item_id IS NULL OR soi.product_type = "simple")
                )
            ');

            $select->columns([
                'item_details' => $itemDetailsExpression,
                'item_prices' => $itemPricesExpression,
                'items_subtotal' => $itemSubtotalExpression
            ]);

            // Debug: Log the final query for troubleshooting
            $this->logger->info('OrderGridPlugin: Final SQL Query: ' . $select->__toString());

            $this->logger->info('OrderGridPlugin: Successfully added custom columns to order grid collection');

        } catch (\Exception $e) {
            $this->logger->error('OrderGridPlugin Error: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}