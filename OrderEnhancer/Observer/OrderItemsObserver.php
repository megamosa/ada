<?php
/**
 * MagoArab OrderEnhancer Order Items Observer
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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;

class OrderItemsObserver implements ObserverInterface
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
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderItemRepositoryInterface
     */
    protected $orderItemRepository;

    /**
     * @param HelperData $helperData
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderItemRepositoryInterface $orderItemRepository
     */
    public function __construct(
        HelperData $helperData,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        OrderItemRepositoryInterface $orderItemRepository
    ) {
        $this->helperData = $helperData;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * Add order items data to collection after load
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->helperData->isProductColumnsEnabled()) {
            return;
        }

        try {
            $collection = $observer->getEvent()->getCollection();
            
            if (!$collection || !$collection->isLoaded()) {
                return;
            }

            // Post-process each order to ensure item details are populated
            foreach ($collection as $order) {
                $this->addItemDetailsToOrder($order);
            }

            $this->logger->info('OrderItemsObserver: Post-processed ' . $collection->count() . ' orders for item details');

        } catch (\Exception $e) {
            $this->logger->error('OrderItemsObserver Error: ' . $e->getMessage());
        }
    }

    /**
     * Add item details to individual order
     *
     * @param \Magento\Framework\DataObject $order
     */
    protected function addItemDetailsToOrder($order)
    {
        try {
            $orderId = $order->getData('entity_id');
            
            if (!$orderId) {
                return;
            }

            // If item_details is already populated, skip
            if (!empty($order->getData('item_details'))) {
                return;
            }

            // Load order items directly
            $orderObject = $this->orderRepository->get($orderId);
            $items = $orderObject->getAllVisibleItems(); // This gets only visible items (no configurable parents)

            if (empty($items)) {
                $this->logger->warning('OrderItemsObserver: No visible items found for order ' . $orderId);
                return;
            }

            $itemDetails = [];
            $itemPrices = [];

            foreach ($items as $item) {
                $itemDetails[] = sprintf(
                    '%s (SKU: %s, Qty: %s)',
                    $item->getName() ?: 'Unknown Product',
                    $item->getSku() ?: 'No SKU',
                    number_format($item->getQtyOrdered(), 4)
                );

                $itemPrices[] = number_format($item->getPrice(), 2);
            }

            // Set the data back to the order object
            $order->setData('item_details', implode(' | ', $itemDetails));
            $order->setData('item_prices', implode(', ', $itemPrices));
            $order->setData('items_subtotal', $orderObject->getSubtotal());

            $this->logger->info('OrderItemsObserver: Added item details for order ' . $orderId . ': ' . implode(' | ', $itemDetails));

        } catch (\Exception $e) {
            $this->logger->error('OrderItemsObserver: Error processing order ' . $orderId . ': ' . $e->getMessage());
        }
    }
}