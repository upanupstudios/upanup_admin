<?php

namespace Drupal\upanup_admin\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define class for admin settings.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Messenger service.
   *
   * @var Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The request stack instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructs a new AdminSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory interface.
   * @param \Drupal\Core\Messenger\MessengerInterface
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('upanup_admin');
    $this->messenger = $messenger;

    // Get settings.
    $this->settings = $this->configFactory->get('upanup_admin.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'upanup_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['upanup_admin.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['redirect_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable admin redirect'),
      '#default_value' => $this->settings->get('redirect_enabled')
    ];

    // Server details
    $form['redirect'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => TRUE
    ];
    $form['redirect']['admin_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Admin Method'),
      '#options' => [
        'upanup_admin' => $this->t('Upanup Admin (name.admin.upanup.com)'),
        'admin_domain' => $this->t('Admin Domain (admin.domain.com)'),
      ],
      '#default_value' => $this->settings->get('admin_method') ?: 'upanup_admin',
      '#required' => TRUE,
    ];
    $form['redirect']['admin_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Admin Name'),
      '#default_value' => $this->settings->get('admin_name'),
      '#states' => [
        'visible' => [
          ':input[name="admin_method"]' => ['value' => 'upanup_admin'],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $admin_method = $form_state->getValue('admin_method');
    $admin_name = $form_state->getValue('admin_name');

    if ($admin_method == 'upanup_admin') {
      if (!preg_match('/^[a-z0-9]+$/i', $admin_name)) {
        $form_state->setErrorByName('subdomain', $this->t('Admin name may only contain alphanumeric characters.'));
      }
    }

    //TODO: Validate if the domain is reachable.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $this->config('upanup_admin.settings');

    // Save configuration.
    $redirect_enabled = $form_state->getValue('redirect_enabled');
    $admin_method = $form_state->getValue('admin_method');
    $admin_name = $form_state->getValue('admin_name');

    $this->config('upanup_admin.settings')
      ->set('redirect_enabled', $redirect_enabled)
      ->set('admin_method', $admin_method)
      ->set('admin_name', $admin_name)
      ->save();

    return parent::submitForm($form, $form_state);
  }

}
