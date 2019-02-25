<?php

namespace Drupal\quivers;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Serialization\Json;

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
  protected $quiversMiddlewareClient;

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
    $this->quiversMiddlewareClient = $client_factory->createMiddlewareInstance($this->quiversConfig->get());
  }

  /**
   * Create Quivers Middleware Profile.
   *
   * @param array $values
   *   Quivers Settings Form array values.
   */
  public function profileCreate(array $values) {
    $request_data = [
      "client_type" => "Drupal",
      "base_url" => $values['drupal_api_base_url'],
      "business_refid" => $values['business_refid'],
      "api_key" => $values['quivers_api_key'],
      "consumer_key" => $values['consumer_key'],
      "consumer_secret" => $values['consumer_secret'],
    ];

    $response = $this->quiversMiddlewareClient->post('profile/create',
      ['json' => $request_data]
    );
    $response_data = Json::decode($response->getBody()->getContents());

    return $response_data['uuid'];
  }

  /**
   * Update Quivers Middleware Profile.
   *
   * @param array $values
   *   Quivers Tax Settings Form array values.
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

    $request_data = [
      "marketplaces" => $marketplaces_request_data,
    ];
    $headers = [
      'uuid' => $this->quiversConfig->get('middleware_profile_id'),
    ];
    $this->quiversMiddlewareClient->post(
      'profile/update/marketplaces', [
        'headers' => $headers,
        'json' => $request_data,
      ]
    );
  }

}
