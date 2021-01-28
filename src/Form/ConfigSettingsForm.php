<?php

namespace Drupal\quivers\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\quivers\QuiversCloudhubService;
use Drupal\quivers\QuiversMiddlewareService;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;


/**
 * Configuration form for Quivers settings.
 */
class ConfigSettingsForm extends ConfigFormBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Quivers Middleware Service.
   *
   * @var \Drupal\quivers\Form\QuiversMiddlewareService
   */
  protected $quiversMiddlewareService;

  /**
   * The Quivers Service.
   *
   * @var \Drupal\quivers\Form\QuiversCloudhubService
   */
  protected $quiversCloudhubService;

  /**
   * Constructs a ConfigSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\quivers\Form\QuiversMiddlewareService $quivers_middleware_service
   *   The Quivers Middleware Service.
   * @param \Drupal\quivers\Form\QuiversCloudhubService $quivers_cloudhub_service
   *   The Quivers Cloudhub Service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger, QuiversMiddlewareService $quivers_middleware_service, QuiversCloudhubService $quivers_cloudhub_service) {
    parent::__construct($config_factory);
    $this->messenger = $messenger;
    $this->quiversMiddlewareService = $quivers_middleware_service;
    $this->quiversCloudhubService = $quivers_cloudhub_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('quivers.quivers_middleware_service'),
      $container->get('quivers.quivers_cloudhub_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quivers_config_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['quivers.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('quivers.settings');
    $sync_error = $this->quiversMiddlewareService->verifyProfileStatus($config->get(), FALSE);
 
    if ($sync_error) {
      $this->messenger->addMessage($sync_error, "SYNC_STATUS");
    }
    else {
     $this->messenger->deleteByType("SYNC_STATUS");
    }
    if($config->get('status')) {
      $status =$config->get('status');
    } else {
      $status =$this->t('Inactive');
    }

    // Plugin Environment Configuration.
    $form['environment_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Environment Configuration'),
      '#open' => TRUE,
      '#id' => 'environment-configuration-wrapper',
    ];
    $form['environment_configuration']['api_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('API mode:'),
      '#default_value' => $config->get('api_mode'),
      '#options' => [
        'development' => $this->t('Development'),
        'production' => $this->t('Production'),
      ],
      '#required' => TRUE,
      '#description' => $this->t('The mode to use when connecting to Quivers.'),
    ];
   
    // Quivers Middleware Configuration.
    $form['profile_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Profile Configuration'),
      '#open' => TRUE,
      '#id' => 'profile-configuration-wrapper',
    ];
    $form['profile_configuration']['profile_Status'] = [
      '#type' => 'label',
      '#title' => $status,
      '#id' => 'profile-status-wrapper',
      '#attributes' => array('class' => $config->get('status') =='Active'?'badge badge-success':'badge badge-danger')
    ];
    $form['profile_configuration']['business_refid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Business RefId:'),
      '#default_value' => $config->get('business_refid'),
      '#required' => TRUE,
      '#attributes' => array('title' => 'Enter the Business Ref Id shared by your Quivers Project Manager. This is a unique id that represents your business account on Quivers Admin Panel. The integration will use this to be able to access your orders to update statuses and tracking information.')
    ];
    $form['profile_configuration']['quivers_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key:'),
      '#default_value' => $config->get('quivers_api_key'),
      '#required' => TRUE,
      '#attributes' => array('title' => 'Enter the Quivers API key shared by your Quivers Project Manager. This key will be used by the integration to access Quivers APIs for your business account.')
    ];
    $form['profile_configuration']['drupal_api_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal API Base URL:'),
      '#default_value' => $config->get('drupal_api_base_url'),
      '#required' => TRUE,
      '#attributes' => array('title' => 'Enter the base URL of your site. Please ensure that if test mode is disabled , please set the base URL to HTTPS. The integration will use this base URL to hit Woocommerce APIs to get orders placed by customers.')
    ];
    $form['profile_configuration']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID:'),
      '#default_value' => $config->get('client_id'),
      '#required' => TRUE,
      '#attributes' => array('title' => 'Enter the consumer key generated while setting up the Simple oauth API client for Quivers integration. Refer to "Configure Simple oauth in drupal " section from the plugin installation document for further information.')
    ];
    $form['profile_configuration']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret:'),
      '#default_value' => $config->get('client_secret'),
      '#required' => TRUE,
      '#attributes' => array('title' => 'Enter the consumer secret generated while setting up the Simple oauth API client for Quivers integration. Refer to "Configure Simple oauth in drupal " section from the plugin installation document for further information.')
    ];
    $form['profile_configuration']['refresh_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Refresh Token:'),
      '#default_value' => $config->get('refresh_token'),
      '#required' => TRUE,
      '#maxlength' => 1024,
      '#attributes' => array('title' => 'Enter the generated Refresh Token by making a POST request to /oauth/token.')
    ];

   $form['profile_configuration']['upc_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('UPC Field:'),
      '#default_value' => $config->get('upc_field'),
      '#required' => FALSE,
      '#maxlength' => 1024,
      '#attributes' => array('title' => 'Since UPC is not a standard field in [e-commerce platform name], you can use UPC Field to provide the custom field that you have created on the product page to refer to when syncing UPCs. Please note that you should provide the field name from the API that represents your UPC field on the UI'),
    ];

      if(isset($_SESSION['Quivers']) && is_array($_SESSION['Quivers']['feild_id'])) {
          $form['#attached']['html_head'][] = [[
            '#tag' => 'script',
            '#value' =>'setTimeout(function(){
              jQuery("#'.$_SESSION['Quivers']['feild_id'][0].'").css("border-color", "red");
              jQuery("#'.$_SESSION['Quivers']['feild_id'][1].'").css("border-color", "red");
            }, 1000);'
          ], 'validation_scripts'];
          $_SESSION['Quivers']['feild_id'] =null;
      }else {
        if(isset($_SESSION['Quivers']) && isset($_SESSION['Quivers']['feild_id']) ) {
        $form['#attached']['html_head'][] = [[
          '#tag' => 'script',
          '#value' =>'setTimeout(function(){jQuery("#'.$_SESSION['Quivers']['feild_id'].'").css("border-color", "red");}, 1000);'
        ], 'validation_scripts'];
        $_SESSION['Quivers']['feild_id'] =null;
      }
      }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();
    $sync_flag = TRUE;
    $this->config('quivers.settings')
    ->set('api_mode', $form_state->getValue('api_mode'))
    ->set('business_refid', $form_state->getValue('business_refid'))
    ->set('quivers_api_key', $form_state->getValue('quivers_api_key'))
    ->set('drupal_api_base_url', $form_state->getValue('drupal_api_base_url'))
    ->set('client_id', $form_state->getValue('client_id'))
    ->set('client_secret', $form_state->getValue('client_secret'))
    ->set('refresh_token', $form_state->getValue('refresh_token'))
    ->set('upc_field', $form_state->getValue('upc_field'))
    ->save();
    $upc_field = $form_state->getValue('upc_field');
    $db = \Drupal::database();
    $result = $db->update('commerce_product_variation_field_data')->fields(['upc_hidden_value' => $upc_field])->execute();
    // Create Quivers Middleware Profile.
    $middleware_response = $this->quiversMiddlewareService->profileCreate($values);
    $api_mode = $values['api_mode'];
    if(! preg_match('/^http(s)?:\/\/[a-z0-9-]+(\.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $form['profile_configuration']['drupal_api_base_url']['#value']) ){
      $key = 'url';
      $this->config('quivers.settings')
        ->set('status', 'Inactive')
        ->save();
      $message = $this->t("Invalid Url.Please try again with valid url. If the issue still persists,please contact 'enterprise@quivers.com' for further assistance.");
      $this->printErrorvalidation($key,$message,$form);
    } else if($api_mode != 'development' && preg_match("#((https)://(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|”|\"|'|:|\<|$|\.\s)#", $form['profile_configuration']['drupal_api_base_url']['#value'])!=1){
      $key = 'url_format';
      $this->config('quivers.settings')
        ->set('status', 'Inactive')
        ->save();
      $message = $this->t("Invalid Url, if test mode is disabled, please set the base URL to https and try again. If the issue still persists,please contact 'enterprise@quivers.com' for further assistance.");
      $this->printErrorvalidation($key,$message,$form);
    } else {

          if (isset($middleware_response['error'])) {
            $this->config('quivers.settings')
              ->set('status', 'Inactive')
            ->save();
            $key = isset($middleware_response['error']['key'])?$middleware_response['error']['key']:null;
            $message = isset($middleware_response['error']['message'])?$middleware_response['error']['message']:$middleware_response['error'];
            $this->printErrorvalidation($key,$message,$form);
            $sync_flag = FALSE;
          } else {
            if (isset($middleware_response['isactive']) && $middleware_response['isactive'] == 'false') {
              $this->config('quivers.settings')
              ->set('middleware_profile_id', $middleware_response['uuid'])
              ->set('status', 'Inactive')
              ->save();
              $message = $this->t("Failed to conect to Quivers. Please check if the settings in Quivers and Quivers tax Tabs are saved correctly. If the issue still persists,please contact 'enterprise@quivers.com' for further assistance.");
              $this->printErrorvalidation(null,$message,$form);
            } else {
              $this->config('quivers.settings')
              ->set('middleware_profile_id', $middleware_response['uuid'])
              ->set('status', 'Active')
              ->save();
              if ($sync_flag) {
                $this->messenger->addMessage($this->t('Quivers Profile Synced successfully.'));
              }

            }

          }
      }


  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // Get Quivers Product Groups.

    $quivers_product_groups = [
      'quivers_marketplaces' => [],
      'quivers_claiming_groups' => [],
    ];
    try {

      $quivers_product_groups = $this->quiversCloudhubService->getQuiversProductGroups($values);
      $this->config('quivers.settings')
      ->set('quivers_marketplaces', $quivers_product_groups['quivers_marketplaces'])
      ->set('quivers_claiming_groups', $quivers_product_groups['quivers_claiming_groups'])
      ->save();
      parent::submitForm($form, $form_state);


    }
    catch (\Exception $e) {
      $this->config('quivers.settings')
      ->set('status', 'Inactive')
      ->save();
       $this->messenger->addError("Failed to conect to Quivers. Please check if the settings in Quivers and Quivers tax Tabs are saved correctly. If the issue still persists,please contact 'enterprise@quivers.com' for further assistance.");
    }
    $config = $this->config('quivers.settings');
    if($config->get('status')=== "Active") {
      $url =str_replace("quivers","quivers-tax",\Drupal::request()->headers->get('referer'));
       header("LOCATION: ".$url);
         exit;
    }
  }

  public function printErrorvalidation ($key,$message,$form) {

     switch ($key) {
      case 'api':
        $id = $form['profile_configuration']['quivers_api_key']['#id'];
        $_SESSION['Quivers']['feild_id'] = $id;
        $this->messenger->addError($message);
      break;
      case 'ref_id':
        $id = $form['profile_configuration']['business_refid']['#id'];
        $_SESSION['Quivers']['feild_id'] = $id;
        $this->messenger->addError($message);
      break;
      case 'url':
        $id = $form['profile_configuration']['drupal_api_base_url']['#id'];
        $_SESSION['Quivers']['feild_id'] = $id;
        $this->messenger->addError($message);
      break;
      case 'url_format':
        $id = $form['profile_configuration']['drupal_api_base_url']['#id'];
        $_SESSION['Quivers']['feild_id'] = $id;
       $this->messenger->addError($message);
      break;
      case 'secret':
        $id = [
              $form['profile_configuration']['client_id']['#id'],
              $form['profile_configuration']['client_secret']['#id']
        ];
        $_SESSION['Quivers']['feild_id'] = $id;
      $this->messenger->addError($message);
      break;
      case 'token':
        $id = $form['profile_configuration']['refresh_token']['#id'];
        $_SESSION['Quivers']['feild_id'] = $id;
        $this->messenger->addError($message);
      break;
      default :
        $this->messenger->addError($message);
      break;
     }
  }


}

