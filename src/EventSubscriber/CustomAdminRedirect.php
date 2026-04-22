<?php

namespace Drupal\upanup_admin\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for redirecting between www and admin domains.
 */
class CustomAdminRedirect implements EventSubscriberInterface {

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $settings;

  /**
   * Constructs a CustomAdminRedirect.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RouteMatchInterface $route_match, AccountInterface $account, ModuleHandlerInterface $module_handler) {
    $this->configFactory = $config_factory;
    $this->routeMatch = $route_match;
    $this->account = $account;
    $this->moduleHandler = $module_handler;

    // Get settings.
    $this->settings = $this->configFactory->get('upanup_admin.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // The number 30 is the priority.
    // This is set at 30 so that it runs before page caching (priority 27).
    $events[KernelEvents::REQUEST][] = ['onRequest', 30];
    return $events;
  }

  /**
   * Handle redirection from www to admin when logging in and from admin to www
   * when logging out.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request.
   */
  public function onRequest(RequestEvent $event) {
    $redirect_enabled = $this->settings->get('redirect_enabled');

    if (!empty($redirect_enabled)) {
      $request = $event->getRequest();
      $scheme = $request->getScheme();
      $host = $request->getHost();
      $uri = $request->getRequestUri();

      $route_name = $this->routeMatch->getRouteName();

      // Check if modules are installed/enabled.
      $upanup_auth_exists = $this->moduleHandler->moduleExists('upanup_auth');
      $samlauth_exists = $this->moduleHandler->moduleExists('samlauth');
      // TODO: Need to check for disable login module?
      // $disable_login_exists = $this->moduleHandler->moduleExists('disable_login');

      // Login and logout routes.
      // TODO: Add password reset?
      $redirect_routes = [
        'user.login',
        'user.logout',
        'user.pass',
        'user.reset.login',
        'system.files',
        'system.private_file_download',
      ];

      if ($upanup_auth_exists) {
        $redirect_routes[] = 'upanup_auth.saml_controller_login';
        $redirect_routes[] = 'upanup_auth.saml_controller_acs';
        $redirect_routes[] = 'upanup_auth.saml_controller_logout';
      }

      if ($samlauth_exists) {
        $redirect_routes[] = 'samlauth.saml_controller_login';
        $redirect_routes[] = 'samlauth.saml_controller_acs';
        $redirect_routes[] = 'samlauth.saml_controller_logout';
      }

      $admin_method = $this->settings->get('admin_method');
      $admin_name = $this->settings->get('admin_name');
      $admin_custom = $this->settings->get('admin_custom') ?: 'admin';

      if ($admin_method === 'admin_subdomain') {
        $admin_host_pattern = '/^' . preg_quote($admin_name, '/') . '\.' . preg_quote($admin_custom, '/') . '\./';
        $is_admin_host = (bool) preg_match($admin_host_pattern, $host);

        if ($this->account->isAnonymous()) {
          // Redirect subdomain to admin if on login/logout routes.
          if (!$is_admin_host && in_array($route_name, $redirect_routes)) {
            $host = preg_replace('/' . preg_quote($admin_name, '/') .'\./', $admin_name . '.' . $admin_custom . '.', $host);
            $url = $scheme . '://' . $host . $uri;
            $response = new TrustedRedirectResponse($url);
            $event->setResponse($response);
          }
          // Redirect admin to subdomain if not on login/logout routes.
          elseif ($is_admin_host && !in_array($route_name, $redirect_routes)) {
            $host = preg_replace('/' . preg_quote($admin_name, '/') . '\.' . preg_quote($admin_custom, '/') . '\./', $admin_name . '.', $host);
            $url = 'https://' . $host . $uri;
            $response = new TrustedRedirectResponse($url);
            $event->setResponse($response);
          }
        }
      }
      elseif ($admin_method === 'admin_domain') {
        // WWW host pattern.
        $www_host_pattern = '/^www\./';
        $admin_host_pattern = '/(^' . preg_quote($admin_custom, '/') . '\.)/';
        $is_admin_host = (bool) preg_match($admin_host_pattern, $host);

        if ($this->account->isAnonymous()) {
          // Redirect www to admin if on login/logout routes.
          if (!$is_admin_host && in_array($route_name, $redirect_routes)) {
            $host = preg_replace($www_host_pattern, $admin_custom . '.', $host);
            $url = $scheme . '://' . $host . $uri;
            $response = new TrustedRedirectResponse($url);
            $event->setResponse($response);
          }
          // Redirect admin to www if not on login/logout routes.
          elseif ($is_admin_host && !in_array($route_name, $redirect_routes)) {
            $host = preg_replace('/' . preg_quote($admin_custom, '/') . '\./', 'www.', $host);
            $url = $scheme . '://' . $host . $uri;
            $response = new TrustedRedirectResponse($url);
            $event->setResponse($response);
          }
        }
      }
      else {
        // Upanup Admin method, redirect to admin subdomain with name prefix.
        // $admin_host_pattern = '/(admin\.upanup\.com)$/';
        // TODO: Need to test.
      }
    }
  }

}
