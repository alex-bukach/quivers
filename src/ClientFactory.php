<?php

namespace Drupal\quivers;

use Drupal\Core\Http\ClientFactory as CoreClientFactory;

/**
 * API Client factory.
 */
class ClientFactory {


  protected $clientFactory;

  /**
   * Constructs a new Quivers ClientFactory object.
   *
   * @param \Drupal\Core\Http\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(CoreClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * Gets an API client instance.
   *
   * @param array $config
   *   The config for the client.
   *
   * @return \GuzzleHttp\Client
   *   The API client.
   */
  public function createInstance(array $config) {
    switch ($config['api_mode']) {
      case 'production':
        $base_uri = 'https://api.quivers.com/v1/';
        break;

      case 'development':
      default:
        $base_uri = 'https://api.quiverstest.com/v1/';
        break;
    }

    $options = [
      'base_uri' => $base_uri,
      'headers' => [
        'Content-Type' => 'application/json',
      ],
    ];

    return $this->clientFactory->fromOptions($options);
  }

  /**
   * Gets an API client instance for Quivers - Middleware.
   *
   * @param array $config
   *   The config for the client.
   *
   * @return \GuzzleHttp\Client
   *   The API client.
   */
  public function createMiddlewareInstance(array $config) {
    switch ($config['api_mode']) {
      case 'production':
        $base_uri = 'https://middleware.quivers.com';
        break;

      case 'development':
      default:
        $base_uri = 'https://middleware.quiverstest.com';
        break;
    }

    $options = [
      'base_uri' => $base_uri,
      'headers' => [
        'Content-Type' => 'application/json',
      ],
    ];

    return $this->clientFactory->fromOptions($options);
  }

}
