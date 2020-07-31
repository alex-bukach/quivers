<?php

namespace Drupal\quivers;

use Exception;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_tax\Event\CustomerProfileEvent;
use Drupal\commerce_tax\Event\TaxEvents;

use GuzzleHttp\Exception\ClientException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Quivers Service.
 */
class QuiversService {

  /**
   * The Quivers configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $quiversConfig;

  /**
   * The Quivers Tax configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $quiversTaxConfig;

  /**
   * The client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $quiversClient;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;
  protected $logTracking;

  /**
   * Constructs a new QuiversTax object.
   *
   * @param \Drupal\quivers\ClientFactory $client_factory
   *   The client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger.
   */
  public function __construct(ClientFactory $client_factory, ConfigFactoryInterface $config_factory, EventDispatcherInterface $event_dispatcher, LoggerChannelFactoryInterface $logger_factory, LogTracking $log_tracking) {
    $this->quiversConfig = $config_factory->get('quivers.settings');
    $this->quiversTaxConfig = $config_factory->get('quivers.tax_settings');
    $this->quiversClient = $client_factory->createInstance($this->quiversConfig->get());
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger_factory->get('quivers');
    $this->logTracking = $log_tracking;
  }

	public function splitBills($totalAmount, int $ways) {
		$splittedAmounts = [];
		// divide total amount into ways
		$amountPerItem = round($totalAmount/$ways, 2);
		// splitted amounts for each way
		for($i=0; $i<$ways; $i++) {
		  $splittedAmounts[$i] = $amountPerItem;
		}
		// check difference of given total amount and total splitted amount
		$amountDiff = round($totalAmount - array_sum($splittedAmounts), 2);
		// if diff is 0 return splitted amounts
		if($amountDiff==0) return $splittedAmounts;
		$iterations = abs((int) ($amountDiff * 100));
		$isAddition = $amountDiff > 0;
		// add or subtract 0.01 to splitted amounts to get to the total amount
		if($isAddition) {
		  for ($j=0; $j<$iterations; $j++) {
		    $splittedAmounts[$j] = round((float) ($splittedAmounts[$j] + ($isAddition ? 0.01 : -0.01)), 2);
		  }
		}else {
		  $length = count($splittedAmounts) - 1;
		  for($j=$length; $j > ($length - $iterations); $j--) {
		    $splittedAmounts[$j] = round((float) ($splittedAmounts[$j] + ($isAddition ? 0.01 : -0.01)), 2);
		  }
		}
		return $splittedAmounts;
	}

  public function getLineItemsDiscount($order, $orderLevelDiscount=0, $discountPercentage=0)
  {
    $per_item_discount = [];
    $subTotalPrice = $order->getSubtotalPrice()->getNumber();
    $items = $order->getItems();

    if($orderLevelDiscount > 0) $discountPercentage = ($orderLevelDiscount/$subTotalPrice);
    elseif(!$discountPercentage) return [];
    // divide discounts
    foreach($items as $item) {
      $itemPrice = $item->getUnitPrice()->getNumber() * $item->getQuantity();
      $itemDiscount = $itemPrice * $discountPercentage;
      $per_item_discount[$item->uuid()] = round($itemDiscount, 2);
    }
    return $per_item_discount;
  }

