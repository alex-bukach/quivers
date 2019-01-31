<?php

namespace Drupal\quivers;

use Drupal\address\AddressInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Exception\ClientException;
use Drupal\commerce_tax\Event\CustomerProfileEvent;
use Drupal\commerce_tax\Event\TaxEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class QuiverstaxLib {

  /**
   * The Quiverstax configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $quivers_config;

  /**
   * The client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $quivers_client;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new Quiverstax object.
   *
   * @param \Drupal\quivers\ClientFactory $client_factory
   *   The client.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ClientFactory $client_factory, ConfigFactoryInterface $config_factory, EventDispatcherInterface $event_dispatcher) {
    $this->quivers_config = $config_factory->get('quivers.settings');
    $this->quivers_client = $client_factory->createInstance($this->quivers_config);
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Returns Order Tax from Quivers Validate API
   *
   * @param OrderInterface $order
   *   OrderInterface object.
   * @return int Order-Total tax which will be added to Order.
   */
  public function getQuiversValidateTax(OrderInterface $order) {
    $request_body = self::prepareValidateRequest($order);
    if ( $request_body === NULL ) {
      return;
    }
    try {
      $quivers_validate_response = $this->quivers_client->post('customerOrders/validate',
        ['json' => $request_body]
      );
    }
    catch (ClientException $e) {
      \Drupal::logger('quivers')->notice($e->getMessage());
      return 'FAILED';
    }
    catch (\Exception $e) {
      \Drupal::logger('quivers')->notice($e->getMessage());
      return 'FAILED';
    }
    if ($quivers_validate_response->getStatusCode() != 200) {
      \Drupal::logger('quivers')->notice("Quivers Validate API did not work, response status code is: " . (string)$quivers_validate_response->getStatusCode());
      return 'FAILED';
    }

    $quivers_validate_response_json = json_decode($quivers_validate_response->getBody()->getContents(), true);

    $total_tax = 0;
    if (!empty($quivers_validate_response_json['result'])) {
      foreach ($quivers_validate_response_json['result']['items'] as $quivers_validate_order_item) {
        if (empty($quivers_validate_order_item['pricing']['taxes'])) {
          // No Taxes are available.
          return;
        }
        $tax = (float)$quivers_validate_order_item['pricing']['taxes'][0]['amount'];
        $total_tax = $total_tax + $tax;
      }
      return $total_tax;
    }
    else {
      return 'FAILED';
    }
  }

  /**
   * Prepares QuiversValidate Request Data.
   *
   * @param OrderInterface $order
   *   OrderInterface object.
   * @return array $request_body for QuiversValidateAPI.
   */
  public function prepareValidateRequest(OrderInterface $order) {
    $currency_code = $order->getTotalPrice() ? $order->getTotalPrice()->getCurrencyCode() : $order->getStore()->getDefaultCurrencyCode();
    $marketplace_id = self::getQuiversMarketplaceID($currency_code);
    if($marketplace_id === NULL) {
      return;
    }

    $request_body = array(
      "marketplaceId" => (string)$marketplace_id,
      "shippingAddress" => [],
      "items" => [],
    );

    foreach ($order->getItems() as $order_item) {
      $profile = $this->resolveCustomerProfile($order_item);
      // If we could not resolve a profile for the order item, do not add it
      // to the API request. There may not be an address available yet, or the
      // item may not be shippable and not attached to a shipment.
      if (!$profile) {
        continue;
      }

      if ( empty($request_body["shippingAddress"]) ) {
        $address = $profile->get('address')->first();
        $order_country_code = $address->getCountryCode();
        $order_region_code = $address->getAdministrativeArea();

        if ( $order_region_code === NULL || $order_region_code === "" || !(strlen($order_region_code) < 3) ) {
          $order_region_code = self::getQuiversCountriesRegionCode($order_country_code, $address);
          if ( $order_region_code === 'FAILED' ) {
            return;
          }
          \Drupal::logger('quivers')->notice("Order Region Code is NULL and new OrderRegionCode is: " . $order_region_code);
        }
        if ( $order_region_code === NULL ) {
          \Drupal::logger('quivers')->error("Order Region Code is NULL for CountryCode: " . $order_country_code);
          return;
        }

        // Add Address to request body
        $request_body["shippingAddress"] = self::formatAddress($address, $order_region_code);
      }
      $order_item_unit_price = (float)$order_item->getAdjustedUnitPrice()->getNumber();
      $line_item = array(
        'product' => array(
          'name' => $order_item->getTitle(),
          'variant' => array(
            "name" => $order_item->getTitle(),
            "refId" => $order_item->uuid()),
        ),
        'quantity' => 1,
        'pricing' => array(
          'unitPrice' => $order_item_unit_price,
        ),
      );
      for ($i=0; $i < $order_item->getQuantity(); $i++) {
        $request_body['items'][] = $line_item;
      }
    }
    return $request_body;

  }

  /**
   * Returns Order Tax from Quivers Countries API
   *
   * @param OrderInterface $order
   *   OrderInterface object.
   * @return int Total tax which will be added to Order.
   */
  public function getQuiversCountriesTax(OrderInterface $order) {
    $quivers_countries_response_json = self::getQuiversCountriesJSON();
    if ( $quivers_countries_response_json == 'FAILED' ) {
      return "FAILED";
    }

    $address = array();
    foreach ($order->getItems() as $order_item) {
      $profile = $this->resolveCustomerProfile($order_item);
      if (!$profile) {
        continue;
      }
      if ( empty($address) ) {
        $address = $profile->get('address')->first();
      }
    }

    $order_country_code = $address->getCountryCode();
    $order_region_code = $address->getAdministrativeArea();

    if ( $order_region_code == NULL ) {
      $order_region_code = $address->getLocality();
    }
    if ( $order_region_code === NULL ) {
      return;
    }

    $quivers_countries_max_tax_rate = NULL;
    $total_tax = NULL;
    foreach ($quivers_countries_response_json['result'] as $key => $value) {
      if ($value['abbreviations']['two'] == $order_country_code) {
        foreach ($value['regions'] as $region_key => $region_value) {
          if ( (strcasecmp($region_value['abbreviation'], $order_region_code) == 0) || (strcasecmp($region_value['name'], $order_region_code) == 0) ) {
            $quivers_countries_max_tax_rate = (float)$region_value['maxTaxRate'];}
        }
        // If Region is not in CountriesAPI, get the '0' key maxTaxRate for region "N/A"
        if ( $quivers_countries_max_tax_rate === NULL && array_key_exists('0', $value['regions']) ) {
          $quivers_countries_max_tax_rate = (float)$value['regions']["0"]["maxTaxRate"];
        }
      }
    }
    if ( $quivers_countries_max_tax_rate === NULL ) {
      \Drupal::logger('quivers')->error("Region Not found in QuiversCountriesAPI: " . (string)$order_region_code." ," . (string)$order_country_code);
      return 'FAILED';
    }
    foreach ($order->getItems() as $order_item) {
      $order_item_tax = (float)$order_item->getAdjustedUnitPrice()->getNumber() * $quivers_countries_max_tax_rate * (int)$order_item->getQuantity();
      $total_tax = $total_tax + $order_item_tax;
    }
    return $total_tax;
  }

  /**
   * Hit QuiversCountries API and returns Response JSON
   *
   * @return json Countries API response
   */
  public function getQuiversCountriesJSON() {
    try {
      $quivers_countries_response = $this->quivers_client->get('countries');
    }
    catch (ClientException $e) {
      \Drupal::logger('quivers')->notice($e->getMessage());
      return 'FAILED';
    }
    catch (\Exception $e) {
      \Drupal::logger('quivers')->notice($e->getMessage());
      return 'FAILED';
    }
    if ($quivers_countries_response->getStatusCode() != 200) {
      \Drupal::logger('quivers')->notice("QuiversCountriesAPI did not work, response status code is: " . (string)$quivers_countries_response->getStatusCode());
      return 'FAILED';
    }

    return json_decode($quivers_countries_response->getBody()->getContents(), true);
  }

  /**
   * For Countries other than U.S, using locality as region name
   * and get Region code from QuiversCountries API.
   *
   * @param $order_country_code
   *   OrderInterface object.
   * @param $address
   *   Order Address
   * @return string Two letter Region Code.
   */
  public function getQuiversCountriesRegionCode($order_country_code, $address)
  {
    $order_region_name = $address->getLocality();
    $order_region_code = NULL;

    if ($order_region_name === NULL) {
      return $order_region_code;
    }
    $quivers_countries_response_json = self::getQuiversCountriesJSON();
    if ($quivers_countries_response_json == 'FAILED') {
      return $quivers_countries_response_json;
    }

    foreach ($quivers_countries_response_json['result'] as $key => $value) {
      if ($value['abbreviations']['two'] == $order_country_code) {
        foreach ($value['regions'] as $region_key => $region_value) {
          if (strcasecmp($region_value['name'], $order_region_name) == 0) {
            $order_region_code = $region_value['abbreviation'];
          }
        }
        // If Region is not in CountriesAPI, get the '0' key maxTaxRate for region "N/A"
        if ($order_region_code === NULL && array_key_exists('0', $value['regions'])) {
          return $value['regions']["0"]["abbreviation"];
        }
      }
    }
    return $order_region_code;
  }

  /**
   * Get Quivers MarketplaceID from 'Quivers Integration Settings',
   * for respective order price type.
   *
   * @param $currency_code
   *   Order Price Type.
   * @return string Quivers MarketplaceID.
   */
  public function getQuiversMarketplaceID($currency_code) {
    // get quivers marketplace id for particular price type
    $price_types = array('aud','cad','eur','gbp','jpy','usd');
    if ( in_array(strtolower($currency_code), $price_types) ) {
      $marketplace_id = $this->quivers_config->get(strtolower($currency_code));
      if ( $marketplace_id === NULL || $marketplace_id === "" ) {
        \Drupal::logger('quivers')->error("QuiversMarketplaceID is Empty for Currency: " . $currency_code);
        return;
      }
      return $marketplace_id;
    }
    else {
      \Drupal::logger('quivers')->error("QuiversMarketplaceID NOT found in Quivers Settings for Currency: " . $currency_code);
      return;
    }
  }

  /**
   * Resolves the customer profile for the given order item.
   * Stolen from TaxTypeBase::resolveCustomerProfile().
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The customer profile, or NULL if not yet known.
   */
  public function resolveCustomerProfile(OrderItemInterface $order_item) {
    $order = $order_item->getOrder();
    $customer_profile = $order->getBillingProfile();
    // A shipping profile is preferred, when available.
    $event = new CustomerProfileEvent($customer_profile, $order_item);
    $this->eventDispatcher->dispatch(TaxEvents::CUSTOMER_PROFILE, $event);
    $customer_profile = $event->getCustomerProfile();

    return $customer_profile;
  }

  /**
   * Format an address for use in the order request.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address to format.
   *
   * @param $order_region_code
   *   Order Address Region Code
   * @return array
   *   Return a formatted address for use in the order request.
   */
  public function formatAddress(AddressInterface $address, $order_region_code) {
    return [
      'line1' => ($address->getAddressLine1() === NULL) ? "" : $address->getAddressLine1(),
      'line2' => ($address->getAddressLine2() === NULL) ? "" : $address->getAddressLine2(),
      'city' => ($address->getLocality() === NULL) ? "" : $address->getLocality(),
      'postCode' => ($address->getPostalCode() === NULL) ? "" : $address->getPostalCode(),
      'region' => $order_region_code,
      'country' => $address->getCountryCode(),
    ];
  }

}
