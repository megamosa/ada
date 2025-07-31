<?php
/**
 * MagoArab OrderEnhancer CSV Processor Service
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Service;

use MagoArab\OrderEnhancer\Helper\Data as HelperData;
use MagoArab\OrderEnhancer\Model\Export\OrderExport;
use Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class CsvProcessor
{
    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var OrderExport
     */
    protected $orderExport;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Required columns mapping
     */
    private $requiredColumnMap = [
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

    /**
     * @param HelperData $helperData
     * @param OrderExport $orderExport
     * @param LoggerInterface $logger
     * @param Filesystem $filesystem
     */
    public function __construct(
        HelperData $helperData,
        OrderExport $orderExport,
        LoggerInterface $logger,
        Filesystem $filesystem
    ) {
        $this->helperData = $helperData;
        $this->orderExport = $orderExport;
        $this->logger = $logger;
        $this->filesystem = $filesystem;
    }

    /**
     * Process CSV file and enhance it
     *
     * @param string $filePath
     * @return bool
     */
    public function processCsvFile($filePath)
    {
        try {
            $this->logger->info('CsvProcessor: Starting to process file: ' . $filePath);

            $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            
            if (!$directory->isExist($filePath)) {
                $this->logger->error('CsvProcessor: File does not exist: ' . $filePath);
                return false;
            }

            // Read file content
            $content = $directory->readFile($filePath);
            if (empty($content)) {
                $this->logger->error('CsvProcessor: Empty file content');
                return false;
            }

            // Remove BOM if present
            $content = $this->removeBom($content);

            // Parse CSV content
            $lines = explode("\n", $content);
            if (empty($lines)) {
                $this->logger->error('CsvProcessor: No lines found in CSV');
                return false;
            }

            // Process the CSV data
            $processedContent = $this->processCsvContent($lines);

            // Write enhanced CSV back
            $enhancedContent = $this->generateEnhancedCsv($processedContent);
            $directory->writeFile($filePath, $enhancedContent);

            $this->logger->info('CsvProcessor: Successfully processed and saved enhanced CSV');
            return true;

        } catch (\Exception $e) {
            $this->logger->error('CsvProcessor Error: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Process CSV content and organize data
     *
     * @param array $lines
     * @return array
     */
    protected function processCSVContent($lines)
    {
        if (empty($lines) || empty(trim($lines[0]))) {
            return [];
        }

        // Parse header
        $header = $this->parseCsvLine($lines[0]);
        $this->logger->info('CsvProcessor: Original header: ' . implode(', ', $header));

        // Map columns to required columns
        $columnMapping = $this->mapColumnsToRequired($header);
        if (empty($columnMapping)) {
            $this->logger->warning('CsvProcessor: No required columns found, returning original data with encoding fix');
            return $this->parseAllLines($lines);
        }

        // Parse and consolidate data
        $orders = [];
        $currentOrderData = [];
        $orderIdentifier = null;

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }

            $row = $this->parseCsvLine($line);
            if (count($row) !== count($header)) {
                $this->logger->warning('CsvProcessor: Row ' . $i . ' has different column count, skipping');
                continue;
            }

            // Combine header with row data
            $rowData = array_combine($header, $row);
            
            // Check if this is a new order or continuation of current order
            $currentIdentifier = $this->getOrderIdentifier($rowData);
            
            if (!empty($currentIdentifier) && $currentIdentifier !== $orderIdentifier) {
                // Save previous order if exists
                if (!empty($currentOrderData)) {
                    $orders[] = $this->processOrderData($currentOrderData, $columnMapping);
                }
                
                // Start new order
                $orderIdentifier = $currentIdentifier;
                $currentOrderData = $rowData;
            } else {
                // Merge with current order
                $currentOrderData = $this->mergeOrderRows($currentOrderData, $rowData);
            }
        }

        // Add last order
        if (!empty($currentOrderData)) {
            $orders[] = $this->processOrderData($currentOrderData, $columnMapping);
        }

        $this->logger->info('CsvProcessor: Processed ' . count($orders) . ' orders from ' . (count($lines) - 1) . ' lines');

        return [
            'header' => array_keys($this->requiredColumnMap),
            'orders' => $orders
        ];
    }

    /**
     * Map original columns to required columns
     *
     * @param array $header
     * @return array
     */
    protected function mapColumnsToRequired($header)
    {
        $mapping = [];
        
        foreach ($this->requiredColumnMap as $displayName => $possibleNames) {
            foreach ($possibleNames as $possibleName) {
                $index = array_search($possibleName, $header);
                if ($index !== false) {
                    $mapping[$displayName] = $index;
                    $this->logger->info('CsvProcessor: Mapped ' . $possibleName . ' -> ' . $displayName);
                    break;
                }
            }
        }
        
        return $mapping;
    }

    /**
     * Get order identifier from row data
     *
     * @param array $rowData
     * @return string|null
     */
    protected function getOrderIdentifier($rowData)
    {
        $identifierFields = ['Order Date', 'created_at', 'increment_id', 'entity_id'];
        
        foreach ($identifierFields as $field) {
            if (isset($rowData[$field]) && !empty(trim($rowData[$field]))) {
                return trim($rowData[$field]);
            }
        }
        
        return null;
    }

    /**
     * Merge order rows (consolidate multi-row data)
     *
     * @param array $existing
     * @param array $new
     * @return array
     */
    protected function mergeOrderRows($existing, $new)
    {
        foreach ($new as $key => $value) {
            if (!empty($value) && (empty($existing[$key]) || $this->shouldMergeField($key))) {
                if ($this->shouldMergeField($key)) {
                    $existing[$key] = $this->mergeFieldValues($existing[$key] ?? '', $value);
                } else {
                    $existing[$key] = $value;
                }
            }
        }
        
        return $existing;
    }

    /**
     * Check if field should be merged (for array-like fields)
     *
     * @param string $fieldName
     * @return bool
     */
    protected function shouldMergeField($fieldName)
    {
        $mergeFields = ['Item Details', 'Item Price', 'item_details', 'item_prices'];
        return in_array($fieldName, $mergeFields);
    }

    /**
     * Merge field values for array-like fields
     *
     * @param string $existing
     * @param string $new
     * @return string
     */
    protected function mergeFieldValues($existing, $new)
    {
        if (empty($existing)) {
            return $new;
        }
        
        if (empty($new)) {
            return $existing;
        }
        
        // Split by common separators and merge unique values
        $existingValues = preg_split('/[,|]/', $existing);
        $newValues = preg_split('/[,|]/', $new);
        
        $existingValues = array_map('trim', $existingValues);
        $newValues = array_map('trim', $newValues);
        
        $merged = array_unique(array_filter(array_merge($existingValues, $newValues)));
        
        return implode(', ', $merged);
    }

    /**
     * Process individual order data
     *
     * @param array $orderData
     * @param array $columnMapping
     * @return array
     */
    protected function processOrderData($orderData, $columnMapping)
    {
        $processedOrder = [];
        
        foreach ($this->requiredColumnMap as $displayName => $possibleNames) {
            $value = '';
            
            // Find value from original data
            foreach ($possibleNames as $possibleName) {
                if (isset($orderData[$possibleName]) && !empty($orderData[$possibleName])) {
                    $value = $orderData[$possibleName];
                    break;
                }
            }
            
            // Apply specific processing based on field type
            $value = $this->processFieldValue($displayName, $value, $orderData);
            
            $processedOrder[$displayName] = $value;
        }
        
        return $processedOrder;
    }

    /**
     * Process individual field value
     *
     * @param string $fieldName
     * @param string $value
     * @param array $orderData
     * @return string
     */
    protected function processFieldValue($fieldName, $value, $orderData)
    {
        switch ($fieldName) {
            case 'Customer Name':
                return $this->orderExport->formatCustomerName($orderData);
                
            case 'Phone Number':
                return $this->orderExport->formatPhoneNumber($orderData);
                
            case 'Order Date':
                return $this->orderExport->formatDate($value);
                
            case 'Customer Email':
                return $this->sanitizeEmail($value);
                
            default:
                return $this->sanitizeValue($value);
        }
    }

    /**
     * Sanitize email value
     *
     * @param string $email
     * @return string
     */
    protected function sanitizeEmail($email)
    {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return '';
    }

    /**
     * Sanitize general value
     *
     * @param string $value
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
     * Generate enhanced CSV content
     *
     * @param array $processedData
     * @return string
     */
    protected function generateEnhancedCsv($processedData)
    {
        if (empty($processedData) || empty($processedData['header']) || empty($processedData['orders'])) {
            return '';
        }
        
        $csvLines = [];
        
        // Add header
        $csvLines[] = $this->createCsvLine($processedData['header']);
        
        // Add data rows
        foreach ($processedData['orders'] as $order) {
            $row = [];
            foreach ($processedData['header'] as $column) {
                $row[] = isset($order[$column]) ? $order[$column] : '';
            }
            $csvLines[] = $this->createCsvLine($row);
        }
        
        // Join lines and add UTF-8 BOM
        $csvContent = implode("\n", $csvLines);
        return "\xEF\xBB\xBF" . $csvContent;
    }

    /**
     * Parse all lines without processing (fallback)
     *
     * @param array $lines
     * @return array
     */
    protected function parseAllLines($lines)
    {
        $data = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $data[] = $this->parseCsvLine($line);
            }
        }
        return $data;
    }

    /**
     * Remove BOM from content
     *
     * @param string $content
     * @return string
     */
    protected function removeBom($content)
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $content);
    }

    /**
     * Parse CSV line properly
     *
     * @param string $line
     * @return array
     */
    protected function parseCsvLine($line)
    {
        return str_getcsv($line);
    }

    /**
     * Create CSV line with proper encoding and escaping
     *
     * @param array $row
     * @return string
     */
    protected function createCsvLine($row)
    {
        $csvRow = [];
        foreach ($row as $field) {
            $field = $this->sanitizeValue($field);
            $field = str_replace('"', '""', $field);
            $csvRow[] = '"' . $field . '"';
        }
        return implode(',', $csvRow);
    }
}