  public function processOrderDiscounts($order) {
    // $rounder = \Drupal::service('commerce_price.rounder');
    $per_item_discount = [];
    $coupons = $order->get('coupons')->referencedEntities();
    $promotion = count($coupons) ? $coupons[0]->getPromotion() : null;
    $couponType = $promotion ? $promotion->getOffer()->getEntityTypeId() : null;
    if($couponType && (string)$couponType==='commerce_order') {
      $offer = $promotion->get('offer')->first()->getValue();
      $offerType = $offer['target_plugin_id'];
      $target_plugin_configuration = $offer['target_plugin_configuration'];
      if($offerType!='order_buy_x_get_y') {
          $orderLevelDiscount = 0;
          $discountPercentage = 0;
          switch ($offer['target_plugin_id']) {
            case 'order_fixed_amount_off':
              $amountOff = $target_plugin_configuration['amount']['number'];
              $orderSubtotal = $order->getSubtotalPrice();
              if($amountOff > $orderSubtotal->getNumber()) {
                $amountOff = $orderSubtotal->getNumber();
              }
              $orderLevelDiscount = $amountOff;
              break;
            case 'order_percentage_off':
              $percentageOff = $target_plugin_configuration['percentage'];
              $discountPercentage = $percentageOff;
              break;
          }
          if($orderLevelDiscount || $discountPercentage) {
            // split discounts per applicable items
            $per_item_discount = $this->getLineItemsDiscount($order, $orderLevelDiscount, $discountPercentage);
          }
      }
    }
    $allOrderItems = [];
    $this->container = \Drupal::getContainer();
    $resolver = $this->container->get('commerce_pricelist.price_resolver');
    $context = new \Drupal\commerce\Context($order->getCustomer(), $order->getStore());
    foreach($order->getItems() as $order_item) {
      $productPrice = $order_item->getPurchasedEntity()->getPrice()->getNumber();
      $resolved_price = $resolver->resolve($order_item->getPurchasedEntity(), 1, $context);
      $productPrice = $resolved_price ? $resolved_price->getNumber() : $productPrice;
      $unitPrice = $order_item->getUnitPrice()->getNumber();
      $itemDiscount = 0;
      if($productPrice - $unitPrice > 0) {
        $itemDiscount = $productPrice - $unitPrice;
      }
      if($couponType==='commerce_order_item') {
        $offer = $promotion->getOffer();
        $offer_conditions = new \Drupal\commerce\ConditionGroup($offer->getConditions(), $offer->getConditionOperator());
        if($offer_conditions->evaluate($order_item)) {
          $offer = $promotion->get('offer')->first()->getValue();
          $target_plugin_configuration = $offer['target_plugin_configuration'];
          if(!$target_plugin_configuration['display_inclusive']) {
            switch ($offer['target_plugin_id']) {
              case 'order_item_fixed_amount_off':
                $amountOff = $target_plugin_configuration['amount']['number'];
                // $itemDiscount = $rounder->round($amountOff);
                if($amountOff > $order_item->getUnitPrice()->getNumber()) {
                  $amountOff = $order_item->getUnitPrice()->getNumber();
                }
                $itemDiscount += $amountOff;
                break;
              case 'order_item_percentage_off':
                $percentageOff = $target_plugin_configuration['percentage'];
                $product_price = $order_item->getPurchasedEntity()->getPrice();
                $amountOff = $product_price->multiply($percentageOff);
                $itemDiscount += $amountOff->getNumber();
                if($amountOff > $order_item->getUnitPrice()->getNumber()) {
                  $amountOff = $order_item->getUnitPrice()->getNumber();
                }
                break;
            }
          }
        }
      }
      if(count($per_item_discount) > 0) {
        $lineItemDiscount = isset($per_item_discount[$order_item->uuid()]) ? $per_item_discount[$order_item->uuid()] : 0;
      }
      $order_item->productPrice = round($productPrice, 2);
      $order_item->perLineItemDiscount = isset($lineItemDiscount) ? $lineItemDiscount : 0;
      $order_item->discountAmount = round($itemDiscount, 2);
      $allOrderItems[] = $order_item;
    }
    return $allOrderItems;
  }



