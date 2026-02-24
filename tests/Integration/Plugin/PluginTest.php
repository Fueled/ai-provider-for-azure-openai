<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Plugin;

use Fueled\AiProviderForAzureOpenAI\Auth\AzureApiKeyRequestAuthentication;
use Fueled\AiProviderForAzureOpenAI\Plugin;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\AbstractProvider;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Tests for Plugin.
 *
 * @covers \Fueled\AiProviderForAzureOpenAI\Plugin
 */
class PluginTest extends \WP_UnitTestCase {

	/**
	 * Plugin instance under test.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * The original AZURE_OPENAI_ENDPOINT value before each test.
	 *
	 * @var string|false
	 */
	private $original_endpoint;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin           = new Plugin();
		$this->original_endpoint = getenv( 'AZURE_OPENAI_ENDPOINT' );
		putenv( 'AZURE_OPENAI_ENDPOINT=' );
		delete_option( 'wp_ai_client_azure_openai_settings' );
	}

	protected function tearDown(): void {
		$this->restore_endpoint();
		delete_option( 'wp_ai_client_azure_openai_settings' );
		parent::tearDown();
	}

	/**
	 * Restores AZURE_OPENAI_ENDPOINT to its pre-test value.
	 */
	private function restore_endpoint(): void {
		if ( false === $this->original_endpoint ) {
			putenv( 'AZURE_OPENAI_ENDPOINT' );
		} else {
			putenv( 'AZURE_OPENAI_ENDPOINT=' . $this->original_endpoint );
		}
	}

	/**
	 * Resets the AiClient default registry and all AbstractProvider static caches
	 * so each test starts with a clean slate when registry state matters.
	 */
	private function reset_registry(): void {
		// Reset the singleton registry.
		$ai_client_reflection = new \ReflectionClass( AiClient::class );
		$registry_prop        = $ai_client_reflection->getProperty( 'defaultRegistry' );
		$registry_prop->setAccessible( true );
		$registry_prop->setValue( null, null );

		// Clear AbstractProvider static caches so providers re-create fresh instances.
		$provider_reflection = new \ReflectionClass( AbstractProvider::class );
		foreach ( array( 'metadataCache', 'availabilityCache', 'modelMetadataDirectoryCache' ) as $prop_name ) {
			$prop = $provider_reflection->getProperty( $prop_name );
			$prop->setAccessible( true );
			$prop->setValue( null, array() );
		}
	}

	// -----------------------------------------------------------------------
	// init() hook-registration tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that init() registers register_provider at priority 5 on the init hook.
	 */
	public function test_init_registers_register_provider_at_priority_5(): void {
		$this->plugin->init();
		$this->assertSame(
			5,
			has_action( 'init', array( $this->plugin, 'register_provider' ) )
		);
	}

	/**
	 * Tests that init() registers ensure_http_transporter at priority 15 on the init hook.
	 */
	public function test_init_registers_ensure_http_transporter_at_priority_15(): void {
		$this->plugin->init();
		$this->assertSame(
			15,
			has_action( 'init', array( $this->plugin, 'ensure_http_transporter' ) )
		);
	}

	/**
	 * Tests that init() registers convert_auth_to_azure at priority 20 on the init hook.
	 */
	public function test_init_registers_convert_auth_to_azure_at_priority_20(): void {
		$this->plugin->init();
		$this->assertSame(
			20,
			has_action( 'init', array( $this->plugin, 'convert_auth_to_azure' ) )
		);
	}

	/**
	 * Tests that init() registers initialize_settings at default priority on the init hook.
	 */
	public function test_init_registers_initialize_settings(): void {
		$this->plugin->init();
		$this->assertNotFalse(
			has_action( 'init', array( $this->plugin, 'initialize_settings' ) )
		);
	}

	// -----------------------------------------------------------------------
	// register_provider() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that register_provider() registers the azure-openai provider with the registry.
	 */
	public function test_register_provider_registers_azure_openai_with_registry(): void {
		$this->reset_registry();
		$this->plugin->register_provider();
		$this->assertTrue( AiClient::defaultRegistry()->hasProvider( 'azure-openai' ) );
	}

	/**
	 * Tests that register_provider() sets AZURE_OPENAI_ENDPOINT from the WordPress option when not already set.
	 */
	public function test_register_provider_sets_env_var_from_option(): void {
		update_option( 'wp_ai_client_azure_openai_settings', array( 'endpoint' => 'https://myresource.openai.azure.com' ) );
		putenv( 'AZURE_OPENAI_ENDPOINT=' );

		$this->plugin->register_provider();

		$this->assertSame( 'https://myresource.openai.azure.com', getenv( 'AZURE_OPENAI_ENDPOINT' ) );
	}

	/**
	 * Tests that register_provider() does not override an already-set AZURE_OPENAI_ENDPOINT env var.
	 */
	public function test_register_provider_does_not_override_existing_env_var(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=https://existing.openai.azure.com' );
		update_option( 'wp_ai_client_azure_openai_settings', array( 'endpoint' => 'https://different.openai.azure.com' ) );

		$this->plugin->register_provider();

		$this->assertSame( 'https://existing.openai.azure.com', getenv( 'AZURE_OPENAI_ENDPOINT' ) );
	}

	/**
	 * Tests that calling register_provider() twice does not throw an exception.
	 */
	public function test_register_provider_is_idempotent(): void {
		$this->reset_registry();
		$this->plugin->register_provider();
		$this->plugin->register_provider(); // Second call should be a no-op.
		$this->assertTrue( AiClient::defaultRegistry()->hasProvider( 'azure-openai' ) );
	}

	// -----------------------------------------------------------------------
	// convert_auth_to_azure() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that convert_auth_to_azure() replaces ApiKeyRequestAuthentication with AzureApiKeyRequestAuthentication.
	 */
	public function test_convert_auth_to_azure_replaces_standard_auth(): void {
		$this->reset_registry();
		$this->plugin->register_provider();

		$registry  = AiClient::defaultRegistry();
		$standard_auth = new ApiKeyRequestAuthentication( 'my-api-key' );
		$registry->setProviderRequestAuthentication( 'azure-openai', $standard_auth );

		$this->plugin->convert_auth_to_azure();

		$auth = $registry->getProviderRequestAuthentication( 'azure-openai' );
		$this->assertInstanceOf( AzureApiKeyRequestAuthentication::class, $auth );
	}

	/**
	 * Tests that convert_auth_to_azure() preserves the API key during conversion.
	 */
	public function test_convert_auth_to_azure_preserves_api_key(): void {
		$this->reset_registry();
		$this->plugin->register_provider();

		$registry = AiClient::defaultRegistry();
		$registry->setProviderRequestAuthentication(
			'azure-openai',
			new ApiKeyRequestAuthentication( 'original-key' )
		);

		$this->plugin->convert_auth_to_azure();

		$auth = $registry->getProviderRequestAuthentication( 'azure-openai' );
		$this->assertInstanceOf( AzureApiKeyRequestAuthentication::class, $auth );
		$this->assertSame( 'original-key', $auth->getApiKey() );
	}

	/**
	 * Tests that convert_auth_to_azure() does not convert if auth is already AzureApiKeyRequestAuthentication.
	 */
	public function test_convert_auth_to_azure_skips_if_already_azure_auth(): void {
		$this->reset_registry();
		$this->plugin->register_provider();

		$registry     = AiClient::defaultRegistry();
		$azure_auth = new AzureApiKeyRequestAuthentication( 'azure-key' );
		$registry->setProviderRequestAuthentication( 'azure-openai', $azure_auth );

		$this->plugin->convert_auth_to_azure();

		$auth = $registry->getProviderRequestAuthentication( 'azure-openai' );
		$this->assertSame( $azure_auth, $auth );
	}

	/**
	 * Tests that convert_auth_to_azure() does nothing when the provider is not registered.
	 */
	public function test_convert_auth_to_azure_does_nothing_when_provider_not_registered(): void {
		$this->reset_registry();
		// Do NOT register the provider.

		// Should not throw.
		$this->plugin->convert_auth_to_azure();
		$this->assertFalse( AiClient::defaultRegistry()->hasProvider( 'azure-openai' ) );
	}
}
