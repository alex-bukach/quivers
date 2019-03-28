<?php

namespace Drupal\quivers\Plugin\Deriver;

use Drupal\rest\Plugin\Deriver\EntityDeriver;

/**
 * Provides a resource plugin definition for whitelisted entity types.
 */
class QuiversDeriver extends EntityDeriver {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $derivatives = parent::getDerivativeDefinitions($base_plugin_definition);
    $entity_types = $this->entityTypeWhitelist();
    $entity_types = array_flip($entity_types);
    return array_intersect_key($derivatives, $entity_types);
  }

  /**
   * The whitelist of entity types we need resource plugins for.
   */
  protected function entityTypeWhitelist() {
    return [
      'commerce_order',
      'commerce_order_item',
      'commerce_payment',
      'commerce_product',
      'commerce_product_attribute_value',
      'commerce_product_variation',
      'commerce_shipment',
      'commerce_shipping_method',
      'profile',
      'taxonomy_term',
    ];
  }

}
