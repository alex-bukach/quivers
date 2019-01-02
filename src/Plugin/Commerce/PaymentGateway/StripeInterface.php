<?php

namespace Drupal\quivers_drupal_plugin\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;

/**
 * Provides the interface for the Stripe payment gateway.
 */
interface StripeInterface extends OnsitePaymentGatewayInterface, SupportsAuthorizationsInterface, SupportsRefundsInterface {

  /**
   * Get the Stripe API Publisable key set for the payment gateway.
   *
   * @return string
   *   The Stripe API publishable key.
   */
  public function getPublishableKey();

}
