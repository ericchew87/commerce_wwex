<?php


namespace Drupal\commerce_wwex\Plugin\Commerce\ShippingMethod;


use Drupal\commerce_packaging\ChainShipmentPackagerInterface;
use Drupal\commerce_packaging\ShippingMethodPackagingTrait;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class WWEXBase extends ShippingMethodBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The shipment packager.
   *
   * @var \Drupal\commerce_packaging\ChainShipmentPackagerInterface
   */
  protected $shipmentPackager;

  /**
   * Constructs a new WWEXBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_packaging\ChainShipmentPackagerInterface $shipment_packager
   *   The shipment packager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager, EntityTypeManagerInterface $entity_type_manager, ChainShipmentPackagerInterface $shipment_packager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager);

    $this->entityTypeManager = $entity_type_manager;
    $this->shipmentPackager = $shipment_packager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('plugin.manager.workflow'),
      $container->get('entity_type.manager'),
      $container->get('commerce_packaging.chain_shipment_packager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'api_information' => [
          'user_name' => '',
          'password' => '',
          'auth_key' => '',
          'account_number' => '',
          'mode' => 'test',
        ],
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Select all services by default.
    if (empty($this->configuration['services'])) {
      $service_ids = array_keys($this->services);
      $this->configuration['services'] = array_combine($service_ids, $service_ids);
    }

    $description = $this->t('Update your @label API information', ['@label' => $this->getLabel()]);
    if (!$this->isConfigured()) {
      $description = $this->t('Fill in your @label API information.', ['@label' => $this->getLabel()]);
    }
    $form['api_information'] = [
      '#type' => 'details',
      '#title' => $this->t('API information'),
      '#description' => $description,
      '#weight' => $this->isConfigured() ? 10 : -10,
      '#open' => !$this->isConfigured(),
    ];

    $form['api_information']['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Name'),
      '#default_value' => $this->configuration['api_information']['user_name'],
      '#required' => TRUE,
    ];

    $form['api_information']['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $this->configuration['api_information']['password'],
      '#required' => TRUE,
    ];

    $form['api_information']['auth_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authentication Key'),
      '#default_value' => $this->configuration['api_information']['auth_key'],
      '#required' => TRUE,
    ];

    $form['api_information']['account_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account Number'),
      '#default_value' => $this->configuration['api_information']['account_number'],
      '#required' => TRUE,
    ];

    $form['api_information']['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#description' => $this->t('Choose whether to use the test or live mode.'),
      '#options' => [
        'test' => $this->t('Test'),
        'live' => $this->t('Live'),
      ],
      '#default_value' => $this->configuration['api_information']['mode'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration['api_information']['user_name'] = $values['api_information']['user_name'];
      $this->configuration['api_information']['password'] = $values['api_information']['password'];
      $this->configuration['api_information']['auth_key'] = $values['api_information']['auth_key'];
      $this->configuration['api_information']['account_number'] = $values['api_information']['account_number'];

      $this->configuration['api_information']['mode'] = $values['api_information']['mode'];
    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Determine if we have the minimum information to connect to UPS.
   *
   * @return bool
   *   TRUE if there is enough information to connect, FALSE otherwise.
   */
  protected function isConfigured() {
    $api_config = &$this->configuration['api_information'];

    if (empty($api_config['user_name']) || empty($api_config['auth_key']) || empty($api_config['password'] || empty($api_config['account_number']))) {
      return FALSE;
    }

    return TRUE;
  }

}
