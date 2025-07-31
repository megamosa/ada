<?php
/**
 * MagoArab OrderEnhancer Export Model
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Model\Export;

use Magento\Framework\Model\AbstractModel;
use MagoArab\OrderEnhancer\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class OrderExport extends AbstractModel
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
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param HelperData $helperData
     * @param LoggerInterface $logger
     * @param DateTime $dateTime
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        HelperData $helperData,
        LoggerInterface $logger,
        DateTime $dateTime,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->helperData = $helperData;
        $this->logger = $logger;
        $this->dateTime = $dateTime;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Process and consolidate order data
     *
     * @param array $orders
     * @return array
     */
    public function consolidateOrderData(array $orders)
    {
        if (!$this->helperData->isOrderConsolidationEnabled()) {
            return $orders;
        }

        $consolidatedOrders = [];
        $orderGroups = [];

        // Group orders by order identifier
        foreach ($orders as $order) {
            $orderId = $this->getOrderIdentifier($order);
            if (!isset($orderGroups[$orderId])) {
                $orderGroups[$orderId] = [];
            }
            $orderGroups[$orderId][] = $order;
        }

        // Consolidate each group
        foreach ($orderGroups as $orderId => $orderGroup) {
            $consolidatedOrder = $this->mergeOrderGroup($orderGroup);
            $consolidatedOrders[] = $consolidatedOrder;
        }

        $this->helperData->logDebug('Consolidated ' . count($consolidatedOrders) . ' orders from ' . count($orders) . ' rows');

        return $consolidatedOrders;
    }

    /**
     * Get order identifier from order data
     *
     * @param array $order
     * @return string
     */
    protected function getOrderIdentifier(array $order)
    {
        // Try different possible order identifiers
        $identifiers = ['entity_id', 'increment_id', 'order_id', 'Order Date'];
        
        foreach ($identifiers as $identifier) {
            if (isset($order[$identifier]) && !empty($order[$identifier])) {
                return $order[$identifier];
            }
        }

        // Fallback to first non-empty value
        foreach ($order as $key => $value) {
            if (!empty($value)) {
                return $value;
            }
        }

        return uniqid('order_');
    }

    /**
     * Merge multiple order rows into one consolidated order
     *
     * @param array $orderGroup
     * @return array
     */
    protected function mergeOrderGroup(array $orderGroup)
    {
        if (count($orderGroup) === 1) {
            return $orderGroup[0];
        }

        $merged = [];
        $arrayFields = ['Item Details', 'Item Price', 'item_details', 'item_prices'];

        foreach ($orderGroup as $order) {
            foreach ($order as $key => $value) {
                if (empty($merged[$key]) && !empty($value)) {
                    $merged[$key] = $value;
                } elseif (in_array($key, $arrayFields) && !empty($value)) {
                    // Merge array-like fields
                    if (empty($merged[$key])) {
                        $merged[$key] = $value;
                    } else {
                        $merged[$key] = $this->mergeArrayField($merged[$key], $value);
                    }
                }
            }
        }

        return $merged;
    }

    /**
     * Merge array-like fields (comma-separated values)
     *
     * @param string $existing
     * @param string $new
     * @return string
     */
    protected function mergeArrayField($existing, $new)
    {
        $existingArray = array_filter(explode(',', $existing));
        $newArray = array_filter(explode(',', $new));
        
        $merged = array_unique(array_merge($existingArray, $newArray));
        
        return implode(', ', $merged);
    }

    /**
     * Format customer name with priority logic
     *
     * @param array $orderData
     * @return string
     */
    public function formatCustomerName(array $orderData)
    {
        $priority = $this->helperData->getCustomerNamePriority();
        
        foreach ($priority as $source) {
            switch ($source) {
                case 'billing_address':
                    $name = $this->getBillingName($orderData);
                    if (!empty($name)) {
                        return $name;
                    }
                    break;
                    
                case 'shipping_address':
                    $name = $this->getShippingName($orderData);
                    if (!empty($name)) {
                        return $name;
                    }
                    break;
                    
                case 'customer_data':
                    $name = $this->getCustomerName($orderData);
                    if (!empty($name)) {
                        return $name;
                    }
                    break;
                    
                case 'guest_fallback':
                    return __('Guest Customer');
            }
        }
        
        return __('Unknown Customer');
    }

    /**
     * Get billing address name
     *
     * @param array $orderData
     * @return string
     */
    protected function getBillingName(array $orderData)
    {
        $fields = ['billing_customer_name', 'enhanced_customer_name', 'Customer Name'];
        
        foreach ($fields as $field) {
            if (isset($orderData[$field]) && !empty(trim($orderData[$field]))) {
                return trim($orderData[$field]);
            }
        }
        
        return '';
    }

    /**
     * Get shipping address name
     *
     * @param array $orderData
     * @return string
     */
    protected function getShippingName(array $orderData)
    {
        $fields = ['shipping_customer_name', 'shipping_name'];
        
        foreach ($fields as $field) {
            if (isset($orderData[$field]) && !empty(trim($orderData[$field]))) {
                return trim($orderData[$field]);
            }
        }
        
        return '';
    }

    /**
     * Get customer data name
     *
     * @param array $orderData
     * @return string
     */
    protected function getCustomerName(array $orderData)
    {
        $firstName = isset($orderData['customer_firstname']) ? trim($orderData['customer_firstname']) : '';
        $lastName = isset($orderData['customer_lastname']) ? trim($orderData['customer_lastname']) : '';
        
        if (!empty($firstName) || !empty($lastName)) {
            return trim($firstName . ' ' . $lastName);
        }
        
        return '';
    }

    /**
     * Format phone number with fallback logic
     *
     * @param array $orderData
     * @return string
     */
    public function formatPhoneNumber(array $orderData)
    {
        $phoneFields = ['billing_telephone', 'Phone Number', 'customer_phone', 'telephone'];
        
        foreach ($phoneFields as $field) {
            if (isset($orderData[$field]) && !empty(trim($orderData[$field]))) {
                return $this->cleanPhoneNumber($orderData[$field]);
            }
        }
        
        return '';
    }

    /**
     * Clean and format phone number
     *
     * @param string $phone
     * @return string
     */
    protected function cleanPhoneNumber($phone)
    {
        // Remove non-numeric characters except + and spaces
        $phone = preg_replace('/[^\d+\s-]/', '', $phone);
        
        // Format Egyptian phone numbers
        if (preg_match('/^01[0-9]{9}$/', $phone)) {
            return $phone; // Already in correct format
        }
        
        return trim($phone);
    }

    /**
     * Format date according to configuration
     *
     * @param string $date
     * @return string
     */
    public function formatDate($date)
    {
        if (empty($date)) {
            return '';
        }
        
        try {
            $dateFormat = $this->helperData->scopeConfig->getValue('order_enhancer/export_settings/date_format') ?: 'Y-m-d H:i:s';
            $timestamp = strtotime($date);
            
            if ($timestamp === false) {
                return $date; // Return original if parsing fails
            }
            
            return date($dateFormat, $timestamp);
            
        } catch (\Exception $e) {
            $this->logger->error('Date formatting error: ' . $e->getMessage());
            return $date;
        }
    }

    /**
     * Validate and clean export data
     *
     * @param array $data
     * @return array
     */
    public function validateExportData(array $data)
    {
        $cleanData = [];
        
        foreach ($data as $row) {
            $cleanRow = [];
            
            foreach ($row as $key => $value) {
                $cleanRow[$key] = $this->sanitizeValue($value);
            }
            
            $cleanData[] = $cleanRow;
        }
        
        return $cleanData;
    }

    /**
     * Sanitize individual value
     *
     * @param mixed $value
     * @return string
     */
    protected function sanitizeValue($value)
    {
        if ($value === null) {
            return '';
        }
        
        $value = (string)$value;
        
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
        }
        
        // Remove control characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        return trim($value);
    }

    /**
     * Get export statistics
     *
     * @param array $originalData
     * @param array $processedData
     * @return array
     */
    public function getExportStatistics(array $originalData, array $processedData)
    {
        return [
            'original_rows' => count($originalData),
            'processed_rows' => count($processedData),
            'consolidation_ratio' => count($originalData) > 0 ? round((count($originalData) - count($processedData)) / count($originalData) * 100, 2) : 0,
            'export_time' => $this->dateTime->gmtDate(),
            'module_version' => '1.0.0'
        ];
    }
}