  /**
   * Get tax from Quivers Validate API.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   OrderInterface object.
   *
   * @return array
   *   Array of Order Item Taxes.
   *
   * @throws Exception
   */
  public function calculateValidateTax(OrderInterface $order) {
    $startTime = microtime(true);
    $tax_response = [];
    $user_profile = NULL;
    $order_shipments = [];
    $request_data = [
      "marketplaceId" => NULL,
      "shippingAddress" => [],
      "items" => [],
      "customer" => [],
    ];

    $has_shipments = $order->hasField('shipments') && !$order->get('shipments')->isEmpty();
    if ($has_shipments) {
      $order_shipment_amt = 0;
      foreach ($order->get('shipments')->referencedEntities() as $shipment) {
        $order_shipment_amt = $order_shipment_amt + $shipment->getAmount()->getNumber();
      }
      $splitted_order_shipments = $order_shipment_amt ? $this->splitBills($order_shipment_amt, count($order->getItems())) : [];
      $itemCounter = 0;
      foreach ($order->getItems() as $order_item) {
        // $order_item_shipment_amt = $order_shipment_amt / (int) $order_item->getQuantity();
        // $order_item_shipment_amt = $order_shipment_amt / (int) count($order->getItems());
        $order_shipments[$order_item->uuid()] = isset($splitted_order_shipments[$itemCounter]) ? $splitted_order_shipments[$itemCounter] : 0;
        $itemCounter++;
      }
    }
    // process discounts on order items
    $allOrderItems = $this->processOrderDiscounts($order);
    // iterate on allOrderItems and prepare validate request api postdata
    foreach($allOrderItems as $order_item) {
    	$splitted_discounts = [];
    	if($order_item->perLineItemDiscount) {
    		$splitted_discounts = $this->splitBills($order_item->perLineItemDiscount, $order_item->getQuantity());
    	}
      // divide shipments into quantity
      $splitted_shipments = [];
      if(isset($order_shipments[$order_item->uuid()]) && $order_shipments[$order_item->uuid()] > 0) {
        $splitted_shipments = $this->splitBills($order_shipments[$order_item->uuid()], $order_item->getQuantity());
      }
    	$user_profile = $this->resolveCustomerProfile($order_item);
      // If no profile resolved yet, no need for any Tax calculation.
      if (!$user_profile) {
        continue;
      }
      for($i=0; $i<$order_item->getQuantity(); $i++) {
      	$quantity_discount = $order_item->discountAmount;
      	if(count($splitted_discounts) > 0) $quantity_discount += $splitted_discounts[$i];
      	// $validate_request_item_data = self::prepareValidateRequestItemData($order_item, $order_shipments);
        $quantity_shipment = isset($splitted_shipments[$i]) ? $splitted_shipments[$i] : 0;
        $validate_request_item_data = self::prepareValidateRequestItemData($order_item, $quantity_shipment);
		    $order_coupons = $order->get('coupons')->referencedEntities();
		    if(count($order_coupons) > 0) {
		    	$couponCode = $order_coupons[0]->get('code')->getValue()[0]['value'];
		    	$promotion = $order_coupons[0]->getPromotion();
			    $offer = $promotion->get('offer')->first()->getValue();
			    $offerType = $offer['target_plugin_id'];
			    if($offerType==='order_buy_x_get_y') {
			    	$order_buy_x_get_y = 1;
			    }else {
			    	$itemUnitPrice = $order_item->productPrice;
			    	$order_buy_x_get_y = 0;
			    }
		    }else {
		    	$couponCode = '';
		    	$itemUnitPrice = $order_item->productPrice;
		    	$order_buy_x_get_y = 0;
		    }
		    if($order_buy_x_get_y==0) {
		    	$validate_request_item_data['pricing']['unitPrice'] = $order_item->productPrice;
	        $discount_obj = [
	          'code' => $couponCode,
	          'name' => $couponCode,
	          'description' => 'discount',
	          'amount' => $quantity_discount ? (0 - round($quantity_discount, 2)) : 0
	        ];
	        $validate_request_item_data['pricing']['discounts'][] = $discount_obj;
	      }
        $request_data['items'][] = $validate_request_item_data;
      }
    }

    // previous code
    /*
    foreach ($order->getItems() as $order_item) {
      $user_profile = $this->resolveCustomerProfile($order_item);
      // If no profile resolved yet, no need for any Tax calculation.
      if (!$user_profile) {
        continue;
      }
      $validate_request_item_data = self::prepareValidateRequestItemData($order_item, $order_shipments);
      $request_data['items'][] = $validate_request_item_data;
    } */

    // If no items are ready for Tax calculation. return [].
    if (empty($request_data["items"])) {
      return $tax_response;
    }

    // Get Marketplace Id using Order Store UUID.
    $marketplace_id = self::getMarketPlaceId($order);
    if ($marketplace_id === NULL) {
      return $tax_response;
    }
    $request_data['marketplaceId'] = $marketplace_id;
    $address_data = self::getShippingAddressData($user_profile, $order);
    if (empty($address_data)) {
      return $tax_response;
    }
    $request_data['shippingAddress'] = $address_data['address'];
    $customer_data = $address_data['customer'];
    $customer_data['email'] = $order->getEmail();
    $request_data['customer'] = $customer_data;
    $this->logTracking->session_start($request_data, gmdate('r', $startTime)."UTC");
    try {
      $response = $this->quiversClient->post('customerOrders/validate',
        ['json' => $request_data]
      );
      $response_data = Json::decode($response->getBody()->getContents());
      $tax_response = self::formatValidateResponse($response_data);
      $config = $this->quiversConfig->get();
      $baseUri = $config["api_mode"]=='production'? 'https://api.quivers.com/v1/':'https://api.quiversdemo.com/v1/';
      $request = ["base url"=>$baseUri, "Endpoint"=>'customerOrders/validate', 'Data'=>$request_data];
      $endTime = microtime(true);
      $order_detail = ["tax_data"=>$request_data, "Response"=>$response_data];
      $statement_descriptor = $response_data['result']['statementDescriptor'];
      $this->logTracking->statement_descriptor($statement_descriptor);
      $order->setData('field_statement_descriptor', $statement_descriptor);
      $this->logTracking->order_data($order->getData('field_statement_descriptor'));
      $this->logTracking->validate_api_call($request, $response_data, gmdate('r', $startTime)."UTC", gmdate('r', $endTime)."UTC");
      $this->logTracking->session_end($order_detail, gmdate('r', $endTime)."UTC");
    }
    catch (ClientException $e) {
      $this->logger->notice($e->getMessage());
      throw new Exception($e->getMessage());
    }
    catch (\Exception $e) {
      $this->logger->notice($e->getMessage());
      throw new Exception($e->getMessage());
    }
    return $tax_response;
  }

