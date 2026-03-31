<?php

declare(strict_types=1);

namespace Drupal\Tests\upanup_admin\Unit\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\upanup_admin\Form\AdminSettingsForm;

/**
 * @coversDefaultClass \Drupal\upanup_admin\Form\AdminSettingsForm
 * @group upanup_admin
 */
class AdminSettingsFormTest extends UnitTestCase {

  protected AdminSettingsForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturn(NULL);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($settings);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $cacheTagsInvalidator = $this->createMock(CacheTagsInvalidatorInterface::class);

    $this->form = new AdminSettingsForm($configFactory, $loggerFactory, $cacheTagsInvalidator);
    $this->form->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $this->assertSame('upanup_admin_settings', $this->form->getFormId());
  }

  /**
   * @covers ::getEditableConfigNames
   */
  public function testGetEditableConfigNames(): void {
    $method = new \ReflectionMethod($this->form, 'getEditableConfigNames');
    $method->setAccessible(TRUE);
    $this->assertSame(['upanup_admin.settings'], $method->invoke($this->form));
  }

  /**
   * @covers ::validateForm
   *
   * admin_name is required when method is upanup_admin.
   */
  public function testValidateFormUpanupAdminMethodRequiresName(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'upanup_admin', 'admin_name' => '']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('admin_name', $errors);
  }

  /**
   * @covers ::validateForm
   *
   * admin_name is required when method is admin_subdomain.
   */
  public function testValidateFormAdminSubdomainMethodRequiresName(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_subdomain', 'admin_name' => '']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('admin_name', $errors);
  }

  /**
   * @covers ::validateForm
   *
   * admin_name is NOT required when method is admin_domain.
   */
  public function testValidateFormAdminDomainMethodDoesNotRequireName(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_domain', 'admin_name' => '']);

    $this->form->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   *
   * admin_name must be alphanumeric.
   */
  public function testValidateFormNameWithHyphenIsInvalid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_subdomain', 'admin_name' => 'my-site']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('admin_name', $errors);
  }

  /**
   * @covers ::validateForm
   *
   * admin_name must be alphanumeric.
   */
  public function testValidateFormNameWithSpecialCharactersIsInvalid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_subdomain', 'admin_name' => 'site!name']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('admin_name', $errors);
  }

  /**
   * @covers ::validateForm
   *
   * Lowercase alphanumeric names are valid.
   */
  public function testValidateFormLowercaseAlphanumericNameIsValid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_subdomain', 'admin_name' => 'mysite123']);

    $this->form->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   *
   * Mixed-case names are valid (pattern uses case-insensitive flag).
   */
  public function testValidateFormMixedCaseAlphanumericNameIsValid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'upanup_admin', 'admin_name' => 'MySite123']);

    $this->form->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   *
   * Whitespace in admin_name is invalid.
   */
  public function testValidateFormNameWithSpaceIsInvalid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_subdomain', 'admin_name' => 'my site']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('admin_name', $errors);
  }

  /**
   * @covers ::validateForm
   *
   * admin_custom is optional — leaving it blank is valid.
   */
  public function testValidateFormEmptyAdminCustomIsValid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_domain', 'admin_name' => '', 'admin_custom' => '']);

    $this->form->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   *
   * admin_custom with alphanumeric characters is valid.
   */
  public function testValidateFormAlphanumericAdminCustomIsValid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_domain', 'admin_name' => '', 'admin_custom' => 'myadmin']);

    $this->form->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   *
   * admin_custom with an internal hyphen is valid.
   */
  public function testValidateFormAdminCustomWithInternalHyphenIsValid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_domain', 'admin_name' => '', 'admin_custom' => 'my-admin']);

    $this->form->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   *
   * admin_custom with a leading hyphen is invalid.
   */
  public function testValidateFormAdminCustomWithLeadingHyphenIsInvalid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_domain', 'admin_name' => '', 'admin_custom' => '-admin']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('admin_custom', $errors);
  }

  /**
   * @covers ::validateForm
   *
   * admin_custom with a trailing hyphen is invalid.
   */
  public function testValidateFormAdminCustomWithTrailingHyphenIsInvalid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_domain', 'admin_name' => '', 'admin_custom' => 'admin-']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('admin_custom', $errors);
  }

  /**
   * @covers ::validateForm
   *
   * admin_custom with special characters is invalid.
   */
  public function testValidateFormAdminCustomWithSpecialCharactersIsInvalid(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_domain', 'admin_name' => '', 'admin_custom' => 'admin!']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('admin_custom', $errors);
  }

  /**
   * @covers ::validateForm
   *
   * admin_custom is not validated when method is upanup_admin.
   */
  public function testValidateFormAdminCustomNotValidatedForUpanupAdminMethod(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'upanup_admin', 'admin_name' => 'mysite', 'admin_custom' => 'bad!value']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayNotHasKey('admin_custom', $errors);
  }

  /**
   * @covers ::validateForm
   *
   * admin_custom is validated when method is admin_subdomain.
   */
  public function testValidateFormAdminCustomValidatedForAdminSubdomainMethod(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['admin_method' => 'admin_subdomain', 'admin_name' => 'mysite', 'admin_custom' => 'bad!value']);

    $this->form->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('admin_custom', $errors);
  }

}
