<?php

namespace Drupal\quivers_drupal_plugin;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_order\Adjustment;

/**
 * Provides an order processor that modifies the cart according to the business logic.
 */
class CustomOrderProcessor implements OrderProcessorInterface
{
  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order)
  {
    $client = \Drupal::httpClient();
    if (!$order->getBillingProfile()) {
      // order_billing_info not found, Don't Process further
      return;
    }
    
    $total_tax = self::get_total_order_tax($client, $order);
    if ($total_tax == 'FAILED') {
      // Both API's Failed, Don't Add Taxes to the Order
      return;
    }

    if ( is_null($total_tax) ) {
      \Drupal::logger('quivers_drupal_plugin')->error("Tax from quivers if null");
      return;
    }

    $currency_code = $order->getTotalPrice() ? $order->getTotalPrice()->getCurrencyCode() : $order->getStore()->getDefaultCurrencyCode();

    $order_initial_adjustments = $order->getAdjustments();
    array_push($order_initial_adjustments, new Adjustment([
        'type' => 'tax',
        'label' => 'Tax',
        'amount' => new Price((string) $total_tax, $currency_code),
      ]));
    $order->setAdjustments($order_initial_adjustments);
  }

  /**
  * Hit Quivers Validate API with the request_body
  *
  * @return Response JSON Data Or string 'FAILED'
  */
  public function get_quivers_validate_api_response($client, $order) {
    $request_body = self::get_quivers_validate_request_body($order);
    try {
      $quivers_validate_response = $client->post(
        'https://api.quiverstest.com/v1/customerOrders/validate?includeFulfillers=false&calculateTaxes=true', 
        ['json' => $request_body]
      ); 
    }
    catch (RequestException $e) {
      \Drupal::logger('quivers_drupal_plugin')->notice($e->getMessage());
      // Quivers Validate API FAILED
      return 'FAILED';
    }
    catch (Exception $e) {
      \Drupal::logger('quivers_drupal_plugin')->notice($e->getMessage());
      // Quivers Validate API FAILED
      return 'FAILED';
    }
    if ($quivers_validate_response->getStatusCode() != 200) {
      \Drupal::logger('quivers_drupal_plugin')->notice("QuiversValidateAPI did not work, response status code is: ".(string)$quivers_validate_response->getStatusCode());
      // Quivers Validate API FAILED
      return 'FAILED';
    }

    return json_decode($quivers_validate_response->getBody()->getContents(), true);
  }

  /**
  * Hit Quivers Countries API with the request_body
  *
  * @return Response JSON Data Or string 'FAILED'
  */
  public function get_quivers_countries_api_response($client) {
    try {
      $quivers_countries_response = $client->get('https://api.quiverstest.com/v1/countries');
      
    } 
    catch (RequestException $e) {
      \Drupal::logger('quivers_drupal_plugin')->notice($e->getMessage());
      // Quivers Countries API FAILED
      return 'FAILED';
    }
    catch (Exception $e) {
      \Drupal::logger('quivers_drupal_plugin')->notice($e->getMessage());
      // Quivers Countries API FAILED
      return 'FAILED';
    }
    if ($quivers_countries_response->getStatusCode() != 200) {
      \Drupal::logger('quivers_drupal_plugin')->notice("QuiversCountriesAPI did not work, response status code is: ".(string)$quivers_countries_response->getStatusCode());
      // Quivers Countries API FAILED
      return 'FAILED';
    }

    return json_decode($quivers_countries_response->getBody()->getContents(), true);
  }


  /**
   * Calculates Order Tax
   *
   * @return
   * The Total Tax Generated for an Order by hitting either Quivers Validated API
   * Or Quivers Countries API. If API fails, then 'FAILED' string is returned.
   */
  public function get_total_order_tax($client, $order) {
    $total_tax = 0;
    // Using Quivers Validate API
    $quivers_validate_response = self::get_quivers_validate_api_response($client, $order);
    if ( $quivers_validate_response == 'FAILED' ) {
      // Quivers Validate API FAILED, Using Quivers Countries API
      $quivers_countries_response = self::get_quivers_countries_api_response($client);
      if ($quivers_countries_response == 'FAILED') {
        return $quivers_countries_response;
      }
      return self::get_quivers_countries_tax($quivers_countries_response, $order);
    }
    // $quivers_validate_response['result'] = []; // Testing
    if (!empty($quivers_validate_response['result'])) {
      foreach ($quivers_validate_response['result']['items'] as $quivers_validate_order_item) {
        $tax = (float)$quivers_validate_order_item['pricing']['taxes'][0]['amount'];
        $total_tax = $total_tax + $tax;
      }
      return $total_tax;
    }
    else {
      // In case of No Response from Quivers Validate API, Hit Quivers Countries API
      $quivers_countries_response = self::get_quivers_countries_api_response($client);
      if ($quivers_countries_response == 'FAILED') {
        return $quivers_countries_response;
      }
      return self::get_quivers_countries_tax($quivers_countries_response, $order);
    }
  }

  /**
  * Extracting Total Tax from Quivers Countries API.
  *
  * @param string $order_region_code
  * Two letter Code defined for a specific Region.
  *
  * @param string $order_country_code
  * Two letter Code defined for a specific Country.
  *
  * @param float $quivers_countries_max_tax_rate
  * The max tax rate used for any State.
  *
  * The MaxTaxRate is then multiplied by each Order Item price and it's quantities
  *
  * @return
  * Returns total_tax.
  */
  public function get_quivers_countries_tax($quivers_countries_response, $order) {
    $order_billing_info = $order->getBillingProfile();
    $order_region_code = $order_billing_info->address->first()->getAdministrativeArea();
    $order_country_code = $order_billing_info->address->first()->getCountryCode();
    foreach ($quivers_countries_response['result'] as $key => $value) {
      if ($value['abbreviations']['two'] == $order_country_code) {
        foreach ($value['regions'] as $region_key => $region_value) {
          if ($region_value['abbreviation'] == $order_region_code) {
            $quivers_countries_max_tax_rate = (float)$region_value['maxTaxRate'];}
        }
      }
    }
    foreach ($order->getItems() as $order_item) {
      $order_item_tax = (float)$order_item->getAdjustedTotalPrice()->getNumber() * $quivers_countries_max_tax_rate * (int)$order_item->getQuantity();
      $total_tax = $total_tax + $order_item_tax;
    }
    return $total_tax; 
  }

  /**
  * Generating Request Data for Quivers Validate API.
  *
  * @param string $order_region_code
  * Two letter Code defined for a specific Region.
  *
  * @param string $order_country_code
  * Two letter Code defined for a specific Country.
  *
  * @param array $request_body
  * An associative array with element 'items' is a nested Array of Order items with their Pricing.
  *
  * @return
  * Returns an Array with required API data.
  */
  public function get_quivers_validate_request_body($order) {
    $order_billing_info = $order->getBillingProfile();
    $order_region_code = $order_billing_info->address->first()->getAdministrativeArea();
    $order_country_code = $order_billing_info->address->first()->getCountryCode();
    $request_body = array(
      "marketplaceId" => "2cd56ca3-dce3-4ff8-8569-8299ea8f9853", 
      "shippingAddress" => array(
        "line1" => "",
        "line2" => "",
        "city" => "",
        "postCode" => "",
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
}