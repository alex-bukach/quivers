<?php

namespace Drupal\quivers\Plugin\Commerce\TaxType;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\RemoteTaxTypeBase;
use Drupal\quivers\QuiversService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * The Quivers Service.
   *
   * @var \Drupal\quivers\QuiversService
   */
  protected $quiversService;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_inclusive' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * Constructs a new QuiversTax object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\quivers\QuiversService $quivers_service
   *   The Quivers Service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, QuiversService $quivers_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $event_dispatcher);
    $this->quiversService = $quivers_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('quivers.quivers_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(OrderInterface $order) {
    try {
      $order_item_taxes = $this->quiversService->calculateValidateTax($order);
    }
    catch (\Exception $e) {
      // Validate API failed to get Taxes.
      // use Countries Tax Rate.
      $order_item_taxes = $this->quiversService->calculateCountryTax($order);
    }

    $currency_code = $order->getTotalPrice() ? $order->getTotalPrice()->getCurrencyCode() : $order->getStore()->getDefaultCurrencyCode();
    $tax = 0;
    if (!empty($order_item_taxes['taxes']['additional'])) {
      $label = 'Tax';
      $tax = $order_item_taxes['taxes']['additional'];
      $include =false;
   } else {
      $label = 'Included tax';
      $tax = $order_item_taxes['taxes']['included'];
      $include =true;
   }

  foreach ($order->getItems() as $item) {
    if (isset($tax)) {
      $item->addAdjustment(new Adjustment([
        'type' => 'tax',
        'label' => $this->t($label),
        'amount' => new Price((string) $tax, $currency_code),
        'included' => $include,
        'source_id' => $this->pluginId . '|' . $this->entityId,
      ]));
    }
  }

  }

}
