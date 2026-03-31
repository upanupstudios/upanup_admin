<?php

namespace Drupal\upanup_admin\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a settings form for Upanup Admin.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

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
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    parent::__construct($config_factory);

    $this->logger = $logger_factory->get('upanup_admin');
    $this->cacheTagsInvalidator = $cache_tags_invalidator;

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
      $container->get('cache_tags.invalidator'),
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
      '#title' => $this->t('Enable redirect'),
      '#default_value' => $this->settings->get('redirect_enabled'),
    ];

    // Redirect settings.
    $form['redirect'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => TRUE,
    ];
    $form['redirect']['admin_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Method'),
      '#options' => [
        // Use the same domain with a path prefix.
        'upanup_admin' => $this->t('Upanup Admin (name.admin.upanup.com)'),
        'admin_domain' => $this->t('Admin Domain (admin.domain.com)'),
        'admin_subdomain' => $this->t('Admin Subdomain (name.admin.domain.com)'),
      ],
      '#default_value' => $this->settings->get('admin_method') ?: 'upanup_admin',
      '#required' => TRUE,
    ];
    $form['redirect']['admin_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->settings->get('admin_name'),
      '#states' => [
        'visible' => [
          [':input[name="admin_method"]' => ['value' => 'upanup_admin']],
          'or',
          [':input[name="admin_method"]' => ['value' => 'admin_subdomain']],
        ],
      ],
    ];
    $form['redirect']['admin_custom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Admin Name'),
      '#description' => $this->t('Enter a custom admin name, leave blank to use admin.domain.com'),
      '#default_value' => $this->settings->get('admin_custom'),
      '#states' => [
        'visible' => [
          [':input[name="admin_method"]' => ['value' => 'admin_domain']],
          'or',
          [':input[name="admin_method"]' => ['value' => 'admin_subdomain']],
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
    $admin_custom = $form_state->getValue('admin_custom');

    if ($admin_method === 'upanup_admin' || $admin_method === 'admin_subdomain') {
      if (empty($admin_name)) {
        $form_state->setErrorByName('admin_name', $this->t('Admin name is required.'));
      }
      elseif (!preg_match('/^[a-z0-9]+$/i', $admin_name)) {
        $form_state->setErrorByName('admin_name', $this->t('Admin name may only contain alphanumeric characters.'));
      }
    }

    if ($admin_method === 'admin_domain' || $admin_method === 'admin_subdomain') {
      if (!empty($admin_custom) && !preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/i', $admin_custom)) {
        $form_state->setErrorByName('admin_custom', $this->t('Custom admin name may only contain alphanumeric characters and hyphens.'));
      }
    }

    // TODO: Validate if the domain is reachable.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save configuration.
    $redirect_enabled = $form_state->getValue('redirect_enabled');
    $admin_method = $form_state->getValue('admin_method');
    $admin_name = $form_state->getValue('admin_name');
    $admin_custom = $form_state->getValue('admin_custom');

    $this->config('upanup_admin.settings')
      ->set('redirect_enabled', $redirect_enabled)
      ->set('admin_method', $admin_method)
      ->set('admin_name', $admin_name)
      ->set('admin_custom', $admin_custom)
      ->save();

    $this->logger->info('Upanup Admin settings updated by @user.', [
      '@user' => $this->currentUser()->getAccountName(),
    ]);

    // Clear cache or trigger an event to apply the new settings immediately.
    $this->cacheTagsInvalidator->invalidateTags(['rendered']);

    // Add message to inform the user that settings have been saved and cache cleared.
    $message = $this->t('Upanup Admin settings have been saved. All rendered cache cleared.');
    $this->messenger()->addStatus($message);

    parent::submitForm($form, $form_state);
  }

}
