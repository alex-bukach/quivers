<?php

namespace Drupal\quivers\Form;

use Drupal\quivers\QuiversMiddlewareService;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Quivers Tax settings.
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
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a ConfigSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\quivers\Form\QuiversMiddlewareService $quivers_middleware_service
   *   The Quivers Middleware Service.
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   Entity Manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger, QuiversMiddlewareService $quivers_middleware_service, EntityManager $entity_manager) {
    parent::__construct($config_factory);
    $this->messenger = $messenger;
    $this->quiversMiddlewareService = $quivers_middleware_service;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('quivers.quivers_middleware_service'),
      $container->get('entity.manager')
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
    $marketplaces = self::loadMarketplaces($config);

    $headers = [
      'store_label' => $this->t('Store Label'),
      'store_id' => $this->t('Store Id'),
      'quivers_marketplace_id' => $this->t('Quivers Marketplace Id'),
      'quivers_claiming_group_ids' => $this->t('Quivers Claiming Group Ids'),
    ];

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
    $form['profile_configuration']['business_refid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Business RefId:'),
      '#default_value' => $config->get('business_refid'),
      '#required' => TRUE,
      '#description' => $this->t('Quivers profile Business RefId.'),
    ];
    $form['profile_configuration']['quivers_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key:'),
      '#default_value' => $config->get('quivers_api_key'),
      '#required' => TRUE,
      '#description' => $this->t('Quivers profile API Key.'),
    ];
    $form['profile_configuration']['drupal_api_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal API Base URL:'),
      '#default_value' => $config->get('drupal_api_base_url'),
      '#required' => TRUE,
      '#description' => $this->t('Drupal JSON API base URL e.g. https://sandbox.something.com/'),
    ];

    // TAX CONFIGURATION.
    $form['tax_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Tax Configuration'),
      '#open' => TRUE,
      '#id' => 'tax-configuration-wrapper',
    ];
    $form['tax_configuration']['marketplaces'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No Stores found'),
    ];
    foreach ($marketplaces as $key => $marketplace) {
      $form['tax_configuration']['marketplaces'][$key]['store_label'] = [
        '#type' => 'label',
        '#title' => $marketplace['store_label'],
      ];
      $form['tax_configuration']['marketplaces'][$key]['store_id'] = [
        '#type' => 'textfield',
        '#value' => $marketplace['store_id'],
        '#attributes' => ['readonly' => 'readonly'],
        '#title_display' => 'invisible',
        '#size' => 'auto',
      ];
      $form['tax_configuration']['marketplaces'][$key]['quivers_marketplace_id'] = [
        '#type' => 'textfield',
        '#title' => $this
          ->t('Quivers Marketplace Id'),
        '#title_display' => 'invisible',
        '#size' => 'auto',
        '#default_value' => $marketplace['quivers_marketplace_id'],
        '#placeholder' => $this->t('Quivers Marketplace Id'),
      ];
      $form['tax_configuration']['marketplaces'][$key]['quivers_claiming_group_ids'] = [
        '#type' => 'textfield',
        '#title' => $this
          ->t('Quivers Claiming Group'),
        '#title_display' => 'invisible',
        '#size' => 'auto',
        '#default_value' => $marketplace['quivers_claiming_group_ids'],
        '#placeholder' => $this->t('Comma seperated Ids'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();
    $marketplaces = $values['marketplaces'];
    if (empty($marketplaces)) {
      return;
    }
    $form_error = FALSE;
    foreach ($marketplaces as $key => $marketplace) {
      // Claiming Groups are entered without Marketplace Id.
      if (
        ($marketplace['quivers_claiming_group_ids'] && !$marketplace['quivers_marketplace_id']) ||
        (!$marketplace['quivers_claiming_group_ids'] && $marketplace['quivers_marketplace_id'])
      ) {
        $form_state->setError($form['tax_configuration']['marketplaces'][$key], $this->t('Quivers Marketplace ID can not be empty.'));
        $form_error = TRUE;
      }
    }
    if ($form_error) {
      return;
    }

    try {
      $this->quiversMiddlewareService->profileUpdate($values);
    }
    catch (\Exception $e) {
      $form_state->setError(
        $form['profile_configuration'], 'Unable to sync Quivers Profile - ' . $e->getMessage());
      return;
    }
    $this->messenger->addMessage($this->t('Quivers Profile Synced successfully.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('quivers.settings')
      ->set('api_mode', $form_state->getValue('api_mode'))
      ->set('business_refid', $form_state->getValue('business_refid'))
      ->set('quivers_api_key', $form_state->getValue('quivers_api_key'))
      ->set('drupal_api_base_url', $form_state->getValue('drupal_api_base_url'))
      ->set('marketplaces', $form_state->getValue('marketplaces'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Load Marketplaces with Quivers Configuration.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The Quivers Settings Configuration.
   *
   * @return array
   *   The Store Configuration array.
   */
  private function loadMarketplaces(Config $config) {
    $saved_marketplace_store_mappings = [];
    $saved_marketplaces = $config->get('marketplaces');
    if (!$config->get('marketplaces')) {
      $saved_marketplaces = [];
    }

    foreach ($saved_marketplaces as $marketplace) {
      $saved_marketplace_store_mappings[$marketplace['store_id']] = [
        'quivers_marketplace_id' => $marketplace['quivers_marketplace_id'],
        'quivers_claiming_group_ids' => $marketplace['quivers_claiming_group_ids'],
      ];
    }

    $marketplaces = [];
    $stores = $this->entityManager->getStorage('commerce_store')->loadMultiple();

    foreach ($stores as $key => $store) {
      $marketplaces[$key] = [
        'store_label' => $store->label(),
        'store_id' => $store->uuid(),
        'quivers_marketplace_id' => isset($saved_marketplace_store_mappings[$store->uuid()]) ? $saved_marketplace_store_mappings[$store->uuid()]['quivers_marketplace_id'] : "",
        'quivers_claiming_group_ids' => isset($saved_marketplace_store_mappings[$store->uuid()]) ? $saved_marketplace_store_mappings[$store->uuid()]['quivers_claiming_group_ids'] : "",
      ];
    }
    return $marketplaces;
  }

}
