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
 * Ships an order item.
 *
 * @JsonRpcMethod(
 *   id = "quivers.order_item_ship",
 *   usage = @Translation("Ship an order item."),
 *   access = {"administer commerce_order"},
 *   params = {
 *     "order_item" = @JsonRpcParameterDefinition(factory = "\Drupal\quivers\ParameterFactory\OrderItemParameterFactory"),
 *     "quantity" = @JsonRpcParameterDefinition(schema = {"type": "number"}),
 *     "item_sales_tax" = @JsonRpcParameterDefinition(schema = {"type": "number"}),
 *     "tracking_number" = @JsonRpcParameterDefinition(schema = {"type": "string"}),
 *     "tracking_number_type" = @JsonRpcParameterDefinition(schema = {"type": "string"}),
 *   }
 * )
 */
class Ship extends JsonRpcMethodBase {

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
    $tracking_number = $params->get('tracking_number');
    $tracking_number_type = $params->get('tracking_number_type');
    $allowed_tracking_number_types = [
      'dhl', 'dhl_global', 'fedex', 'ups', 'usps', 'other',
    ];
    if (!empty($tracking_number) && (empty($tracking_number_type) || !in_array($tracking_number_type, $allowed_tracking_number_types))) {
      $error = Error::invalidParams('Please provide a valid tracking number type. It can be one of dhl, dhl_global, fedex, ups, usps and other.');
      throw JsonRpcException::fromError($error);
    }
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $params->get('order_item');
    $quantity = $params->get('quantity') ?: $order_item->getQuantity();
    $new_order_item = $this->orderItemSplitter->split($order_item, $quantity);
    if (!$new_order_item) {
      $error = Error::invalidParams('Unable to create split order item.');
      throw JsonRpcException::fromError($error);
    }
    $current_state = $order_item->get('quivers_state')->value;
    $order = $order_item->getOrder();
    if (empty($current_state)) {
      $current_state = $order->getState()->value;
    }
    if (in_array($current_state, ['refunded', 'shipped', 'canceled'])) {
      $error = Error::internalError('Cannot ship a refunded, shipped or canceled order item.');
      throw JsonRpcException::fromError($error);
    }

    $new_order_item->set('quivers_state', 'shipped');
    if ($tracking_number) {
      $new_order_item->set('quivers_tracking_number', ['value' => $tracking_number, 'type' => $tracking_number_type]);
    }

    $tax = $params->get('item_sales_tax');
    if ($tax) {
      $new_order_item->set('quivers_sales_tax', $tax);
    }

    $new_order_item->save();

    $this->setState($order, 'shipped');

    return $new_order_item->uuid();
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return ['type' => 'string'];
  }

}
