<?php

namespace Drupal\quivers\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Quivers Tax settings.
 */
class ConfigSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
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
      '#title' => $this->t('API mode:'),
      '#default_value' => $config->get('api_mode'),
      '#options' => [
        'development' => $this->t('Development'),
        'production' => $this->t('Production'),
      ],
      '#required' => TRUE,
      '#description' => $this->t('The mode to use when calculating taxes.'),
    ];
    $form['configuration']['aud_marketplace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AUD Marketplace ID:'),
      '#default_value' => $config->get('aud'),
      '#required' => TRUE,
    ];
    $form['configuration']['cad_marketplace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CAD Marketplace ID:'),
      '#default_value' => $config->get('cad'),
      '#required' => TRUE,
    ];
    $form['configuration']['eur_marketplace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('EUR Marketplace ID:'),
      '#default_value' => $config->get('eur'),
      '#required' => TRUE,
    ];
    $form['configuration']['gbp_marketplace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GBP Marketplace ID:'),
      '#default_value' => $config->get('gbp'),
      '#required' => TRUE,
    ];
    $form['configuration']['jpy_marketplace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JPY Marketplace ID:'),
      '#default_value' => $config->get('jpy'),
      '#required' => TRUE,
    ];
    $form['configuration']['usd_marketplace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('USD Marketplace ID:'),
      '#default_value' => $config->get('usd'),
      '#required' => TRUE,
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
      '#description' => $this->t('Quivers API Key to send to Quivers when calculating taxes.'),
    ];
    $form['configuration']['business_refid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Business RefId:'),
      '#default_value' => $config->get('business_refid'),
      '#required' => TRUE,
      '#description' => $this->t('Quivers Business Id to send to Quivers when calculating taxes.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback for validation.
   *
   * @param array $form
   *   Array of configuration form.
   *
   * @return mixed
   *   Return configuration Form.
   */
  public function validateCredentials(array &$form) {
    return $form['configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('quivers.settings')
      ->set('api_mode', $form_state->getValue('api_mode'))
      ->set('aud_marketplace', $form_state->getValue('aud_marketplace'))
      ->set('eur_marketplace', $form_state->getValue('eur_marketplace'))
      ->set('cad_marketplace', $form_state->getValue('cad_marketplace'))
      ->set('gbp_marketplace', $form_state->getValue('gbp_marketplace'))
      ->set('jpy_marketplace', $form_state->getValue('jpy_marketplace'))
      ->set('usd_marketplace', $form_state->getValue('usd_marketplace'))
      ->set('claiming_groups', $form_state->getValue('claiming_groups'))
      ->set('quivers_api_key', $form_state->getValue('quivers_api_key'))
      ->set('business_refid', $form_state->getValue('business_refid'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
