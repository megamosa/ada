<?php

namespace MagoArab\OrderEnhancer\Plugin;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use MagoArab\OrderEnhancer\Helper\Data as HelperData;

class ExcelExportPlugin
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * Required columns mapping - UPDATED with shipping address fallbacks
     */
    private const REQUIRED_COLUMNS = [
        'Order Date' => ['Order Date', 'created_at', 'Created At'],
        'Customer Name' => ['Customer Name', 'customer_name', 'enhanced_customer_name', 'billing_customer_name', 'shipping_customer_name'],
        'Customer Email' => ['Customer Email', 'customer_email', 'Customer Email Address'],
        'Phone Number' => ['Phone Number', 'billing_telephone', 'Customer Phone', 'shipping_telephone'],
        'Alternative Phone' => ['Alternative Phone', 'alternative_phone'],
        'Order Comments' => ['Order Comments', 'customer_note'],
        'Order Status' => ['Order Status', 'status', 'Status'],
        'Governorate' => ['Region/Governorate/Province', 'Governorate', 'billing_region', 'shipping_region'],
        'City' => ['City', 'billing_city', 'shipping_city'],
        'Street Address' => ['Street Address', 'billing_street', 'shipping_street'],
        'Total Quantity Ordered' => ['Total Quantity Ordered', 'total_qty_ordered'],
        'Item Details' => ['Item Details', 'item_details'],
        'Item Price' => ['Item Price', 'item_prices'],
        'Subtotal' => ['Subtotal', 'subtotal', 'items_subtotal'],
        'Shipping Amount' => ['Shipping Amount', 'shipping_and_handling', 'Shipping and Handling'],
        'Discount Amount' => ['Discount Amount', 'discount_amount'],
        'Grand Total' => ['Grand Total', 'grand_total']
    ];

    /**
     * @param Filesystem $filesystem
     * @param LoggerInterface $logger
     * @param FileFactory $fileFactory
     * @param HelperData $helperData
     */
    public function __construct(
        Filesystem $filesystem,
        LoggerInterface $logger,
        FileFactory $fileFactory,
        HelperData $helperData
    ) {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->fileFactory = $fileFactory;
        $this->helperData = $helperData;
    }

    /**
     * After get CSV file - ConvertToCsv
     */
    public function afterGetCsvFile($subject, $result)
    {
        if (!$this->helperData->isExcelExportEnabled()) {
            return $result;
        }

        $this->logger->info('ExcelExportPlugin: afterGetCsvFile called');
        return $this->processExportResult($result);
    }
    
    /**
     * After get XML file - ConvertToXml
     */
    public function afterGetXmlFile($subject, $result)
    {
        if (!$this->helperData->isExcelExportEnabled()) {
            return $result;
        }

        $this->logger->info('ExcelExportPlugin: afterGetXmlFile called');
        return $this->processExportResult($result);
    }
    
    /**
     * Process export result
     */
    protected function processExportResult($result)
    {
        try {
            $this->logger->info('Processing export result: ' . print_r($result, true));
            
            $filePath = $this->extractFilePath($result);
            
            if ($filePath) {
                $this->logger->info('Found file path: ' . $filePath);
                $this->enhanceOrderExport($filePath);
            } else {
                $this->logger->info('No valid file path found in result');
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error in ExcelExportPlugin: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
        }

        return $result;
    }

    /**
     * Extract file path from various result formats
     *
     * @param mixed $result
     * @return string|null
     */
    protected function extractFilePath($result)
    {
        if (is_array($result)) {
            if (isset($result['value'])) {
                return $result['value'];
            } elseif (isset($result['file'])) {
                return $result['file'];
            }
        } elseif (is_string($result)) {
            return $result;
        }
        
        return null;
    }

    /**
     * Enhance order export with proper UTF-8 encoding and required columns only
     */
    protected function enhanceOrderExport($filePath)
    {
        try {
            $this->logger->info('Starting enhanceOrderExport for file: ' . $filePath);
            
            $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            
            $fullPath = $this->findValidFilePath($directory, $filePath);
            if (!$fullPath) {
                $this->logger->info('File not found: ' . $filePath);
                return;
            }
            
            $this->logger->info('Using file path: ' . $fullPath);

            $content = $directory->readFile($fullPath);
            $this->logger->info('File content length: ' . strlen($content));
            
            if (empty($content)) {
                $this->logger->info('Empty file content');
                return;
            }
            
            // Remove BOM if present
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            
            $lines = explode("\n", $content);
            $this->logger->info('Total lines: ' . count($lines));
            
            if (empty($lines) || empty(trim($lines[0]))) {
                $this->logger->info('Empty CSV file or no header');
                return;
            }

            // Process and organize the CSV data
            $this->processOrderData($lines, $directory, $fullPath);
            
        } catch (\Exception $e) {
            $this->logger->error('Error enhancing order export: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Find valid file path
     *
     * @param \Magento\Framework\Filesystem\Directory\WriteInterface $directory
     * @param string $filePath
     * @return string|null
     */
    protected function findValidFilePath($directory, $filePath)
    {
        $paths = [
            $filePath,
            'export/' . basename($filePath),
            'tmp/' . basename($filePath)
        ];

        foreach ($paths as $path) {
            if ($directory->isExist($path)) {
                return $path;
            }
        }

        return null;
    }
    
    /**
     * Process and organize order data
     */
    protected function processOrderData($lines, $directory, $fullPath)
    {
        try {
            $header = $this->parseCsvLine($lines[0]);
            $this->logger->info('Original header: ' . implode(', ', $header));
            
            // Map headers to required columns
            $columnMapping = $this->mapColumnsToRequired($header);
            
            if (empty($columnMapping)) {
                $this->logger->info('No required columns found, fixing encoding only');
                $this->fixEncodingOnly($lines, $directory, $fullPath);
                return;
            }
            
            // Group data by order (consolidate multi-row orders into single rows)
            $consolidatedData = $this->consolidateOrderData($lines, $header);
            
            // Create new CSV with required columns only
            $this->createEnhancedCsv($consolidatedData, $columnMapping, $directory, $fullPath);
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing order data: ' . $e->getMessage());
        }
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
        
        foreach (self::REQUIRED_COLUMNS as $displayName => $possibleNames) {
            foreach ($header as $index => $columnName) {
                $trimmedName = trim($columnName);
                if (in_array($trimmedName, $possibleNames)) {
                    $mapping[$displayName] = $index;
                    $this->logger->info('Mapped column: ' . $trimmedName . ' -> ' . $displayName);
                    break;
                }
            }
        }
        
        return $mapping;
    }

    /**
     * Consolidate multi-row order data into single rows
     *
     * @param array $lines
     * @param array $header
     * @return array
     */
    protected function consolidateOrderData($lines, $header)
    {
        $orders = [];
        $currentOrder = null;
        
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }
            
            $row = $this->parseCsvLine($line);
            
            // Find order identifier (typically order date or order ID)
            $orderIdentifier = $this->getOrderIdentifier($row, $header);
            
            if (!empty($orderIdentifier)) {
                // New order found
                if ($currentOrder !== null) {
                    $orders[] = $currentOrder;
                }
                $currentOrder = $row;
            } else {
                // Continuation of current order, merge data
                if ($currentOrder !== null) {
                    $currentOrder = $this->mergeOrderData($currentOrder, $row);
                }
            }
        }
        
        // Add the last order
        if ($currentOrder !== null) {
            $orders[] = $currentOrder;
        }
        
        $this->logger->info('Consolidated ' . count($orders) . ' orders from ' . (count($lines) - 1) . ' lines');
        
        return $orders;
    }

    /**
     * Get order identifier from row
     *
     * @param array $row
     * @param array $header
     * @return string
     */
    protected function getOrderIdentifier($row, $header)
    {
        $identifierColumns = ['Order Date', 'created_at', 'increment_id', 'entity_id'];
        
        foreach ($identifierColumns as $col) {
            $index = array_search($col, $header);
            if ($index !== false && !empty($row[$index])) {
                return trim($row[$index]);
            }
        }
        
        return '';
    }

    /**
     * Merge order data from multiple rows
     *
     * @param array $currentOrder
     * @param array $additionalData
     * @return array
     */
    protected function mergeOrderData($currentOrder, $additionalData)
    {
        for ($i = 0; $i < count($additionalData); $i++) {
            if (!empty($additionalData[$i]) && empty($currentOrder[$i])) {
                $currentOrder[$i] = $additionalData[$i];
            }
        }
        
        return $currentOrder;
    }

    /**
     * Create enhanced CSV with proper structure
     *
     * @param array $orders
     * @param array $columnMapping
     * @param \Magento\Framework\Filesystem\Directory\WriteInterface $directory
     * @param string $fullPath
     */
    protected function createEnhancedCsv($orders, $columnMapping, $directory, $fullPath)
    {
        $csvLines = [];
        
        // Create header
        $newHeader = array_keys($columnMapping);
        $csvLines[] = $this->createCsvLine($newHeader);
        
        // Process each order
        foreach ($orders as $order) {
            $newRow = [];
            
            foreach ($columnMapping as $displayName => $originalIndex) {
                $value = isset($order[$originalIndex]) ? $order[$originalIndex] : '';
                $newRow[] = $this->cleanFieldValue($value);
            }
            
            $csvLines[] = $this->createCsvLine($newRow);
        }
        
        // Write enhanced CSV
        $csvContent = implode("\n", $csvLines);
        $csvContent = "\xEF\xBB\xBF" . $csvContent; // Add BOM for UTF-8
        
        $directory->writeFile($fullPath, $csvContent);
        
        $this->logger->info('Successfully created enhanced CSV with ' . count($orders) . ' consolidated orders');
    }

    /**
     * Clean field value
     *
     * @param mixed $value
     * @return string
     */
    protected function cleanFieldValue($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        
        $value = (string)$value;
        
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
        }
        
        return trim($value);
    }

    /**
     * Fix encoding without column filtering
     */
    protected function fixEncodingOnly($lines, $directory, $fullPath)
    {
        try {
            $csvLines = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                
                $row = $this->parseCsvLine($line);
                $csvLines[] = $this->createCsvLine($row);
            }
            
            $csvContent = implode("\n", $csvLines);
            $csvContent = "\xEF\xBB\xBF" . $csvContent;
            
            $directory->writeFile($fullPath, $csvContent);
            
            $this->logger->info('Successfully fixed encoding');
            
        } catch (\Exception $e) {
            $this->logger->error('Error fixing encoding: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse CSV line properly
     */
    private function parseCsvLine($line)
    {
        return str_getcsv($line);
    }
    
    /**
     * Create CSV line with proper encoding
     */
    private function createCsvLine($row)
    {
        $csvRow = [];
        foreach ($row as $field) {
            $field = $this->cleanFieldValue($field);
            $field = str_replace('"', '""', $field);
            $csvRow[] = '"' . $field . '"';
        }
        return implode(',', $csvRow);
    }
}