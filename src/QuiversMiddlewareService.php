<?php

namespace Drupal\quivers;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Quivers Middleware Service.
 */
class QuiversMiddlewareService {

  /**
   * The Quivers Settings configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $quiversConfig;

  /**
   * The client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $quiversClient;

  /**
   * Constructs a new QuiversMiddlewareService object.
   *
   * @param \Drupal\quivers\ClientFactory $client_factory
   *   The client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ClientFactory $client_factory, ConfigFactoryInterface $config_factory) {
    $this->quiversConfig = $config_factory->get('quivers.settings');
    $this->quiversClient = $client_factory->createMiddlewareInstance($this->quiversConfig->get());
  }

  /**
   * Update Quivers Profile.
   *
   * @param array $values
   *   Quivers Settings Form array values.
   */
  public function profileUpdate(array $values) {
    $marketplaces = $values['marketplaces'];
    $marketplaces_request_data = [];

    foreach ($marketplaces as $marketplace) {
      if (!$marketplace['quivers_marketplace_id']) {
        continue;
      }
      $data = [];
      $data['store_id'] = $marketplace['store_id'];
      $data['marketplace_id'] = $marketplace['quivers_marketplace_id'];
      $data['claiming_group_ids'] = explode(",", $marketplace['quivers_claiming_group_ids']);
      array_push($marketplaces_request_data, $data);
    }

    $request_body = [
      "client_type" => "Drupal",
      "base_url" => $values['drupal_api_base_url'],
      "business_refid" => $values['business_refid'],
      "api_key" => $values['quivers_api_key'],
      "marketplaces" => $marketplaces_request_data,
    ];
    // Do not need to return any data back to Form.
    $this->quiversClient->post('profile/create',
      ['json' => $request_body]
    );
  }

}
