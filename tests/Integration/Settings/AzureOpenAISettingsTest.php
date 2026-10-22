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
		delete_option( 'wp_ai_client_azure_openai_settings' );
	}

	protected function tearDown(): void {
		delete_option( 'wp_ai_client_azure_openai_settings' );
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// sanitize_settings() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a valid endpoint URL is returned unchanged.
	 */
	public function test_sanitize_settings_with_valid_endpoint_url(): void {
		$result = $this->settings->sanitize_settings( array( 'endpoint' => 'https://myresource.openai.azure.com' ) );
		$this->assertSame( 'https://myresource.openai.azure.com', $result['endpoint'] );
		$this->assertArrayHasKey( 'deployments', $result );
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
	 * Tests that the settings page is only added where there is no Connectors screen.
	 */
	public function test_init_adds_settings_page_only_without_connectors_screen(): void {
		$this->settings->init();
		$expected = ! AzureOpenAISettings::has_connectors_screen();

		$this->assertSame( $expected, false !== has_action( 'admin_init', array( $this->settings, 'register_settings' ) ) );
		$this->assertSame( $expected, false !== has_action( 'admin_menu', array( $this->settings, 'register_settings_screen' ) ) );
	}

	/**
	 * Tests that init() registers the setting for the REST API, so the Connectors card can save it.
	 */
	public function test_init_registers_setting_in_rest(): void {
		$this->settings->init();
		$registered = get_registered_settings();

		$this->assertArrayHasKey( AzureOpenAISettings::OPTION_NAME, $registered );
		$this->assertNotEmpty( $registered[ AzureOpenAISettings::OPTION_NAME ]['show_in_rest'] );
	}

	/**
	 * Tests that an /openai path copied from the Azure portal is stripped from the endpoint.
	 */
	public function test_sanitize_settings_strips_openai_path(): void {
		foreach ( array( 'https://myresource.openai.azure.com/openai/v1/', 'https://myresource.openai.azure.com/openai/deployments/gpt/chat/completions?api-version=2024-10-21', 'https://myresource.openai.azure.com/openai' ) as $input ) {
			$result = $this->settings->sanitize_settings( array( 'endpoint' => $input ) );
			$this->assertSame( 'https://myresource.openai.azure.com', $result['endpoint'], $input );
		}
	}

	// -----------------------------------------------------------------------
	// Deployment sanitization tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a valid deployments array is saved as-is.
	 */
	public function test_sanitize_settings_saves_valid_deployments_array(): void {
		$result = $this->settings->sanitize_settings(
			array(
				'endpoint'    => 'https://myresource.openai.azure.com',
				'deployments' => array(
					array( 'name' => 'my-gpt4o', 'type' => 'chat_multimodal' ),
					array( 'name' => 'my-dall-e', 'type' => 'image_dalle' ),
				),
			)
		);

		$this->assertArrayHasKey( 'deployments', $result );
		$this->assertCount( 2, $result['deployments'] );
		$this->assertSame( 'my-gpt4o', $result['deployments'][0]['name'] );
		$this->assertSame( 'chat_multimodal', $result['deployments'][0]['type'] );
		$this->assertSame( 'my-dall-e', $result['deployments'][1]['name'] );
		$this->assertSame( 'image_dalle', $result['deployments'][1]['type'] );
	}

	/**
	 * Tests that blank deployment names are stripped from the saved array.
	 */
	public function test_sanitize_settings_strips_blank_deployment_names(): void {
		$result = $this->settings->sanitize_settings(
			array(
				'deployments' => array(
					array( 'name' => '', 'type' => 'chat' ),
					array( 'name' => 'valid-name', 'type' => 'chat' ),
					array( 'name' => '   ', 'type' => 'chat' ),
				),
			)
		);

		$this->assertCount( 1, $result['deployments'] );
		$this->assertSame( 'valid-name', $result['deployments'][0]['name'] );
	}

	/**
	 * Tests that an invalid deployment type defaults to 'chat'.
	 */
	public function test_sanitize_settings_invalid_deployment_type_defaults_to_chat(): void {
		$result = $this->settings->sanitize_settings(
			array(
				'deployments' => array(
					array( 'name' => 'my-deployment', 'type' => 'invalid_type' ),
				),
			)
		);

		$this->assertCount( 1, $result['deployments'] );
		$this->assertSame( 'chat', $result['deployments'][0]['type'] );
	}

	/**
	 * Tests that a non-array deployments value results in an empty deployments array.
	 */
	public function test_sanitize_settings_non_array_deployments_becomes_empty_array(): void {
		$result = $this->settings->sanitize_settings(
			array(
				'endpoint'    => 'https://myresource.openai.azure.com',
				'deployments' => 'not-an-array',
			)
		);

		$this->assertArrayHasKey( 'deployments', $result );
		$this->assertSame( array(), $result['deployments'] );
	}

	/**
	 * Tests that all valid deployment types are accepted without defaulting.
	 */
	public function test_sanitize_settings_all_valid_types_are_accepted(): void {
		$valid_types = array( 'chat', 'chat_multimodal', 'image_dalle', 'image_gpt', 'tts' );
		$deployments = array();

		foreach ( $valid_types as $type ) {
			$deployments[] = array( 'name' => 'deploy-' . $type, 'type' => $type );
		}

		$result = $this->settings->sanitize_settings( array( 'deployments' => $deployments ) );

		$this->assertCount( 5, $result['deployments'] );
		foreach ( $result['deployments'] as $index => $deployment ) {
			$this->assertSame( $valid_types[ $index ], $deployment['type'] );
		}
	}

	/**
	 * Tests that get_settings() returns saved settings as an array.
	 */
	public function test_get_settings_returns_saved_option(): void {
		$saved_settings = array(
			'endpoint'    => 'https://myresource.openai.azure.com',
			'deployments' => array(
				array(
					'name' => 'my-gpt4o',
					'type' => 'chat',
				),
			),
		);
		update_option( 'wp_ai_client_azure_openai_settings', $saved_settings );

		$this->assertSame( $saved_settings, AzureOpenAISettings::get_settings() );
	}

	/**
	 * Tests that render_screen() links to the Connectors admin screen.
	 */
	public function test_render_screen_outputs_connectors_screen_link(): void {
		$admin_user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $admin_user_id );
		$GLOBALS['title'] = 'Azure OpenAI Settings';

		ob_start();
		$this->settings->render_screen();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'options-connectors.php', $output );
		wp_set_current_user( 0 );
	}
}