  /**
   * Prepare Quivers Validate API Request data for given Order Item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   OrderItemInterface object.
   *
   * @return array
   *   Quivers Validate API Request LineItem data.
   */
  // protected function prepareValidateRequestItemData(OrderItemInterface $order_item, array $order_shipments) {
  protected function prepareValidateRequestItemData(OrderItemInterface $order_item, $quantity_shipment) {
    $order_item_unit_price = (float) $order_item->getUnitPrice()->getNumber();
    $order_item_shipping_fee = $quantity_shipment;
    $line_item = [
      'product' => [
        'name' => $order_item->getTitle(),
        'variant' => [
          "name" => $order_item->getTitle(),
          "refId" => $order_item->uuid(),
        ],
      ],
      // 'quantity' => (int) $order_item->getQuantity(),
      'quantity' => 1,
      'pricing' => [
        'unitPrice' => $order_item_unit_price,
        'shippingFees' => [[
          'name' => 'Shipping',
          'amount' => (float) $order_item_shipping_fee,
        ]],
      ],
    ];

    return $line_item;
  }

  /**
   * Get Address Data for Quivers Validate Tax API.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   ProfileInterface object.
   *
   * @return array
   *   Quivers Validate API Request Address data.
   */
  protected function getShippingAddressData(ProfileInterface $profile, OrderInterface $order) {
    $address_data = [];
    try {
        $order_profiles = $order->collectProfiles();
        if ($order_profiles['shipping']){
            $address = $order_profiles['shipping']->get('address')->first();
            $this->logTracking->shipping_address($order_profiles['shipping']->get('address')->getValue());
        }
        else{
        /** @var \Drupal\address\AddressInterface $address */
        $address = $profile->get('address')->first();
        $this->logTracking->billing_address($profile->get('address')->getValue());
      }
    }
    catch (MissingDataException $e) {
      $this->logger->error("Unable to access Address instance." . $e->getMessage());
      return $address_data;
    }
    $order_country_code = $address->getCountryCode();
    $order_region_code = $address->getAdministrativeArea();
    $order_region_name = $address->getLocality();

    if (!($order_region_name || $order_region_code)) {
      // If not Region & Code, can not calculate Tax.
      $this->logger->notice("Order Region Code and Region Name are NULL.");
      return $address_data;
    }

    if (!($order_region_code && strlen($order_region_code) <= 3)) {
      // We don't have correct Region Code, get Code from Quivers.
      $order_region_code = self::getQuiversRegionCode($order_country_code, $order_region_name);
    }

    if (!$order_region_code) {
      $order_region_code = 'N/A';
    }

    $address_data = [
      'address' => [
        'line1' => ($address->getAddressLine1() === NULL) ? "" : $address->getAddressLine1(),
        'line2' => ($address->getAddressLine2() === NULL) ? "" : $address->getAddressLine2(),
        'city' => ($address->getLocality() === NULL) ? "" : $address->getLocality(),
        'postCode' => ($address->getPostalCode() === NULL) ? "" : $address->getPostalCode(),
        'region' => $order_region_code,
        'country' => $address->getCountryCode(),
      ],
      'customer' => [
        'firstname' => ($address->getGivenName() === NULL) ? "" : $address->getGivenName(),
        'lastname' => ($address->getFamilyName() === NULL) ? "" : $address->getFamilyName(),
      ]
    ];

    return $address_data;
  }

