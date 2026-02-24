<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Settings;

use Fueled\AiProviderForAzureOpenAI\Settings\AzureOpenAISettings;

/**
 * Tests for AzureOpenAISettings.
 *
 * @covers \Fueled\AiProviderForAzureOpenAI\Settings\AzureOpenAISettings
 */
class AzureOpenAISettingsTest extends \WP_UnitTestCase {

	/**
	 * Settings instance under test.
	 *
	 * @var AzureOpenAISettings
	 */
	private AzureOpenAISettings $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = new AzureOpenAISettings();
	}

	// -----------------------------------------------------------------------
	// sanitize_settings() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a valid endpoint URL is returned unchanged.
	 */
	public function test_sanitize_settings_with_valid_endpoint_url(): void {
		$result = $this->settings->sanitize_settings( array( 'endpoint' => 'https://myresource.openai.azure.com' ) );
		$this->assertSame( array( 'endpoint' => 'https://myresource.openai.azure.com' ), $result );
	}

	/**
	 * Tests that a trailing slash is stripped from the endpoint URL.
	 */
	public function test_sanitize_settings_strips_trailing_slash(): void {
		$result = $this->settings->sanitize_settings( array( 'endpoint' => 'https://myresource.openai.azure.com/' ) );
		$this->assertSame( 'https://myresource.openai.azure.com', $result['endpoint'] );
	}

	/**
	 * Tests that a non-array input returns an empty array.
	 */
	public function test_sanitize_settings_with_non_array_returns_empty_array(): void {
		$result = $this->settings->sanitize_settings( 'not-an-array' );
		$this->assertSame( array(), $result );
	}

	/**
	 * Tests that an empty array input returns an array with an empty endpoint key.
	 */
	public function test_sanitize_settings_with_empty_array_returns_endpoint_key(): void {
		$result = $this->settings->sanitize_settings( array() );
		$this->assertArrayHasKey( 'endpoint', $result );
		$this->assertSame( '', $result['endpoint'] );
	}

	/**
	 * Tests that an explicitly empty endpoint string is preserved.
	 */
	public function test_sanitize_settings_with_empty_endpoint_preserves_empty_string(): void {
		$result = $this->settings->sanitize_settings( array( 'endpoint' => '' ) );
		$this->assertSame( '', $result['endpoint'] );
	}

	/**
	 * Tests that leading and trailing whitespace is trimmed from the endpoint.
	 */
	public function test_sanitize_settings_trims_whitespace(): void {
		$result = $this->settings->sanitize_settings( array( 'endpoint' => '  https://myresource.openai.azure.com  ' ) );
		$this->assertStringStartsWith( 'https://', $result['endpoint'] );
	}

	/**
	 * Tests that the endpoint value is sanitized as a URL.
	 */
	public function test_sanitize_settings_sanitizes_url(): void {
		$input  = 'https://myresource.openai.azure.com';
		$result = $this->settings->sanitize_settings( array( 'endpoint' => $input ) );
		$this->assertIsString( $result['endpoint'] );
		$this->assertNotEmpty( $result['endpoint'] );
		$this->assertStringStartsWith( 'https', $result['endpoint'] );
	}

	// -----------------------------------------------------------------------
	// Hook-registration tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that init() registers register_settings on admin_init.
	 */
	public function test_init_registers_admin_init_hook(): void {
		$this->settings->init();
		$this->assertNotFalse(
			has_action( 'admin_init', array( $this->settings, 'register_settings' ) )
		);
	}

	/**
	 * Tests that init() registers register_settings_screen on admin_menu.
	 */
	public function test_init_registers_admin_menu_hook(): void {
		$this->settings->init();
		$this->assertNotFalse(
			has_action( 'admin_menu', array( $this->settings, 'register_settings_screen' ) )
		);
	}

	/**
	 * Tests that init() registers ajax_list_models on the AJAX action hook.
	 */
	public function test_init_registers_ajax_hook(): void {
		$this->settings->init();
		$this->assertNotFalse(
			has_action(
				'wp_ajax_wp_ai_client_azure_openai_list_models',
				array( $this->settings, 'ajax_list_models' )
			)
		);
	}
}
