<?php

namespace Drupal\quivers\Plugin\jsonrpc\Method;

use Drupal\jsonrpc\Exception\JsonRpcException;
use Drupal\jsonrpc\MethodInterface;
use Drupal\jsonrpc\Object\Error;
use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\jsonrpc\Plugin\JsonRpcMethodBase;
use Drupal\quivers\OrderItemSplitter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_order\OrderRefreshInterface;

/**
 * Cancels an order item.
 *
 * @JsonRpcMethod(
 *   id = "quivers.order_item_cancel",
 *   usage = @Translation("Cancel an order item."),
 *   access = {"administer commerce_order"},
 *   params = {
 *     "order_item" = @JsonRpcParameterDefinition(factory = "\Drupal\quivers\ParameterFactory\OrderItemParameterFactory"),
 *     "quantity" = @JsonRpcParameterDefinition(schema = {"type": "number"}),
 *   }
 * )
 */
class Cancel extends JsonRpcMethodBase {

  use SetOrderStateTrait;

  /**
   * The order item splitter service.
   *
   * @var \Drupal\quivers\OrderItemSplitter;
   */
  protected $orderItemSplitter;

  /**
   * The order item splitter service.
   *
   * @var \Drupal\commerce_order\OrderRefreshInterface
   */
  protected $orderRefresh;

  /**
   * Constructs a \Drupal\quivers\Plugin\jsonrpc\Method\Cancel object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_order\OrderRefreshInterface $order_refresh
   *   The order refresh service.
   */
  public function __construct(array $configuration, string $plugin_id, MethodInterface $plugin_definition, OrderItemSplitter $order_item_splitter, OrderRefreshInterface $order_refresh) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->orderItemSplitter = $order_item_splitter;
    $this->orderRefresh = $order_refresh;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('quivers.order_item_splitter'), $container->get('commerce_order.order_refresh'));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\jsonrpc\Exception\JsonRpcException
   */
  public function execute(ParameterBag $params) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $params->get('order_item');
    $current_state = $order_item->get('quivers_state')->value;
    $order = $order_item->getOrder();
    if (empty($current_state)) {
      $current_state = $order->getState()->value;
    }
    if (in_array($current_state, ['refunded', 'shipped', 'canceled'])) {
      $error = Error::internalError('Cannot cancel a refunded, shipped or canceled order item.');
      throw JsonRpcException::fromError($error);
    }

    $quantity = $params->get('quantity') ?: $order_item->getQuantity();
    $new_order_item = $this->orderItemSplitter->split($order_item, $quantity);
    if (!$new_order_item) {
      $error = Error::internalError('Unable to create split order item.');
      throw JsonRpcException::fromError($error);
    }
    $new_order_item->set('quivers_state', 'canceled');
    $new_order_item->setUnitPrice(new Price(0, $order_item->getUnitPrice()->getCurrencyCode()), TRUE);
    $new_order_item->save();

    $this->orderRefresh->refresh($order);
    $order->save();
    $this->setState($order, 'canceled');

    return $new_order_item->uuid();
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return ['type' => 'string'];
  }

}
