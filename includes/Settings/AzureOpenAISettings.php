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

/**
 * Class for the Azure OpenAI settings in the WordPress admin.
 *
 * Provides a settings page under Settings > Azure OpenAI Settings for
 * configuring the Azure OpenAI resource endpoint URL and deployments.
 *
 * @since 1.0.0
 */
class AzureOpenAISettings {

	private const OPTION_GROUP = 'wp-ai-client-azure-openai-settings';
	private const OPTION_NAME  = 'wp_ai_client_azure_openai_settings';
	private const PAGE_SLUG    = 'wp-ai-client-azure-openai';
	private const SECTION_ID   = 'wp_ai_client_azure_openai_main';

	/**
	 * Initializes the settings.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_script' ) );
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
			self::OPTION_NAME . '_deployments',
			__( 'Deployments', 'ai-provider-for-azure-openai' ),
			array( $this, 'render_deployments_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID
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
	 * @return array<string, mixed> The sanitized settings.
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$endpoint = isset( $value['endpoint'] ) ? trim( (string) $value['endpoint'] ) : '';
		if ( '' !== $endpoint ) {
			$endpoint = rtrim( esc_url_raw( $endpoint ), '/' );
		}

		$allowed_types = array( 'chat', 'chat_multimodal', 'image_dalle', 'image_gpt', 'tts' );
		$deployments   = array();

		if ( isset( $value['deployments'] ) && is_array( $value['deployments'] ) ) {
			foreach ( $value['deployments'] as $deployment ) {
				if ( ! is_array( $deployment ) ) {
					continue;
				}

				$name = isset( $deployment['name'] ) ? sanitize_text_field( (string) $deployment['name'] ) : '';

				if ( '' === $name ) {
					continue;
				}

				$type = isset( $deployment['type'] ) ? sanitize_key( (string) $deployment['type'] ) : 'chat';

				if ( ! in_array( $type, $allowed_types, true ) ) {
					$type = 'chat';
				}

				$deployments[] = array(
					'name' => $name,
					'type' => $type,
				);
			}
		}

		return array(
			'endpoint'    => $endpoint,
			'deployments' => $deployments,
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
					esc_html__( 'Configure the connection to your Azure OpenAI resource. Enter the resource endpoint URL below, then add your API key to the %1$sSettings > Connectors%2$s screen.', 'ai-provider-for-azure-openai' ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=connectors-wp-admin' ) ) . '">',
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
		$settings = self::get_settings();
		$value    = isset( $settings['endpoint'] ) ? $settings['endpoint'] : '';
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
	 * Returns the model type labels for the deployments select field.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Map of type key to display label.
	 */
	private function get_model_type_labels(): array {
		return array(
			'chat'            => __( 'Chat (Text Generation)', 'ai-provider-for-azure-openai' ),
			'chat_multimodal' => __( 'Chat + Vision (Multimodal)', 'ai-provider-for-azure-openai' ),
			'image_dalle'     => __( 'Image Generation (DALL-E)', 'ai-provider-for-azure-openai' ),
			'image_gpt'       => __( 'Image Generation (GPT Image)', 'ai-provider-for-azure-openai' ),
			'tts'             => __( 'Text-to-Speech', 'ai-provider-for-azure-openai' ),
		);
	}

	/**
	 * Renders the deployments table with any saved deployments pre-populated.
	 *
	 * JavaScript on this page handles adding and removing rows interactively.
	 *
	 * @since 1.0.0
	 */
	public function render_deployments_field(): void {
		$settings    = self::get_settings();
		$raw_deps    = isset( $settings['deployments'] ) && is_array( $settings['deployments'] )
			? $settings['deployments']
			: array();
		$model_types = $this->get_model_type_labels();

		// Pre-process into typed rows to keep template logic minimal.
		$deployments = array();
		foreach ( $raw_deps as $dep ) {
			if ( ! is_array( $dep ) ) {
				continue;
			}

			$deployments[] = array(
				'name' => isset( $dep['name'] ) ? (string) $dep['name'] : '',
				'type' => isset( $dep['type'] ) ? (string) $dep['type'] : 'chat',
			);
		}
		$table_style = empty( $deployments )
			? 'display: none;'
			: '';
		?>

		<div style="max-width: 570px;">
			<table class="widefat striped" style="<?php echo esc_attr( $table_style ); ?>">
				<thead>
					<tr>
						<th style="padding-left: 15px;"><?php esc_html_e( 'Deployment Name', 'ai-provider-for-azure-openai' ); ?></th>
						<th style="padding-left: 15px;"><?php esc_html_e( 'Model Type', 'ai-provider-for-azure-openai' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="azure-openai-deployments-tbody">
					<?php foreach ( $deployments as $index => $dep ) : ?>
					<tr>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr( self::OPTION_NAME . '[deployments][' . $index . '][name]' ); ?>"
								value="<?php echo esc_attr( $dep['name'] ); ?>"
								class="regular-text"
							/>
						</td>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME . '[deployments][' . $index . '][type]' ); ?>">
								<?php foreach ( $model_types as $type_key => $type_label ) : ?>
								<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $dep['type'], $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<button type="button" class="button azure-openai-remove-deployment"><?php esc_html_e( 'Remove', 'ai-provider-for-azure-openai' ); ?></button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<button type="button" id="azure-openai-add-deployment" class="button button-secondary" style="margin-top: 8px;">
			<?php esc_html_e( 'Add Deployment', 'ai-provider-for-azure-openai' ); ?>
		</button>
		<p class="description">
			<?php echo esc_html__( 'Add each deployment name and select its model type. The deployment name is the one you chose when creating the deployment in Azure.', 'ai-provider-for-azure-openai' ); ?>
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

		$settings = self::get_settings();
		$raw_deps = isset( $settings['deployments'] ) && is_array( $settings['deployments'] )
			? $settings['deployments']
			: array();

		wp_localize_script(
			'wp-ai-client-azure-openai-settings',
			'wpAiClientAzureOpenAISettings',
			array(
				'modelTypes' => $this->get_model_type_labels(),
				'nextIndex'  => count( $raw_deps ),
			)
		);
	}

	/**
	 * Gets the settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The settings.
	 */
	public static function get_settings(): array {
		return (array) get_option( self::OPTION_NAME, array() );
	}
}
