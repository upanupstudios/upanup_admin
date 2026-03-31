<?php

declare(strict_types=1);

namespace Drupal\Tests\upanup_admin\Unit\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\upanup_admin\EventSubscriber\CustomAdminRedirect;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @coversDefaultClass \Drupal\upanup_admin\EventSubscriber\CustomAdminRedirect
 * @group upanup_admin
 */
class CustomAdminRedirectTest extends UnitTestCase {

  protected ConfigFactoryInterface&MockObject $configFactory;

  protected ImmutableConfig&MockObject $settings;

  protected RouteMatchInterface&MockObject $routeMatch;

  protected AccountInterface&MockObject $account;

  protected ModuleHandlerInterface&MockObject $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->settings = $this->createMock(ImmutableConfig::class);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('upanup_admin.settings')
      ->willReturn($this->settings);

    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    $this->account = $this->createMock(AccountInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
  }

  protected function createSubscriber(): CustomAdminRedirect {
    return new CustomAdminRedirect(
      $this->configFactory,
      $this->routeMatch,
      $this->account,
      $this->moduleHandler
    );
  }

  protected function createEvent(string $url): RequestEvent {
    $request = Request::create($url);
    $kernel = $this->createMock(HttpKernelInterface::class);
    return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
  }

  /**
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEvents(): void {
    $events = CustomAdminRedirect::getSubscribedEvents();
    $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    $this->assertContains(['onRequest', 30], $events[KernelEvents::REQUEST]);
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestRedirectDisabledDoesNothing(): void {
    $this->settings->method('get')
      ->willReturnMap([['redirect_enabled', FALSE]]);

    $event = $this->createEvent('https://www.example.com/user/login');
    $this->createSubscriber()->onRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestAdminDomainAnonymousOnLoginRouteRedirectsToAdmin(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('user.login');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://www.example.com/user/login');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $location = $event->getResponse()->headers->get('Location');
    $this->assertStringContainsString('admin.example.com', $location);
    $this->assertStringNotContainsString('www.example.com', $location);
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestAdminDomainAnonymousOnLogoutRouteRedirectsToAdmin(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('user.logout');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://www.example.com/user/logout');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $this->assertStringContainsString('admin.example.com', $event->getResponse()->headers->get('Location'));
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestAdminDomainAnonymousOnNonLoginRouteOnAdminHostRedirectsToWww(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('entity.node.canonical');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://admin.example.com/node/1');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $location = $event->getResponse()->headers->get('Location');
    $this->assertStringContainsString('www.example.com', $location);
    $this->assertStringNotContainsString('admin.example.com', $location);
  }

  /**
   * @covers ::onRequest
   *
   * Already on admin host and accessing a login route - no redirect needed.
   */
  public function testOnRequestAdminDomainAlreadyOnAdminHostForLoginRouteNoRedirect(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('user.login');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://admin.example.com/user/login');
    $this->createSubscriber()->onRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onRequest
   *
   * On www host browsing non-login routes - no redirect needed.
   */
  public function testOnRequestAdminDomainOnWwwHostForNonLoginRouteNoRedirect(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('entity.node.canonical');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://www.example.com/node/1');
    $this->createSubscriber()->onRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onRequest
   *
   * Authenticated users are never redirected.
   */
  public function testOnRequestAuthenticatedUserNoRedirect(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(FALSE);
    $this->routeMatch->method('getRouteName')->willReturn('user.login');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://www.example.com/user/login');
    $this->createSubscriber()->onRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestAdminSubdomainAnonymousOnLoginRouteRedirectsToAdminSubdomain(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_subdomain'],
        ['admin_name', 'mysite'],
        ['admin_custom', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('user.login');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://mysite.example.com/user/login');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $location = $event->getResponse()->headers->get('Location');
    $this->assertStringContainsString('mysite.admin.example.com', $location);
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestAdminSubdomainAnonymousOnNonLoginRouteOnAdminSubdomainRedirects(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_subdomain'],
        ['admin_name', 'mysite'],
        ['admin_custom', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('entity.node.canonical');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://mysite.admin.example.com/node/1');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $location = $event->getResponse()->headers->get('Location');
    $this->assertStringContainsString('mysite.example.com', $location);
    $this->assertStringNotContainsString('mysite.admin.example.com', $location);
  }

  /**
   * @covers ::onRequest
   *
   * Already on the regular subdomain for a non-login route - no redirect needed.
   */
  public function testOnRequestAdminSubdomainOnRegularSubdomainForNonLoginRouteNoRedirect(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_subdomain'],
        ['admin_name', 'mysite'],
        ['admin_custom', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('entity.node.canonical');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://mysite.example.com/node/1');
    $this->createSubscriber()->onRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onRequest
   *
   * Custom admin_custom segment is used in the admin subdomain redirect.
   */
  public function testOnRequestAdminSubdomainWithCustomSegmentRedirectsToCustomAdminSubdomain(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_subdomain'],
        ['admin_name', 'mysite'],
        ['admin_custom', 'myadmin'],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('user.login');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://mysite.example.com/user/login');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $location = $event->getResponse()->headers->get('Location');
    $this->assertStringContainsString('mysite.myadmin.example.com', $location);
    $this->assertStringNotContainsString('mysite.admin.example.com', $location);
  }

  /**
   * @covers ::onRequest
   *
   * Reverse redirect from custom admin subdomain back to the regular subdomain.
   */
  public function testOnRequestAdminSubdomainWithCustomSegmentReverseRedirect(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_subdomain'],
        ['admin_name', 'mysite'],
        ['admin_custom', 'myadmin'],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('entity.node.canonical');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://mysite.myadmin.example.com/node/1');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $location = $event->getResponse()->headers->get('Location');
    $this->assertStringContainsString('mysite.example.com', $location);
    $this->assertStringNotContainsString('mysite.myadmin.example.com', $location);
  }

  /**
   * @covers ::onRequest
   *
   * Custom admin_custom segment is used in the admin domain redirect.
   */
  public function testOnRequestAdminDomainWithCustomSegmentRedirectsToCustomAdmin(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
        ['admin_custom', 'myadmin'],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('user.login');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://www.example.com/user/login');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $location = $event->getResponse()->headers->get('Location');
    $this->assertStringContainsString('myadmin.example.com', $location);
    $this->assertStringNotContainsString('www.example.com', $location);
  }

  /**
   * @covers ::onRequest
   *
   * Reverse redirect from custom admin domain back to www.
   */
  public function testOnRequestAdminDomainWithCustomSegmentReverseRedirectToWww(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
        ['admin_custom', 'myadmin'],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('entity.node.canonical');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://myadmin.example.com/node/1');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $location = $event->getResponse()->headers->get('Location');
    $this->assertStringContainsString('www.example.com', $location);
    $this->assertStringNotContainsString('myadmin.example.com', $location);
  }

  /**
   * @covers ::onRequest
   *
   * upanup_auth module routes are added to the redirect routes list.
   */
  public function testOnRequestWithUpanupAuthModuleAddsRoutes(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('upanup_auth.saml_controller_login');
    $this->moduleHandler->method('moduleExists')
      ->willReturnMap([
        ['upanup_auth', TRUE],
        ['samlauth', FALSE],
      ]);

    $event = $this->createEvent('https://www.example.com/saml/login');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $this->assertStringContainsString('admin.example.com', $event->getResponse()->headers->get('Location'));
  }

  /**
   * @covers ::onRequest
   *
   * samlauth module routes are added to the redirect routes list.
   */
  public function testOnRequestWithSamlauthModuleAddsRoutes(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('samlauth.saml_controller_login');
    $this->moduleHandler->method('moduleExists')
      ->willReturnMap([
        ['upanup_auth', FALSE],
        ['samlauth', TRUE],
      ]);

    $event = $this->createEvent('https://www.example.com/saml/login');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $this->assertStringContainsString('admin.example.com', $event->getResponse()->headers->get('Location'));
  }

  /**
   * @covers ::onRequest
   *
   * upanup_auth module ACS route is treated as a redirect route.
   */
  public function testOnRequestUpanupAuthAcsRouteIsRedirectRoute(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('upanup_auth.saml_controller_acs');
    $this->moduleHandler->method('moduleExists')
      ->willReturnMap([
        ['upanup_auth', TRUE],
        ['samlauth', FALSE],
      ]);

    $event = $this->createEvent('https://www.example.com/saml/acs');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
  }

  /**
   * @covers ::onRequest
   *
   * Request URI is preserved in the redirect URL.
   */
  public function testOnRequestPreservesRequestUri(): void {
    $this->settings->method('get')
      ->willReturnMap([
        ['redirect_enabled', TRUE],
        ['admin_method', 'admin_domain'],
        ['admin_name', NULL],
      ]);
    $this->account->method('isAnonymous')->willReturn(TRUE);
    $this->routeMatch->method('getRouteName')->willReturn('user.login');
    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $event = $this->createEvent('https://www.example.com/user/login?destination=/node/1');
    $this->createSubscriber()->onRequest($event);

    $this->assertTrue($event->hasResponse());
    $location = $event->getResponse()->headers->get('Location');
    $this->assertStringContainsString('/user/login', $location);
    $this->assertStringContainsString('destination=', $location);
  }

}
