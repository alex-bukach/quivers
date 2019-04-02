<?php

namespace Drupal\quivers\Plugin\jsonrpc\Method;

use Drupal\jsonrpc\Exception\JsonRpcException;
use Drupal\jsonrpc\MethodInterface;
use Drupal\jsonrpc\Object\Error;
use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\jsonrpc\Plugin\JsonRpcMethodBase;
use Drupal\quivers\OrderItemSplitter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Refunds an order item.
 *
 * @JsonRpcMethod(
 *   id = "quivers.order_item_refund",
 *   usage = @Translation("Refund an order item."),
 *   access = {"administer commerce_order"},
 *   params = {
 *     "order_item" = @JsonRpcParameterDefinition(factory = "\Drupal\quivers\ParameterFactory\OrderItemParameterFactory"),
 *     "quantity" = @JsonRpcParameterDefinition(schema = {"type": "number"}),
 *     "amount_refunded" = @JsonRpcParameterDefinition(schema = {"type": "string"}),
 *     "tax_amount_refunded" = @JsonRpcParameterDefinition(schema = {"type": "string"}),
 *   }
 * )
 */
class Refund extends JsonRpcMethodBase {

  use SetOrderStateTrait;

  /**
   * The order item splitter service.
   *
   * @var \Drupal\quivers\OrderItemSplitter;
   */
  protected $orderItemSplitter;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, string $plugin_id, MethodInterface $plugin_definition, OrderItemSplitter $order_item_splitter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->orderItemSplitter = $order_item_splitter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('quivers.order_item_splitter'));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\jsonrpc\Exception\JsonRpcException
   */
  public function execute(ParameterBag $params) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $params->get('order_item');
    $quantity = $params->get('quantity') ?: $order_item->getQuantity();
    $new_order_item = $this->orderItemSplitter->split($order_item, $quantity);
    if (!$new_order_item) {
      $error = Error::internalError('Unable to create split order item.');
      throw JsonRpcException::fromError($error);
    }
    $current_state = $order_item->get('quivers_state')->value;
    $order = $order_item->getOrder();
    if (empty($current_state)) {
      $current_state = $order->getState()->value;
    }
    if (!in_array($current_state, ['shipped', 'refunded'])) {
      $error = Error::internalError('Only shipped and partially refunded items can be refunded.');
      throw JsonRpcException::fromError($error);
    }
    $amount_refunded = $params->get('amount_refunded') ?: $order_item->getTotalPrice()->getNumber();
    if ($amount_refunded > $order_item->getTotalPrice()->getNumber()) {
      $error = Error::internalError('Cannot refund more than the original amount.');
      throw JsonRpcException::fromError($error);
    }
    $new_order_item->set('quivers_state', 'refunded');

    $new_order_item->set('quivers_amount_refunded', $amount_refunded);

    $tax_amount_refunded = $params->get('tax_amount_refunded') ?: '';
    if (!empty($tax_amount_refunded)) {
      $new_order_item->set('quivers_sales_tax_refunded', $tax_amount_refunded);
    }
    $new_order_item->save();

    $this->setState($order, 'refunded');
    return $new_order_item->uuid();
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return ['type' => 'string'];
  }

}
