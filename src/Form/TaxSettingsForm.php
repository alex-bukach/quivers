<?php

namespace Drupal\quivers\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\quivers\QuiversMiddlewareService;
use Drupal\Core\Url;

/**
 * Configuration form for Quivers Tax settings.
 */
class TaxSettingsForm extends ConfigFormBase {

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
    return 'quivers_tax_config_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['quivers.tax_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $quivers_tax_config = $this->config('quivers.tax_settings');
    $quivers_config = $this->config('quivers.settings');
    $marketplaces = self::loadMarketplaces($quivers_tax_config);
    $sync_error = $this->quiversMiddlewareService->verifyProfileStatus($quivers_config->get(), TRUE);
    if ($sync_error) {
      $this->messenger->addMessage($sync_error, "SYNC_STATUS");
    }
    else {
     $this->messenger->deleteByType("SYNC_STATUS");
    }

    if (empty($marketplaces)) {
      $url = Url::fromRoute('quivers.config_settings');
      $form['update_settings']['#markup'] = 'Submit you Quivers profile first at - <p>Manage > Administration > Commerce > Configuration > ' . $this->l($this->t('Quivers Settings'), $url) . '</p>';
      return $form;
    }

    $form['tax_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Tax Configuration'),
      '#open' => TRUE,
      '#id' => 'tax-configuration-wrapper',
    ];

    $headers = [
      'store_label' => $this->t('Store Label'),
      'store_id' => $this->t('Store Id'),
      'quivers_marketplace_id' => $this->t('Quivers Marketplace Id'),
      'quivers_claiming_group_ids' => $this->t('Quivers Claiming Group Ids'),
    ];

    $form['tax_configuration']['marketplaces'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#empty' => $this->t('No Stores found'),
    ];
 
    foreach ($marketplaces as $key => $marketplace) {

      if (!isset($marketplace['store_id'])) {
        continue;
      }

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
        '#type' => 'select',
        '#title' => $this->t('Quivers Marketplace Id'),
        '#options' => $marketplaces['quivers_marketplaces'],
        '#title_display' => 'invisible',
        '#size' => 'auto',
        '#value' => $marketplace['quivers_marketplace_id'],
        '#placeholder' => $this->t('Quivers Marketplace Id'),
        '#empty_option' => $this->t('- Select a Marketplace -'),
        '#empty_value' => '',
      ];

      $form['tax_configuration']['marketplaces'][$key]['quivers_claiming_group_ids'] = [
        '#type' => 'select',
        '#title' => $this->t('Quivers Claiming Group'),
        '#options' => $marketplaces['quivers_claiming_groups'],
        '#title_display' => 'invisible',
        '#size' => 'auto',
        '#value' =>$marketplace['quivers_claiming_group_ids'],
        '#multiple' => 'true',
        '#attributes' => array('data-claiming' => 'True'),

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

    if (!isset($values['marketplaces'])) {
      return;
    }
    $marketplaces = $values['marketplaces'];
    $form_error = FALSE;
    $sync_flag = TRUE;

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
      \Drupal::configFactory()->getEditable('quivers.settings')
      ->set('status', 'Active')
      ->save();
    }
    catch (\Exception $e) {
      \Drupal::configFactory()->getEditable('quivers.settings')
      ->set('status', 'Inactive')
      ->save();
      $this->messenger->addError("Failed to conect to Quivers. Please check if the settings in Quivers and Quivers tax Tabs are saved correctly. If the issue still persists,please contact 'enterprise@quivers.com' for further assistance.");
      $sync_flag = FALSE;
    }
    if ($sync_flag) {
      $this->messenger->addMessage($this->t('Quivers Profile Synced successfully.'));
      $url =str_replace("quivers-tax","quivers",\Drupal::request()->headers->get('referer'));
      header("LOCATION: ".$url);
      exit;
    }

  }

  /**
   * Load Marketplaces with Quivers Configuration.
   *
   * @param \Drupal\Core\Config\Config $quivers_tax_config
   *   The Quivers Tax Settings Configuration.
   *
   * @return array
   *   The Store Configuration array.
   */
  private function loadMarketplaces(Config $quivers_tax_config) {
    $marketplaces = [];
    $saved_marketplace_store_mappings = [];

    // Get Saved Marketplaces Mapping.
    $saved_marketplaces = $quivers_tax_config->get('marketplaces');

    if ($saved_marketplaces === NULL) {
      $saved_marketplaces = [];
    }

    // Get Quivers Ids from Quivers Settings.
    $quivers_config = $this->config('quivers.settings');
    if (!$quivers_config->get('quivers_marketplaces')) {
      return $marketplaces;
    }
   
    $marketplaces['quivers_marketplaces'] = $quivers_config->get('quivers_marketplaces');
    $marketplaces['quivers_claiming_groups'] = $quivers_config->get('quivers_claiming_groups');

    $saved_marketplaces =is_string($saved_marketplaces) == true ? json_decode($saved_marketplaces) : $saved_marketplaces;
    foreach ($saved_marketplaces as $marketplace) { 
      $marketplace =  is_object($marketplace)?json_decode(json_encode($marketplace), true):$marketplace;
        $saved_marketplace_store_mappings[$marketplace['store_id']] = [
          'quivers_marketplace_id' => $marketplace['quivers_marketplace_id'],
          'quivers_claiming_group_ids' => $marketplace['quivers_claiming_group_ids'],
        ];     
    }

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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('quivers.tax_settings')
      ->set('marketplaces', json_encode($form_state->getValue('marketplaces')))
      ->save();
      
    parent::submitForm($form, $form_state);

  }

}
