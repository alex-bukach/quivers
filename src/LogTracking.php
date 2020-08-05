<?php
namespace Drupal\quivers;

use Drupal\Core\Config\ConfigFactoryInterface;

class LogTracking
{
     protected $logEndpoint = 'logs/';
     protected $loggingClient;
     protected $quiversConfig;
     protected $config;

     public function __construct(ClientFactory $client_factory, ConfigFactoryInterface $config_factory) {
                $this->quiversConfig = $config_factory->get('quivers.settings');
                $this->loggingClient = $client_factory->createLoggingInstance($this->quiversConfig->get());
     }

    public function session_start($order_detail, $time){
        $logData = [
            "order_detail" => $order_detail,
            "time" => $time
        ];
         $this->logging("SESSION START", $logData);
    }

    public function validate_api_call($request, $response, $start_time, $end_time){
        $logData = [
            "start_time" => $start_time,
            "end_time" => $end_time,
            "request" => $request,
            "response" => $response
        ];
        $this->logging("VALIDATE API CALL", $logData);
    }

    public function countries_api_call($request, $response, $start_time, $end_time){
     $logData = [
            "request" => $request,
            "response" => $response,
            "start_time" => $start_time,
            "end_time" => $end_time
        ];
     $this->logging("COUNTRIES API CALL", $logData);
    }

    public function payment_request($order_data, $payment_method, $time, $response, $success){
     $logData = [
            "order_data" => $order_data,
            "payment_method" => $payment_method,
            "time" => $time,
            "response" => $response,
            "success" => $success
        ];
     $this->logging("PAYMENT REQUEST", $logData);
    }

    public function stripe_statement_descriptor($stripe_statement_descriptor){
        $logData = [
            "stripe_statement_descriptor" => $stripe_statement_descriptor
        ];
        $this->logging("STRIPE STATEMENT DESCRIPTOR", $logData);

    }

    public function statement_descriptor($statement_descriptor){
        $logData = [
            "statement_descriptor" => $statement_descriptor
        ];
        $this->logging("STATEMENT DESCRIPTOR", $logData);
    }

    public function order_data($order){
        $logData = [
            "order" => $order
        ];
        $this->logging("ORDER DATA", $logData);
    }

    public function shipping_address($address){
     $logData = [
        "address" => $address
     ];
    $this->logging("SHIPPING ADDRESS", $logData);
    }

    public function billing_address($address){
     $logData = [
        "address" => $address
     ];
    $this->logging("BILLING ADDRESS", $logData);
    }

    public function session_end($order_detail, $time){
     $logData = [
            "order_detail" => $order_detail,
            "time" => $time
        ];
     $this->logging("SESSION END", $logData);
    }

    public function logging($type, $data)
    {
        $this->config = $this->quiversConfig->get();
        $quiversAPIKey =  $this->config['quivers_api_key'];
        $logdata = [
            "api_key" => $quiversAPIKey,
            "uuid" => uniqid(),
            "type" => $type,
            "log_data" => $data
        ];
        $response = $this->loggingClient->post($this->logEndpoint,
            ['json' => $logdata]
        );
    }
}