<?php

/**
 * Plugin initializer class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Fueled\AiProviderForAzureOpenAI\Auth\AzureApiKeyRequestAuthentication;
use Fueled\AiProviderForAzureOpenAI\Metadata\AzureOpenAIModelMetadataDirectory;
use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProvider;
use Fueled\AiProviderForAzureOpenAI\Settings\AzureOpenAISettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\HttpTransporterFactory;

/**
 * Plugin initializer class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_provider' ), 5 );
		add_action( 'init', array( $this, 'ensure_http_transporter' ), 15 );
		add_action( 'init', array( $this, 'convert_auth_to_azure' ), 20 );
		add_action( 'init', array( $this, 'initialize_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_AZURE_OPENAI_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Sets the AZURE_OPENAI_ENDPOINT environment variable from the WordPress option.
	 *
	 * @since 1.0.0
	 */
	private function set_azure_endpoint_from_option(): void {
		// Check if the AZURE_OPENAI_ENDPOINT environment variable is already set.
		$env_endpoint = getenv( 'AZURE_OPENAI_ENDPOINT' );
		if ( false !== $env_endpoint && '' !== $env_endpoint ) {
			return;
		}

		// Get the Azure OpenAI endpoint from the WordPress option.
		$settings = get_option( 'wp_ai_client_azure_openai_settings', array() );
		if (
			! is_array( $settings ) ||
			! isset( $settings['endpoint'] ) ||
			'' === $settings['endpoint']
		) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required to set AZURE_OPENAI_ENDPOINT for the provider SDK.
		putenv( 'AZURE_OPENAI_ENDPOINT=' . $settings['endpoint'] );
	}

	/**
	 * Sets the deployments configuration from the WordPress option.
	 *
	 * @since 1.0.0
	 */
	private function set_deployments_from_option(): void {
		$settings    = get_option( 'wp_ai_client_azure_openai_settings', array() );
		$deployments = isset( $settings['deployments'] ) && is_array( $settings['deployments'] )
			? $settings['deployments']
			: array();
		AzureOpenAIModelMetadataDirectory::setDeployments( $deployments );
	}

	/**
	 * Registers the Azure OpenAI provider with the AI Client.
	 *
	 * @since 1.0.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$this->set_azure_endpoint_from_option();
		$this->set_deployments_from_option();

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( AzureOpenAIProvider::class ) ) {
			return;
		}

		$registry->registerProvider( AzureOpenAIProvider::class );
	}

	/**
	 * Ensures the HTTP transporter is set on the registry.
	 *
	 * Providers register at priority 5, before the WordPress PSR-18 HTTP
	 * client discovery strategy is available (registered at priority 10 by
	 * wp-ai-client). This method runs at priority 15 to trigger transporter
	 * creation after the strategy is in place.
	 *
	 * @since 1.0.0
	 */
	public function ensure_http_transporter(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		try {
			$registry->getHttpTransporter();
		} catch ( \Throwable $e ) {
			try {
				$registry->setHttpTransporter( HttpTransporterFactory::createTransporter() );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Graceful degradation when no PSR-18 client is available.
			}
		}
	}

	/**
	 * Swaps the standard Bearer-token authentication for Azure's `api-key` header authentication.
	 *
	 * Runs at priority 20, after wp-ai-client sets credentials at priority 10. Replaces a
	 * standard ApiKeyRequestAuthentication (which sends `Authorization: Bearer`) with
	 * AzureApiKeyRequestAuthentication (which sends `api-key: <key>`), so existing
	 * credential management in wp-ai-client works without modification.
	 *
	 * @since 1.0.0
	 */
	public function convert_auth_to_azure(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'azure-openai' ) ) {
			return;
		}

		$auth = $registry->getProviderRequestAuthentication( 'azure-openai' );
		if ( ! $auth instanceof ApiKeyRequestAuthentication ) {
			return;
		}

		// Only convert if not already using Azure-specific authentication.
		if ( $auth instanceof AzureApiKeyRequestAuthentication ) {
			return;
		}

		$registry->setProviderRequestAuthentication(
			'azure-openai',
			new AzureApiKeyRequestAuthentication( $auth->getApiKey() )
		);
	}

	/**
	 * Initializes the Azure OpenAI settings.
	 *
	 * @since 1.0.0
	 */
	public function initialize_settings(): void {
		$settings = new AzureOpenAISettings();
		$settings->init();
	}

	/**
	 * Adds action links to the plugin list table.
	 *
	 * This adds "Settings" link to the plugin's action links
	 * on the Plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'options-general.php?page=wp-ai-client-azure-openai' ),
			esc_html__( 'Settings', 'ai-provider-for-azure-openai' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
