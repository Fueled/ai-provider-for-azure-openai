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
use Fueled\AiProviderForAzureOpenAI\Connectors\AzureOpenAIConnectorCard;
use Fueled\AiProviderForAzureOpenAI\Metadata\AzureOpenAIModelMetadataDirectory;
use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProvider;
use Fueled\AiProviderForAzureOpenAI\Settings\AzureOpenAISettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Plugin initializer class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * The endpoint this plugin last put in AZURE_OPENAI_ENDPOINT, so any other value is known to come from the environment.
	 *
	 * @since 1.1.0
	 *
	 * @var string|null
	 */
	private static ?string $synced_endpoint = null;

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_provider' ), 5 );
		add_action( 'init', array( $this, 'convert_auth_to_azure' ), 25 );
		add_action( 'init', array( $this, 'initialize_settings' ) );
		add_action( 'add_option_' . AzureOpenAISettings::OPTION_NAME, array( $this, 'sync_settings' ) );
		add_action( 'update_option_' . AzureOpenAISettings::OPTION_NAME, array( $this, 'sync_settings' ) );
		add_action( 'delete_option_' . AzureOpenAISettings::OPTION_NAME, array( $this, 'sync_settings' ) );
		add_action( 'init', array( new AzureOpenAIConnectorCard(), 'init' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_AZURE_OPENAI_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Sets the AZURE_OPENAI_ENDPOINT environment variable from the settings.
	 *
	 * An endpoint set in the environment wins and is left alone.
	 *
	 * @since 1.0.0
	 */
	private function set_azure_endpoint(): void {
		$env_endpoint = getenv( 'AZURE_OPENAI_ENDPOINT' );
		if ( false !== $env_endpoint && '' !== $env_endpoint && self::$synced_endpoint !== $env_endpoint ) {
			return;
		}

		// Get the Azure OpenAI endpoint from the WordPress option.
		$settings = AzureOpenAISettings::get_settings();
		$endpoint = isset( $settings['endpoint'] ) ? (string) $settings['endpoint'] : '';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required to set AZURE_OPENAI_ENDPOINT for the provider SDK.
		putenv( '' !== $endpoint ? 'AZURE_OPENAI_ENDPOINT=' . $endpoint : 'AZURE_OPENAI_ENDPOINT' );
		self::$synced_endpoint = $endpoint;
	}

	/**
	 * Sets the deployments configuration.
	 *
	 * @since 1.0.0
	 */
	private function set_deployments(): void {
		$settings    = AzureOpenAISettings::get_settings();
		$deployments = isset( $settings['deployments'] ) && is_array( $settings['deployments'] )
			? $settings['deployments']
			: array();
		AzureOpenAIModelMetadataDirectory::setDeployments( $deployments );
	}

	/**
	 * Hands the saved endpoint and deployments to the provider.
	 *
	 * Runs on registration and again whenever the settings are saved or deleted, so a check made in the
	 * same request as the save, like the one WordPress runs when a key is saved on the
	 * Connectors screen, sees the new values.
	 *
	 * @since 1.1.0
	 */
	public function sync_settings(): void {
		$this->set_azure_endpoint();
		$this->set_deployments();
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

		$this->sync_settings();

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( AzureOpenAIProvider::class ) ) {
			return;
		}

		$registry->registerProvider( AzureOpenAIProvider::class );
	}

	/**
	 * Swaps the standard Bearer-token authentication for Azure's `api-key` header authentication.
	 *
	 * Runs at priority 25, after credentials are set: by the wp-ai-client plugin at priority 10, or by
	 * WordPress core's Connectors at priority 20. Replaces a standard ApiKeyRequestAuthentication
	 * (which sends `Authorization: Bearer`) with AzureApiKeyRequestAuthentication (which sends
	 * `api-key: <key>`), so existing credential management works without modification.
	 *
	 * @since 1.0.0
	 */
	public function convert_auth_to_azure(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'azure_openai' ) ) {
			return;
		}

		$auth = $registry->getProviderRequestAuthentication( 'azure_openai' );
		if ( ! $auth instanceof ApiKeyRequestAuthentication ) {
			return;
		}

		// Only convert if not already using Azure-specific authentication.
		if ( $auth instanceof AzureApiKeyRequestAuthentication ) {
			return;
		}

		$registry->setProviderRequestAuthentication(
			'azure_openai',
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
	 * on the Plugins page, pointing at the Connectors screen where it exists.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			AzureOpenAISettings::has_connectors_screen()
				? admin_url( 'options-connectors.php' )
				: admin_url( 'options-general.php?page=wp-ai-client-azure-openai' ),
			esc_html__( 'Settings', 'ai-provider-for-azure-openai' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
