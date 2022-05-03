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
    $quivers_marketplaces = array();
    $quivers_claiming_groups =array();

    $quivers_cloudhub_client = $this->clientFactory->createCloudhubInstance($values);
    $claim_response = $quivers_cloudhub_client->get('v1/private/ClaimingPolicies/GetByBusiness?refId=' . $values['business_refid']);
    $response = $quivers_cloudhub_client->get('v1/private/ProductGroups/MyProductGroups?refId=' . $values['business_refid']);
    $product_groups = Json::decode($response->getBody()->getContents());
    $claim_groups = Json::decode($claim_response->getBody()->getContents());

    foreach ($claim_groups as $claim_group) {
      if($claim_group['Inclusive']){
      $quivers_claiming_groups = array_merge($quivers_claiming_groups, [' '.$claim_group['Id']=> $claim_group['Name']]);
      }
    }
    
    foreach ($product_groups as $product_group) {
      if ($product_group['Type'] === 'Marketplace') {
        $quivers_marketplaces = array_merge($quivers_marketplaces, [$product_group['Id'] => $product_group['Name']]);
      }
    }

    return [
      'quivers_marketplaces' => $quivers_marketplaces,
      'quivers_claiming_groups' => $quivers_claiming_groups,
    ];
  }

}
