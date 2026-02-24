<?php

/**
 * AzureOpenAISettings class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\AiClient;

/**
 * Class for the Azure OpenAI settings in the WordPress admin.
 *
 * Provides a settings page under Settings > Azure OpenAI Settings for
 * configuring the Azure OpenAI resource endpoint URL.
 *
 * @since 1.0.0
 */
class AzureOpenAISettings {

	private const OPTION_GROUP = 'wp-ai-client-azure-openai-settings';
	private const OPTION_NAME  = 'wp_ai_client_azure_openai_settings';
	private const PAGE_SLUG    = 'wp-ai-client-azure-openai';
	private const SECTION_ID   = 'wp_ai_client_azure_openai_main';
	private const AJAX_ACTION  = 'wp_ai_client_azure_openai_list_models';
	private const NONCE_ACTION = 'wp_ai_client_azure_openai_nonce';

	/**
	 * Initializes the settings.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_script' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_list_models' ) );
	}

	/**
	 * Registers the setting and settings fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			'',
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_NAME . '_endpoint',
			__( 'Endpoint URL', 'ai-provider-for-azure-openai' ),
			array( $this, 'render_endpoint_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-endpoint' )
		);

		add_settings_field(
			self::OPTION_NAME . '_models',
			__( 'Available Models', 'ai-provider-for-azure-openai' ),
			array( $this, 'render_available_models_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-models' )
		);
	}

	/**
	 * Registers the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_screen(): void {
		add_options_page(
			__( 'Azure OpenAI Settings', 'ai-provider-for-azure-openai' ),
			__( 'Azure OpenAI Settings', 'ai-provider-for-azure-openai' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_screen' )
		);
	}

	/**
	 * Sanitizes the settings array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The input value.
	 * @return array<string, string> The sanitized settings.
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$endpoint = isset( $value['endpoint'] ) ? trim( (string) $value['endpoint'] ) : '';
		if ( '' !== $endpoint ) {
			$endpoint = rtrim( esc_url_raw( $endpoint ), '/' );
		}

		return array(
			'endpoint' => $endpoint,
		);
	}

	/**
	 * Renders the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function render_screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>

		<div class="wrap" style="max-width: 50rem;">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: link to the AI Credentials screen, 2: closing link tag */
					esc_html__( 'Configure the connection to your Azure OpenAI resource. Enter the resource endpoint URL below, then add your API key on the %1$sSettings > AI Credentials%2$s screen.', 'ai-provider-for-azure-openai' ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=wp-ai-client' ) ) . '">',
					'</a>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: code tag, 2: closing code tag */
					esc_html__( 'The endpoint URL is the base URL of your Azure OpenAI resource. Do not include %1$s/openai/v1%2$s as it will be appended automatically. You can also set the %1$sAZURE_OPENAI_ENDPOINT%2$s environment variable to override this setting.', 'ai-provider-for-azure-openai' ),
					'<code>',
					'</code>'
				);
				?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>

		<?php
	}

	/**
	 * Renders the endpoint URL field.
	 *
	 * @since 1.0.0
	 */
	public function render_endpoint_field(): void {
		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$value = isset( $settings['endpoint'] ) ? $settings['endpoint'] : '';
		?>

		<input
			type="url"
			id="<?php echo esc_attr( self::OPTION_NAME . '-endpoint' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[endpoint]' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="https://myresource.openai.azure.com"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: 1: code tag, 2: closing code tag */
				esc_html__( 'The base URL of your Azure OpenAI resource. Example: %1$shttps://myresource.openai.azure.com%2$s', 'ai-provider-for-azure-openai' ),
				'<code>',
				'</code>'
			);
			?>
		</p>

		<?php
	}

	/**
	 * Renders the available models list.
	 *
	 * @since 1.0.0
	 */
	public function render_available_models_field(): void {
		?>

		<div id="azure-openai-models-container">
			<span id="azure-openai-model-status"></span>
		</div>
		<p class="description">
			<?php
			echo esc_html__( 'Available models are auto-discovered from your Azure OpenAI resource. Models are listed by their deployment name.', 'ai-provider-for-azure-openai' );
			?>
		</p>

		<?php
	}

	/**
	 * Enqueues the settings page script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_settings_script( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$plugin_dir = AI_PROVIDER_FOR_AZURE_OPENAI_PLUGIN_DIR;
		$asset_file = $plugin_dir . 'build/admin/settings.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(); // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Asset file path is built from a known constant.

		$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : array();
		$version      = isset( $asset['version'] ) ? $asset['version'] : false;

		wp_enqueue_script(
			'wp-ai-client-azure-openai-settings',
			plugins_url( 'build/admin/settings.js', $plugin_dir . 'plugin.php' ),
			$dependencies,
			$version,
			true
		);

		wp_localize_script(
			'wp-ai-client-azure-openai-settings',
			'wpAiClientAzureOpenAISettings',
			array(
				'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) . '?action=' . self::AJAX_ACTION . '&_wpnonce=' . wp_create_nonce( self::NONCE_ACTION ) ),
			)
		);
	}

	/**
	 * Handles the AJAX request to list available Azure OpenAI models.
	 *
	 * @since 1.0.0
	 */
	public function ajax_list_models(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'ai-provider-for-azure-openai' ), 403 );
		}

		$provider_id = 'azure-openai';
		$registry    = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			wp_send_json_error( __( 'AI provider not found.', 'ai-provider-for-azure-openai' ), 404 );
		}

		$provider_classname = $registry->getProviderClassName( $provider_id );

		try {
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$provider_availability = $provider_classname::availability();
			if ( ! $provider_availability->isConfigured() ) {
				wp_send_json_error( __( 'AI provider not configured — enter an endpoint URL and save settings first.', 'ai-provider-for-azure-openai' ), 400 );
			}

			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$model_metadata_directory = $provider_classname::modelMetadataDirectory();
			$model_metadata_objects   = $model_metadata_directory->listModelMetadata();

			wp_send_json_success( $model_metadata_objects );
		} catch ( \Throwable $e ) {
			/* translators: %s: Error message. */
			wp_send_json_error( sprintf( __( 'Could not list models for provider — are the endpoint URL and API key correct? Error: %s', 'ai-provider-for-azure-openai' ), $e->getMessage() ), 500 );
		}
	}
}
