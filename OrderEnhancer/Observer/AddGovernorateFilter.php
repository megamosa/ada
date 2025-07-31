<?php
/**
 * MagoArab OrderEnhancer Enhanced Observer
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MagoArab\OrderEnhancer\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;

class AddGovernorateFilter implements ObserverInterface
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
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param HelperData $helperData
     * @param LoggerInterface $logger
     * @param RequestInterface $request
     */
    public function __construct(
        HelperData $helperData,
        LoggerInterface $logger,
        RequestInterface $request
    ) {
        $this->helperData = $helperData;
        $this->logger = $logger;
        $this->request = $request;
    }

    /**
     * Add governorate filter to order collection
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->helperData->isGovernorateFilterEnabled()) {
            return;
        }

        try {
            $collection = $observer->getEvent()->getCollection();
            
            if (!$collection) {
                return;
            }

            // Add governorate filter from request parameters
            $this->addGovernorateFilter($collection);
            
            // Add enhanced search functionality
            $this->addEnhancedSearch($collection);
            
            $this->helperData->logDebug('GovernorateFilter: Successfully applied filters to collection');

        } catch (\Exception $e) {
            $this->logger->error('GovernorateFilter Error: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Add governorate filter based on request parameters
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Grid\Collection $collection
     */
    protected function addGovernorateFilter($collection)
    {
        $governorateFilter = $this->request->getParam('governorate');
        $regionFilter = $this->request->getParam('billing_region');
        
        if ($governorateFilter || $regionFilter) {
            $filterValue = $governorateFilter ?: $regionFilter;
            
            // Join with billing address to filter by region
            $collection->getSelect()->joinLeft(
                ['billing_addr' => $collection->getTable('sales_order_address')],
                'billing_addr.parent_id = main_table.entity_id AND billing_addr.address_type = "billing"',
                []
            )->where(
                'billing_addr.region LIKE ?',
                '%' . $filterValue . '%'
            );
            
            $this->helperData->logDebug('Applied governorate filter: ' . $filterValue);
        }
    }

    /**
     * Add enhanced search functionality
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Grid\Collection $collection
     */
    protected function addEnhancedSearch($collection)
    {
        $searchTerm = $this->request->getParam('search');
        
        if ($searchTerm) {
            $collection->getSelect()->joinLeft(
                ['search_addr' => $collection->getTable('sales_order_address')],
                'search_addr.parent_id = main_table.entity_id',
                []
            )->joinLeft(
                ['search_order' => $collection->getTable('sales_order')],
                'search_order.entity_id = main_table.entity_id',
                []
            )->where(
                'search_addr.firstname LIKE ? OR ' .
                'search_addr.lastname LIKE ? OR ' .
                'search_addr.telephone LIKE ? OR ' .
                'search_addr.city LIKE ? OR ' .
                'search_addr.region LIKE ? OR ' .
                'search_order.customer_email LIKE ? OR ' .
                'search_order.increment_id LIKE ?',
                '%' . $searchTerm . '%'
            );
            
            $this->helperData->logDebug('Applied enhanced search filter: ' . $searchTerm);
        }
    }

    /**
     * Add custom filters based on request parameters
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Grid\Collection $collection
     */
    protected function addCustomFilters($collection)
    {
        // Filter by customer phone
        $phoneFilter = $this->request->getParam('customer_phone');
        if ($phoneFilter) {
            $collection->getSelect()->joinLeft(
                ['phone_addr' => $collection->getTable('sales_order_address')],
                'phone_addr.parent_id = main_table.entity_id',
                []
            )->where('phone_addr.telephone LIKE ?', '%' . $phoneFilter . '%');
        }

        // Filter by city
        $cityFilter = $this->request->getParam('customer_city');
        if ($cityFilter) {
            $collection->getSelect()->joinLeft(
                ['city_addr' => $collection->getTable('sales_order_address')],
                'city_addr.parent_id = main_table.entity_id',
                []
            )->where('city_addr.city LIKE ?', '%' . $cityFilter . '%');
        }

        // Filter by order comments
        $commentsFilter = $this->request->getParam('order_comments');
        if ($commentsFilter) {
            $collection->getSelect()->joinLeft(
                ['comments_order' => $collection->getTable('sales_order')],
                'comments_order.entity_id = main_table.entity_id',
                []
            )->where('comments_order.customer_note LIKE ?', '%' . $commentsFilter . '%');
        }
    }
}