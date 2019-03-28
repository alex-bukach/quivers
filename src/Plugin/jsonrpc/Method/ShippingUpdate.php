<?php

namespace Drupal\quivers\Plugin\jsonrpc\Method;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\jsonrpc\Plugin\JsonRpcMethodBase;

/**
 * Sets shipping amount and shipping tax on an order.
 *
 * @JsonRpcMethod(
 *   id = "quivers.order_shipping_update",
 *   usage = @Translation("Shipping amount and shipping tax."),
 *   access = {"administer commerce_order"},
 *   params = {
 *     "order" = @JsonRpcParameterDefinition(factory = "\Drupal\quivers\ParameterFactory\OrderParameterFactory"),
 *     "amount" = @JsonRpcParameterDefinition(schema = {"type": "string"}),
 *     "sales_tax_amount" = @JsonRpcParameterDefinition(schema = {"type": "string"}),
 *   }
 * )
 */
class ShippingUpdate extends JsonRpcMethodBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ParameterBag $params) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $params->get('order');
    $adjustments = $order->getAdjustments();
    $shipping_set = FALSE;
    $shipping_tax_set = FALSE;
    foreach ($adjustments as $adjustment) {
      if (in_array($adjustment->getType(), ['shipping', 'quivers_shipping_tax'])) {
        $definition = $adjustment->toArray();
        if ($adjustment->getType() === 'shipping') {
          $param_key = 'amount';
          $shipping_set = TRUE;
        }
        else {
          $param_key = 'sales_tax_amount';
          $shipping_tax_set = TRUE;
        }
        $definition['amount'] = new Price($params->get($param_key), $definition['amount']->getCurrencyCode());
        $definition['locked'] = TRUE;
        $order->removeAdjustment($adjustment);

        $adjustment = new Adjustment($definition);
        $order->addAdjustment($adjustment)->save();
      }
    }
    if (!$shipping_set) {
      $definition = [
        'type' => 'shipping',
        'label' => $this->t('Shipping'),
        'amount' => new Price($params->get('amount'), $order->getTotalPrice()->getCurrencyCode()),
        'included' => FALSE,
        'locked' => TRUE,
      ];
      $adjustment = new Adjustment($definition);
      $order->addAdjustment($adjustment)->save();
    }
    if (!$shipping_tax_set) {
      $definition = [
        'type' => 'quivers_shipping_tax',
        'label' => $this->t('Shipping tax'),
        'amount' => new Price($params->get('sales_tax_amount'), $order->getTotalPrice()->getCurrencyCode()),
        'included' => FALSE,
        'locked' => TRUE,
      ];
      $adjustment = new Adjustment($definition);
      $order->addAdjustment($adjustment)->save();
    }
    return $order->uuid();
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return ['type' => 'string'];
  }

}