  /**
   * Format Quivers Validate API response.
   *
   * @param array $validate_tax_data
   *   Response from Quivers Validate API.
   *
   * @return array
   *   Array of Order Item wise tax, NULL.
   */
  protected function formatValidateResponse(array $validate_tax_data) {
    $order_item_taxes = [];
    if (empty($validate_tax_data['result']) || empty($validate_tax_data['result']['items'])) {
      return $order_item_taxes;
    }
    foreach ($validate_tax_data['result']['items'] as $order_item_tax_data) {
      $order_item_tax = 0;
      foreach ($order_item_tax_data['pricing']['taxes'] as $validate_tax) {
        $order_item_tax = $order_item_tax + $validate_tax['amount'];
      }
      if(isset($order_item_taxes[$order_item_tax_data['variantRefId']])) {
        $order_item_taxes[$order_item_tax_data['variantRefId']] += $order_item_tax;
      }else {
        $order_item_taxes[$order_item_tax_data['variantRefId']] = $order_item_tax;
      }
      // $order_item_taxes[$order_item_tax_data['variantRefId']] = $order_item_tax;
    }
    return $order_item_taxes;
  }

  /**
   * Returns Order Item Taxes from Quivers Countries API.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   OrderInterface object.
   *
   * @return array
   *   Array of Order Item taxes.
   */
  public function calculateCountryTax(OrderInterface $order) {
    $startTime = microtime(true);
    $tax_response = [];
    $address = NULL;

    foreach ($order->getItems() as $order_item) {
      $user_profile = $this->resolveCustomerProfile($order_item);
      // If no profile resolved yet, no need for any Tax calculation.
      if (!$user_profile) {
        continue;
      }
      try {
        /** @var \Drupal\address\AddressInterface $address */
        $address = $user_profile->get('address')->first();
      }
      catch (MissingDataException $e) {
        $this->logger->error("Unable to access Address instance." . $e->getMessage());
        return $tax_response;
      }
      if ($address) {
        break;
      }
    }
    // If no items are ready for Tax calculation. return [].
    if ($address === NULL) {
      return $tax_response;
    }
    $order_country_code = $address->getCountryCode();
    $order_region_code = $address->getAdministrativeArea();
    $order_region_name = $address->getLocality();
    $order_data = ["Country code"=>$order_country_code, "Region code"=>$order_region_code, "Region Name"=>$order_region_name];
    $this->logTracking->session_start(["Tax Data"=>$order_data], gmdate('r', $startTime)."UTC");
    $countries_response_data = self::getQuiversCountries();

    // If API call unable to return Countries list.
    if (empty($countries_response_data)) {
      return $tax_response;
    }

    $country_max_tax_rate = NULL;
    foreach ($countries_response_data['result'] as $value) {
      if ($value['abbreviations']['two'] === $order_country_code) {
        foreach ($value['regions'] as $region_value) {
          if (strcasecmp($region_value['abbreviation'], $order_region_code) == 0) {
            $country_max_tax_rate = (float) $region_value['maxTaxRate'];
            break;
          }
          if (strcasecmp($region_value['name'], $order_region_name) == 0) {
            $country_max_tax_rate = (float) $region_value['maxTaxRate'];
            break;
          }
        }
        // Quivers supports "N/A" regions for some countries.
        if (!$country_max_tax_rate && array_key_exists('0', $value['regions'])) {
          $country_max_tax_rate = (float) $value['regions']["0"]["maxTaxRate"];
        }
      }
      if ($country_max_tax_rate) {
        break;
      }
    }

    if ($country_max_tax_rate === NULL) {
      $this->logger->error("Region not supported with Quivers - " . (string) $order_region_code . "|" . (string) $order_country_code);
      return $tax_response;
    }

    foreach ($order->getItems() as $order_item) {
      $tax_response[$order_item->uuid()] = (float) $order_item->getAdjustedUnitPrice()->getNumber() * $country_max_tax_rate * (int) $order_item->getQuantity();
    }
    $endTime = microtime(true);
    $this->logTracking->session_end(["Tax Data"=>$order_data, "Response"=>$countries_response_data], gmdate('r', $endTime)."UTC");
    return $tax_response;
  }

