<?php

namespace Drupal\quivers;

use Drupal\Component\Serialization\Json;

/**
 * Quivers Cloudhub Service.
 */
class QuiversCloudhubService {

  /**
   * The Quivers Client Factory.
   *
   * @var \Drupal\quivers\ClientFactory
   */
  protected $clientFactory;

  /**
   * Constructs a new QuiversCloudhubService object.
   */
  public function __construct(ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * Get Quivers Product Groups.
   *
   * @param array $values
   *   Quivers Settings Form array values.
   */
  public function getQuiversProductGroups(array $values) {
    $quivers_marketplaces = [];
    $quivers_claiming_groups = [];

    $quivers_cloudhub_client = $this->clientFactory->createCloudhubInstance($values);
    $response = $quivers_cloudhub_client->get('v1/private/ProductGroups/MyProductGroups?refId=' . $values['business_refid']);
    $product_groups = Json::decode($response->getBody()->getContents());

    foreach ($product_groups as $product_group) {
      if ($product_group['Type'] === 'Marketplace') {
        $quivers_marketplaces = array_merge($quivers_marketplaces, [$product_group['Id'] => $product_group['Name']]);
      }
      if ($product_group['Type'] === 'Claiming') {
        $quivers_claiming_groups = array_merge($quivers_claiming_groups, [$product_group['Id'] => $product_group['Name']]);
      }
    }
    return [
      'quivers_marketplaces' => $quivers_marketplaces,
      'quivers_claiming_groups' => $quivers_claiming_groups,
    ];
  }

}