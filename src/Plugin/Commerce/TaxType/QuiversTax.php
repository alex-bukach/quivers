<?php

namespace Drupal\quivers\Plugin\Commerce\TaxType;

use Drupal\quivers\QuiverstaxLib;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\RemoteTaxTypeBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the Quiverstax remote tax type.
 *
 * @CommerceTaxType(
 *   id = "quiverstax",
 *   label = "Quiverstax",
 * )
 */
class Quiverstax extends RemoteTaxTypeBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_inclusive' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function apply(OrderInterface $order) {
    // order shipping address not found, Don't Process further
    if (!$order->hasField('shipments') || $order->get('shipments')->isEmpty()) {
      return;
    }

    $quiverstax_lib = \Drupal::service('quivers.quiverstax_lib');
    $order_tax = $quiverstax_lib->getQuiversValidateTax($order);
    if ( $order_tax == 'FAILED' ) {
      $order_tax = $quiverstax_lib->getQuiversCountriesTax($order);
    }
    if ( $order_tax == 'FAILED' ) {
      \Drupal::logger('quivers')->error("Quivers API's Failed: ".$order_tax);
      return;
    }
    if ( is_null($order_tax) ) {
      \Drupal::logger('quivers')->error("Tax from quivers is null: ".$order_tax);
      return;
    }

    $order_initial_adjustments = $order->getAdjustments();
    $currency_code = $order->getTotalPrice() ? $order->getTotalPrice()->getCurrencyCode() : $order->getStore()->getDefaultCurrencyCode();
    array_push($order_initial_adjustments, new Adjustment([
        'type' => 'tax',
        'label' => 'Tax',
        'amount' => new Price((string) $order_tax, $currency_code),
      ]));
    $order->setAdjustments($order_initial_adjustments);
  }

}
