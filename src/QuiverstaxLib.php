<?php

namespace Drupal\quivers;

use Drupal\commerce_order\AdjustmentTypeManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Drupal\quivers\ClientFactory;

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
   * Constructs a new Quiverstax object.
   *
   * @param \Drupal\quivers\ClientFactory $client_factory
   *   The client.
   */
  public function __construct(ClientFactory $client_factory, ConfigFactoryInterface $config_factory) {
    $this->quivers_config = $config_factory->get('quivers.settings');
    $this->quivers_client = $client_factory->createInstance($this->quivers_config);
  }

  /**
   * Returns Order Tax from Quivers Validate API
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   OrderInterface object.
   * @return Order Total tax which will be added to Order.
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
      \Drupal::logger('quivers')->notice("Quivers Validate API did not work, response status code is: ".(string)$quivers_validate_response->getStatusCode());
      return 'FAILED';
    }

    $quivers_validate_response_json = json_decode($quivers_validate_response->getBody()->getContents(), true);

    $total_tax = 0;
    if (!empty($quivers_validate_response_json['result'])) {
      foreach ($quivers_validate_response_json['result']['items'] as $quivers_validate_order_item) {
        if (empty($quivers_validate_order_item['pricing']['taxes'])) {
          \Drupal::logger('quivers')->notice("Quivers Validate Taxes are Empty for: ".(string)$order->get('shipments')->referencedEntities()[0]->getShippingProfile()->address->first()->getCountryCode());
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
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   OrderInterface object.
   * @return array $request_body for QuiversValidateAPI.
   */
  public function prepareValidateRequest(OrderInterface $order) {
    $currency_code = $order->getTotalPrice() ? $order->getTotalPrice()->getCurrencyCode() : $order->getStore()->getDefaultCurrencyCode();
    $marketplace_id = self::getQuiversMarketplaceID($currency_code);
    if($marketplace_id === NULL) {
      return;
    }
    $order_shipment_profile = $order->get('shipments')->referencedEntities()[0]->getShippingProfile()->address->first();

    // get shipping details from order shipping information
    $order_country_code = $order_shipment_profile->getCountryCode();
    $order_region_code = $order_shipment_profile->getAdministrativeArea();
    $order_line1 =($order_shipment_profile->getAddressLine1() === NULL) ? "" : $order_shipment_profile->getAddressLine1();
    $order_line2 =($order_shipment_profile->getAddressLine2() === NULL) ? "" : $order_shipment_profile->getAddressLine2();
    $order_city =($order_shipment_profile->getLocality() === NULL) ? "" : $order_shipment_profile->getLocality();
    $order_post_code =($order_shipment_profile->getPostalCode() === NULL) ? "" : $order_shipment_profile->getPostalCode();

    if ( $order_region_code === NULL || $order_region_code === "" || !(strlen($order_region_code) < 3) ) {
      $order_region_code = self::getQuiversCountriesRegionCode($order, $order_country_code);
      if ( $order_region_code === 'FAILED' ) {
        return;
      }
      \Drupal::logger('quivers')->notice("Order Region Code is NULL and new OrderRegionCode is: ".$order_region_code);
    }
    if ( $order_region_code === NULL ) {
      \Drupal::logger('quivers')->error("Order Region Code is NULL for CountryCode: ".$order_country_code);
      return;
    }

    $request_body = array(
      "marketplaceId" => (string)$marketplace_id, 
      "shippingAddress" => array(
        "line1" => $order_line1,
        "line2" => $order_line2,
        "city" => $order_city,
        "postCode" => $order_post_code,
        "region" => $order_region_code,
        "country" => $order_country_code
      ),
      "items" => [],
    );
    $line_items = [];
    foreach ($order->getItems() as $order_item) {
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
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   OrderInterface object.
   * @return Order Total tax which will be added to Order.
   */
  public function getQuiversCountriesTax(OrderInterface $order) {
    $quivers_countries_response_json = self::getQuiversCountriesJSON();
    if ( $quivers_countries_response_json == 'FAILED' ) {
      return $quivers_countries_response_json;
    }

    // get shipping details from order shipping information
    $order_country_code = $order->get('shipments')->referencedEntities()[0]->getShippingProfile()->address->first()->getCountryCode();
    $order_region_code = $order->get('shipments')->referencedEntities()[0]->getShippingProfile()->address->first()->getAdministrativeArea();
    if ( $order_region_code == NULL ) {
      $order_region_code = $order->get('shipments')->referencedEntities()[0]->getShippingProfile()->address->first()->getLocality();
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
      \Drupal::logger('quivers')->error("Region Not found in QuiversCountriesAPI: ".(string)$order_region_code." ,".(string)$order_country_code);
      return 'FAILED';
    }
    foreach ($order->getItems() as $order_item) {
      $order_item_tax = (float)$order_item->getAdjustedTotalPrice()->getNumber() * $quivers_countries_max_tax_rate * (int)$order_item->getQuantity();
      $total_tax = $total_tax + $order_item_tax;
    }
    return $total_tax;
  }

  /**
   * Hit QuiversCountries API and returns Response JSON
   *
   * @return json Quivers Countries API response
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
      \Drupal::logger('quivers')->notice("QuiversCountriesAPI did not work, response status code is: ".(string)$quivers_countries_response->getStatusCode());
      return 'FAILED';
    }

    return json_decode($quivers_countries_response->getBody()->getContents(), true);
  }

  /**
   * For Countries other than U.S, using locality as region name 
   * and get Region code from QuiversCountries API.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   OrderInterface object.
   * @param $order_country_code
   *   OrderInterface object.
   * @return string Two letter Region Code.
   */
  public function getQuiversCountriesRegionCode(OrderInterface $order, $order_country_code) {
    $order_region_name = $order->get('shipments')->referencedEntities()[0]->getShippingProfile()->address->first()->getLocality();
    $order_region_code = NULL;
    
    if ( $order_region_name === NULL ) {
      return $order_region_code;
    }
    $quivers_countries_response_json = self::getQuiversCountriesJSON();
    if ( $quivers_countries_response_json == 'FAILED' ) {
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
        if ( $order_region_code === NULL && array_key_exists('0', $value['regions']) ) {
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
      return $marketplace_id;
    }
    else {
      \Drupal::logger('quivers')->error("QuiversMarketplaceID NOT found in Quivers Settings for Currency: ".$currency_code);
      return;
    }
    if ( empty($marketplace_id) ) {
      \Drupal::logger('quivers')->error("QuiversMarketplaceID is Empty for Currency: ".$currency_code);
      return;
    }
  }

}