  /**
   * Quivers Countries API with Region Details.
   *
   * @return array
   *   List of Countries with Region details.
   */
  protected function getQuiversCountries() {
    $startTime = microtime(true);
    $countries = [];
    try {
      $response = $this->quiversClient->get('countries');
      $countries = Json::decode($response->getBody()->getContents());
      $config = $this->quiversConfig->get();
      $baseUri = $config["api_mode"]=='production'? 'https://api.quivers.com/v1/':'https://api.quiversdemo.com/v1/';
      $request = ["base url"=>$baseUri, "Endpoint"=>'countries'];
      $endTime = microtime(true);
      $this->logTracking->countries_api_call($request, $countries, gmdate('r', $startTime)."UTC", gmdate('r', $endTime)."UTC");
    }
    catch (ClientException $e) {
      $this->logger->notice($e->getMessage());
    }
    catch (\Exception $e) {
      $this->logger->notice($e->getMessage());
    }
    return $countries;
  }

  /**
   * Get Region code using Region Name from Quivers.
   *
   * @param string $country_code
   *   String country name.
   * @param string $region_name
   *   String region name.
   *
   * @return string
   *   Two letter Region Code.
   */
  protected function getQuiversRegionCode($country_code, $region_name) {
    $quivers_countries_response_data = self::getQuiversCountries();
    $region_code = NULL;
    if (empty($quivers_countries_response_data)) {
      return $region_code;
    }

    foreach ($quivers_countries_response_data['result'] as $value) {
      if ($value['abbreviations']['two'] == $country_code) {
        foreach ($value['regions'] as $region_value) {
          if (strcasecmp($region_value['name'], $region_name) == 0) {
            $region_code = $region_value['abbreviation'];
            break;
          }
        }
        // Quivers supports "N/A" regions for some countries.
        if (!$region_code && array_key_exists('0', $value['regions'])) {
          return $value['regions']["0"]["abbreviation"];
        }
      }
      if ($region_code) {
        break;
      }
    }
    return $region_code;
  }

  /**
   * Get Quivers MarketplaceID from 'Quivers Settings'.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   OrderInterface object.
   *
   * @return string|null
   *   Quivers MarketplaceID or NULL.
   */
  protected function getMarketPlaceId(OrderInterface $order) {
    $marketplace_id = NULL;
    $marketplace_mappings = $this->quiversTaxConfig->get('marketplaces');
    if (!$marketplace_mappings) {
      $this->logger->error("Quivers Marketplaces are NOT configured");
      return $marketplace_id;
    }
    $order_store_id = $order->getStore()->uuid();
    // Get quivers marketplace id for order store uuid.
    foreach ($marketplace_mappings as $mapping) {
      if ($mapping['store_id'] === $order_store_id) {
        $marketplace_id = $mapping['quivers_marketplace_id'];
        break;
      }
    }
    if ($marketplace_id === "") {
      // If somehow Marketplace is not configured
      // for the store correctly and Quivers Tax enabled.
      $this->logger->error("Quivers Marketplace NOT configured for store id - " . $order_store_id);
      $marketplace_id = NULL;
    }
    return $marketplace_id;
  }

  /**
   * Stolen from TaxTypeBase::resolveCustomerProfile().
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The customer profile, or NULL if not yet known.
   */
  protected function resolveCustomerProfile(OrderItemInterface $order_item) {
    $order = $order_item->getOrder();
    $customer_profile = $order->getBillingProfile();
    // A shipping profile is preferred, when available.
    $event = new CustomerProfileEvent($customer_profile, $order_item);
    $this->eventDispatcher->dispatch(TaxEvents::CUSTOMER_PROFILE, $event);
    $customer_profile = $event->getCustomerProfile();

    return $customer_profile;
  }

}