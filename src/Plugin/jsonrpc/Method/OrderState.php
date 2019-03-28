<?php

namespace Drupal\quivers\Plugin\jsonrpc\Method;

use Drupal\jsonrpc\Exception\JsonRpcException;
use Drupal\jsonrpc\Object\Error;
use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\jsonrpc\Plugin\JsonRpcMethodBase;

/**
 * Updates the order state.
 *
 * @JsonRpcMethod(
 *   id = "quivers.order_state_update",
 *   usage = @Translation("Update the order state."),
 *   access = {"administer commerce_order"},
 *   params = {
 *     "order" = @JsonRpcParameterDefinition(factory = "\Drupal\quivers\ParameterFactory\OrderParameterFactory"),
 *     "state" = @JsonRpcParameterDefinition(schema = {"type": "string"}),
 *   }
 * )
 */
class OrderState extends JsonRpcMethodBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\jsonrpc\Exception\JsonRpcException
   */
  public function execute(ParameterBag $params) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $params->get('order');
    $state = $params->get('state');
    $allowed_states = ['shipped', 'processing', 'canceled', 'draft'];
    if (!in_array($state, $allowed_states)) {
      $error = Error::internalError('Invalid order state. Only the shipped, processing and canceled states are allowed.');
      throw JsonRpcException::fromError($error);
    }
    $order->set('state', $state);
    $constraints = $order->validate();

    if ($constraints->count() > 0) {
      $error = Error::internalError('Invalid state update request. The requested state transition might be illegal.');
      throw JsonRpcException::fromError($error);
    }
    $order->save();
    return $order->uuid();
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return ['type' => 'string'];
  }

}
