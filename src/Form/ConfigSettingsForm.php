<?php

namespace Drupal\quivers\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Avatax settings.
 */
class ConfigSettingsForm extends ConfigFormBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a ConfigSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger, ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory); 
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('module_handler')
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

    $form['configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration'),
      '#open' => TRUE,
      '#id' => 'configuration-wrapper',
    ];
    $form['configuration']['api_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Quivers Sandbox Enabled:'),
      '#default_value' => $config->get('api_mode'),
      '#options' => [
        'development' => $this->t('Yes'),
        'production' => $this->t('No'),
      ],
      '#required' => TRUE,
    ];
    $form['configuration']['aud'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AUD Marketplace ID:'),
      '#default_value' => $config->get('aud'),
      '#required' => TRUE,
    ];
    $form['configuration']['cad'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CAD Marketplace ID:'),
      '#default_value' => $config->get('cad'),
      '#required' => TRUE,
    ];
    $form['configuration']['eur'] = [
      '#type' => 'textfield',
      '#title' => $this->t('EUR Marketplace ID:'),
      '#default_value' => $config->get('eur'),
      '#required' => TRUE,
    ];
    $form['configuration']['gbp'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GBP Marketplace ID:'),
      '#default_value' => $config->get('gbp'),
      '#required' => TRUE,
    ];
    $form['configuration']['jpy'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JPY Marketplace ID:'),
      '#default_value' => $config->get('jpy'),
      '#required' => TRUE,
    ];
    $form['configuration']['usd'] = [
      '#type' => 'textfield',
      '#title' => $this->t('USD Marketplace ID:'),
      '#default_value' => $config->get('usd'),
      '#required' => TRUE,
    ];
    $form['configuration']['default_tax_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Tax Code:'),
      '#default_value' => $config->get('default_tax_code'),
      '#required' => FALSE,
    ];
    $form['configuration']['claiming_groups'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Claiming Groups:'),
      '#default_value' => $config->get('claiming_groups'),
      '#required' => TRUE,
    ];
    $form['configuration']['quivers_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Quivers API Key:'),
      '#default_value' => $config->get('quivers_api_key'),
      '#required' => TRUE,
    ];
    $form['configuration']['business_refid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Business RefId:'),
      '#default_value' => $config->get('business_refid'),
      '#required' => TRUE,
    ];
    $form['configuration']['drupal_integration_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal Integration Base URL:'),
      '#default_value' => $config->get('drupal_integration_base_url'),
      '#required' => FALSE,
    ];
    $form['configuration']['drupal_integration_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal Integration Username:'),
      '#default_value' => $config->get('drupal_integration_username'),
      '#required' => FALSE,
    ];
    $form['configuration']['drupal_integration_auth_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal Integration Auth Token:'),
      '#default_value' => $config->get('drupal_integration_auth_token'),
      '#required' => FALSE,
    ];
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback for validation.
   */
  public function validateCredentials(array &$form, FormStateInterface $form_state) {
    return $form['configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('quivers.settings')
      ->set('api_mode', $form_state->getValue('api_mode'))
      ->set('aud', $form_state->getValue('aud'))
      ->set('eur', $form_state->getValue('eur'))
      ->set('cad', $form_state->getValue('cad'))
      ->set('gbp', $form_state->getValue('gbp'))
      ->set('jpy', $form_state->getValue('jpy'))
      ->set('usd', $form_state->getValue('usd'))
      ->set('default_tax_code', $form_state->getValue('default_tax_code'))
      ->set('claiming_groups', $form_state->getValue('claiming_groups'))
      ->set('quivers_api_key', $form_state->getValue('quivers_api_key'))
      ->set('business_refid', $form_state->getValue('business_refid'))
      ->set('drupal_integration_base_url', $form_state->getValue('drupal_integration_base_url'))
      ->set('drupal_integration_username', $form_state->getValue('drupal_integration_username'))
      ->set('drupal_integration_auth_token', $form_state->getValue('drupal_integration_auth_token'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}