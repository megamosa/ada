<?php
/**
 * MagoArab OrderEnhancer Console Commands
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use MagoArab\OrderEnhancer\Helper\Data as HelperData;
use MagoArab\OrderEnhancer\Model\Export\OrderExport;
use Psr\Log\LoggerInterface;

/**
 * Export Orders Command
 */
class ExportOrdersCommand extends Command
{
    const NAME = 'magoarab:orders:export';
    
    /**
     * @var State
     */
    protected $appState;
    
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
     * @param State $appState
     * @param HelperData $helperData
     * @param OrderExport $orderExport
     * @param LoggerInterface $logger
     */
    public function __construct(
        State $appState,
        HelperData $helperData,
        OrderExport $orderExport,
        LoggerInterface $logger
    ) {
        $this->appState = $appState;
        $this->helperData = $helperData;
        $this->orderExport = $orderExport;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Export orders with enhanced formatting')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Export format (csv, xml)',
                'csv'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output file path',
                'var/export/orders_' . date('Y-m-d_H-i-s') . '.csv'
            )
            ->addOption(
                'consolidate',
                'c',
                InputOption::VALUE_NONE,
                'Consolidate multi-row orders'
            )
            ->addOption(
                'validate',
                'v',
                InputOption::VALUE_NONE,
                'Validate configuration before export'
            );
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
            
            $output->writeln('<info>Starting MagoArab Order Export...</info>');
            
            // Validate configuration if requested
            if ($input->getOption('validate')) {
                $errors = $this->helperData->validateConfiguration();
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $output->writeln('<error>' . $error . '</error>');
                    }
                    return Command::FAILURE;
                }
                $output->writeln('<info>Configuration validation passed</info>');
            }
            
            // Check if export is enabled
            if (!$this->helperData->isExcelExportEnabled()) {
                $output->writeln('<error>Excel export is disabled in configuration</error>');
                return Command::FAILURE;
            }
            
            $format = $input->getOption('format');
            $outputFile = $input->getOption('output');
            $consolidate = $input->getOption('consolidate');
            
            $output->writeln('<info>Export format: ' . $format . '</info>');
            $output->writeln('<info>Output file: ' . $outputFile . '</info>');
            $output->writeln('<info>Consolidate orders: ' . ($consolidate ? 'Yes' : 'No') . '</info>');
            
            // Perform export logic here
            $result = $this->performExport($format, $outputFile, $consolidate, $output);
            
            if ($result) {
                $output->writeln('<info>Export completed successfully!</info>');
                return Command::SUCCESS;
            } else {
                $output->writeln('<error>Export failed</error>');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            $this->logger->error('Export command error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Perform the actual export
     *
     * @param string $format
     * @param string $outputFile
     * @param bool $consolidate
     * @param OutputInterface $output
     * @return bool
     */
    protected function performExport($format, $outputFile, $consolidate, OutputInterface $output)
    {
        // This would integrate with your existing export logic
        $output->writeln('<comment>Export functionality would be implemented here</comment>');
        $output->writeln('<comment>This would call the enhanced export plugins and models</comment>');
        
        return true;
    }
}

/**
 * Validate Configuration Command
 */
class ValidateConfigCommand extends Command
{
    const NAME = 'magoarab:orders:validate-config';
    
    /**
     * @var HelperData
     */
    protected $helperData;
    
    /**
     * @var State
     */
    protected $appState;

    /**
     * @param HelperData $helperData
     * @param State $appState
     */
    public function __construct(
        HelperData $helperData,
        State $appState
    ) {
        $this->helperData = $helperData;
        $this->appState = $appState;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Validate MagoArab Order Enhancer configuration');
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
            
            $output->writeln('<info>Validating MagoArab Order Enhancer Configuration...</info>');
            $output->writeln('');
            
            // Check main settings
            $output->writeln('<comment>Main Settings:</comment>');
            $output->writeln('Excel Export: ' . ($this->helperData->isExcelExportEnabled() ? '<info>Enabled</info>' : '<error>Disabled</error>'));
            $output->writeln('Customer Email: ' . ($this->helperData->isCustomerEmailEnabled() ? '<info>Enabled</info>' : '<error>Disabled</error>'));
            $output->writeln('Order Consolidation: ' . ($this->helperData->isOrderConsolidationEnabled() ? '<info>Enabled</info>' : '<comment>Disabled</comment>'));
            $output->writeln('UTF-8 Encoding: ' . ($this->helperData->isUtf8EncodingEnabled() ? '<info>Enabled</info>' : '<comment>Disabled</comment>'));
            $output->writeln('Governorate Filter: ' . ($this->helperData->isGovernorateFilterEnabled() ? '<info>Enabled</info>' : '<comment>Disabled</comment>'));
            $output->writeln('Product Columns: ' . ($this->helperData->isProductColumnsEnabled() ? '<info>Enabled</info>' : '<comment>Disabled</comment>'));
            
            $output->writeln('');
            
            // Check required columns
            $output->writeln('<comment>Required Columns Configuration:</comment>');
            $requiredColumns = $this->helperData->getRequiredColumns();
            foreach ($requiredColumns as $displayName => $possibleNames) {
                $output->writeln('- ' . $displayName . ': <info>' . implode(', ', $possibleNames) . '</info>');
            }
            
            $output->writeln('');
            
            // Validate configuration
            $errors = $this->helperData->validateConfiguration();
            if (empty($errors)) {
                $output->writeln('<info>✓ Configuration validation passed!</info>');
                return Command::SUCCESS;
            } else {
                $output->writeln('<error>Configuration Errors:</error>');
                foreach ($errors as $error) {
                    $output->writeln('- <error>' . $error . '</error>');
                }
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}