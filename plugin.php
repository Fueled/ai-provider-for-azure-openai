<?php
/**
 * Plugin Name: AI Provider for Azure OpenAI
 * Plugin URI: https://github.com/fueled/ai-provider-for-azure-openai
 * Description: Azure OpenAI provider for the WordPress AI Client.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: 10up
 * Author URI: https://10up.com
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-azure-openai
 *
 * @package Fueled\AiProviderForAzureOpenAI
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_PROVIDER_FOR_AZURE_OPENAI_MIN_PHP_VERSION', '7.4' );
define( 'AI_PROVIDER_FOR_AZURE_OPENAI_MIN_WP_VERSION', '6.9' );
define( 'AI_PROVIDER_FOR_AZURE_OPENAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_PROVIDER_FOR_AZURE_OPENAI_PLUGIN_FILE', __FILE__ );

/**
 * Displays an admin notice for requirement failures.
 *
 * @since 1.0.0
 *
 * @param string $message The error message to display.
 */
function requirement_notice( string $message ): void {
	if ( ! is_admin() ) {
		return;
	}
	?>

	<div class="notice notice-error">
		<p><?php echo wp_kses_post( $message ); ?></p>
	</div>

	<?php
}

/**
 * Checks if the PHP version meets the minimum requirement.
 *
 * @since 1.0.0
 *
 * @return bool True if PHP version is sufficient, false otherwise.
 */
function check_php_version(): bool {
	if ( version_compare( phpversion(), AI_PROVIDER_FOR_AZURE_OPENAI_MIN_PHP_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				requirement_notice(
					sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version */
						__( 'The Azure OpenAI Provider plugin requires PHP version %1$s or higher. You are running PHP version %2$s.', 'ai-provider-for-azure-openai' ),
						AI_PROVIDER_FOR_AZURE_OPENAI_MIN_PHP_VERSION,
						PHP_VERSION
					)
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Checks if the WordPress version meets the minimum requirement.
 *
 * @since 1.0.0
 *
 * @global string $wp_version WordPress version.
 *
 * @return bool True if WordPress version is sufficient, false otherwise.
 */
function check_wp_version(): bool {
	if ( ! is_wp_version_compatible( AI_PROVIDER_FOR_AZURE_OPENAI_MIN_WP_VERSION ) ) {
		add_action(
			'admin_notices',
			static function () {
				global $wp_version;
				requirement_notice(
					sprintf(
						/* translators: 1: Required WordPress version, 2: Current WordPress version */
						__( 'The Azure OpenAI Provider plugin requires WordPress version %1$s or higher. You are running WordPress version %2$s.', 'ai-provider-for-azure-openai' ),
						AI_PROVIDER_FOR_AZURE_OPENAI_MIN_WP_VERSION,
						$wp_version
					)
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Loads the Azure OpenAI provider plugin.
 *
 * @since 1.0.0
 */
function load(): void {
	static $loaded = false;

	// Prevent loading twice.
	if ( $loaded ) {
		return;
	}

	// Check version requirements.
	if ( ! check_php_version() || ! check_wp_version() ) {
		return;
	}

	// Throw an error if the composer autoloader is not found.
	if ( ! file_exists( AI_PROVIDER_FOR_AZURE_OPENAI_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
		add_action(
			'admin_notices',
			static function () {
				requirement_notice(
					sprintf(
						/* translators: %s: composer install command */
						esc_html__( 'Your installation of the Azure OpenAI Provider plugin is incomplete. Please run %s.', 'ai-provider-for-azure-openai' ),
						'<code>composer install</code>'
					)
				);
			},
			10
		);

		return;
	}

	// Load the composer autoloader.
	require_once AI_PROVIDER_FOR_AZURE_OPENAI_PLUGIN_DIR . 'vendor/autoload.php';

	// Initialize the plugin.
	$plugin = new Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\load' );
