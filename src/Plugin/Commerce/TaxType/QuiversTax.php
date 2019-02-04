<?php

namespace Drupal\quivers\Plugin\Commerce\TaxType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\RemoteTaxTypeBase;

/**
 * Provides the Quiverstax remote tax type.
 *
 * @CommerceTaxType(
 *   id = "quiverstax",
 *   label = "Quivers Tax",
 * )
 */
class QuiversTax extends RemoteTaxTypeBase {

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
    $quivers_services = \Drupal::service('quivers.quivers_services');
    try {
      $order_item_taxes = $quivers_services->calculateValidateTax($order);
    }
    catch (\Exception $e) {
      // Validate API failed to get Taxes.
      // use Countries Tax Rate.
      $order_item_taxes = $quivers_services->calculateCountryTax($order);
    }

    $currency_code = $order->getTotalPrice() ? $order->getTotalPrice()->getCurrencyCode() : $order->getStore()->getDefaultCurrencyCode();

    foreach ($order->getItems() as $item) {
      if (isset($order_item_taxes[$item->uuid()])) {
        $item->addAdjustment(new Adjustment([
          'type' => 'tax',
          'label' => $this->t('Tax'),
          'amount' => new Price((string) $order_item_taxes[$item->uuid()], $currency_code),
          'source_id' => $this->pluginId . '|' . $this->entityId,
        ]));
      }
    }

  }

}
