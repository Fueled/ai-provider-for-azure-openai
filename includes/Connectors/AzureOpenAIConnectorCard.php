<?php

/**
 * AzureOpenAIConnectorCard class.
 *
 * @since 1.1.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Connectors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Fueled\AiProviderForAzureOpenAI\Settings\AzureOpenAISettings;
use WordPress\AiClient\AiClient;

/**
 * Class for the Azure OpenAI card on the Settings > Connectors screen.
 *
 * Core's card for an AI provider only collects an API key. Azure also needs the resource
 * endpoint and the deployments, so this swaps in a card (assets/js/connector.js) that collects
 * all three and reports whether Azure accepts them.
 *
 * @since 1.1.0
 */
class AzureOpenAIConnectorCard {

	private const MODULE_ID = 'ai-provider-for-azure-openai/connector';

	/**
	 * Initializes the card.
	 *
	 * @since 1.1.0
	 */
	public function init(): void {
		if ( ! AzureOpenAISettings::has_connectors_screen() ) {
			return;
		}

		add_action( 'options-connectors-wp-admin_init', array( $this, 'enqueue_card' ) );
		add_filter( 'script_module_data_' . self::MODULE_ID, array( $this, 'card_data' ) );
		add_action( 'rest_api_init', array( $this, 'register_status_route' ) );
	}

	/**
	 * Enqueues the card's script module on the Connectors screen.
	 *
	 * A card registered for the provider's ID before core registers its defaults keeps its
	 * own render, and core fills in the name, logo and connection state.
	 *
	 * @since 1.1.0
	 */
	public function enqueue_card(): void {
		wp_register_script_module(
			self::MODULE_ID,
			plugins_url( 'assets/js/connector.js', AI_PROVIDER_FOR_AZURE_OPENAI_PLUGIN_FILE ),
			array( array( 'id' => '@wordpress/connectors' ) ),
			(string) filemtime( AI_PROVIDER_FOR_AZURE_OPENAI_PLUGIN_DIR . 'assets/js/connector.js' )
		);
		wp_enqueue_script_module( self::MODULE_ID );

		// The card renders with the classic globals. The screen loads them already; this says so.
		foreach ( array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ) as $handle ) {
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * Hands the card the saved endpoint and deployments, and the deployment types.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Existing script module data.
	 * @return array<string, mixed> The card's data.
	 */
	public function card_data( array $data ): array {
		$settings = AzureOpenAISettings::get_settings();
		$saved    = isset( $settings['endpoint'] ) ? (string) $settings['endpoint'] : '';
		$env      = (string) getenv( 'AZURE_OPENAI_ENDPOINT' );

		// The plugin copies the saved endpoint into the environment, so only a different value was set there by hand.
		$data['optionName']     = AzureOpenAISettings::OPTION_NAME;
		$data['endpointLocked'] = '' !== $env && $env !== $saved;
		$data['endpoint']       = $data['endpointLocked'] ? $env : $saved;
		$data['deployments']    = isset( $settings['deployments'] ) && is_array( $settings['deployments'] ) ? array_values( $settings['deployments'] ) : array();
		$data['modelTypes']     = AzureOpenAISettings::get_model_type_labels();

		return $data;
	}

	/**
	 * Registers `GET ai-provider-for-azure-openai/v1/status`: whether Azure accepts the saved settings.
	 *
	 * The card calls it after saving, because core only checks the connection when the key
	 * itself is saved, not when only the endpoint or deployments change.
	 *
	 * @since 1.1.0
	 */
	public function register_status_route(): void {
		register_rest_route(
			'ai-provider-for-azure-openai/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'callback'            => static function (): array {
					if ( ! class_exists( AiClient::class ) ) {
						return array( 'connected' => false );
					}

					try {
						$connected = AiClient::defaultRegistry()->isProviderConfigured( 'azure_openai' );
					} catch ( \Throwable $e ) {
						$connected = false;
					}

					return array( 'connected' => $connected );
				},
			)
		);
	}
}
