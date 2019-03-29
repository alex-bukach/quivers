<?php

namespace Drupal\quivers\Plugin\jsonrpc\Method;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\jsonrpc\Object\Error;
use Drupal\jsonrpc\Exception\JsonRpcException;

trait SetOrderStateTrait {

  /**
   * Conditionally sets the order state.
   *
   * If all order items are in a state, the order will be transitioned into
   * that state.
   *
   * Otherwise, if no order items are "processing" or "readytofulfill", the
   * order will be closed.
   *
   * @param OrderInterface $order
   *   The order.
   * @param string $state
   *   The order item state.
   */
  public function setState(OrderInterface $order, $state) {
    // Set order state.
    $set_order_state = TRUE;
    $close_order = TRUE;
    foreach ($order->getItems() as $item) {
      $item_state = $item->get('quivers_state')->value;
      if (empty($item_state)) {
        $item_state = $order->getState()->value;
      }
      if ($item_state !== $state) {
        $set_order_state = FALSE;
      }
      if (in_array($item_state, ['processing', 'readytofulfill'])) {
        $close_order = FALSE;
      }
    }
    if ($set_order_state) {
      $order->set('state', $state);
    }
    elseif ($close_order) {
      $order->set('state', 'closed');
    }
    if ($set_order_state || $close_order) {
      $constraints = $order->validate()->getByField('state');
      if ($constraints->count() > 0) {
        $error = Error::internalError('Invalid state update request. The requested state transition might be illegal.');
        throw JsonRpcException::fromError($error);
      }
      $order->save();
    }
  }

}