<?php

namespace Drupal\quivers;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderRefreshInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;

class OrderItemSplitter {

  /**
   * The order item splitter service.
   *
   * @var \Drupal\commerce_order\OrderRefreshInterface
   */
  protected $orderRefresh;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new OrderItemSplitter object.
   *
   * @param \Drupal\commerce_order\OrderRefreshInterface $order_refresh
   *   The order refresh service.
   * @param \Drupal\commerce_order\Entity\OrderInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(OrderRefreshInterface $order_refresh, EntityTypeManagerInterface $entity_type_manager) {
    $this->orderRefresh = $order_refresh;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Split an order item to two order items.
   *
   * @param OrderItemInterface $order_item
   *   The order item to split.
   * @param string $quantity
   *  The new order item quantity.
   *
   * @return boolean|\Drupal\commerce_order\Entity\OrderItemInterface
   *   FALSE in case of failure. If no split is required, the old order item.
   *   Otherwise the new order item.
   */
  public function split(OrderItemInterface $order_item, $quantity) {
    $order_item_quantity = $order_item->getQuantity();
    if ($quantity > $order_item_quantity) {
      return FALSE;
    }
    if ($quantity == $order_item_quantity) {
      return $order_item;
    }

    $new_order_item = $order_item->createDuplicate();
    $new_order_item->setQuantity($quantity)->save();
    $order_item->setQuantity($order_item_quantity - $quantity);
    $order_item->save();
    $order = $order_item->getOrder();
    $order->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
    $order->addItem($new_order_item);
    $order->save();
    $new_order_item = $this->entityTypeManager->getStorage('commerce_order_item')->load($new_order_item->id());
    return $new_order_item;
  }

}
