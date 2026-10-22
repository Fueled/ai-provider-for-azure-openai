<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Connectors;

use Fueled\AiProviderForAzureOpenAI\Connectors\AzureOpenAIConnectorCard;
use Fueled\AiProviderForAzureOpenAI\Settings\AzureOpenAISettings;

/**
 * Tests for AzureOpenAIConnectorCard.
 *
 * @covers \Fueled\AiProviderForAzureOpenAI\Connectors\AzureOpenAIConnectorCard
 */
class AzureOpenAIConnectorCardTest extends \WP_UnitTestCase {

	/**
	 * Card instance under test.
	 *
	 * @var AzureOpenAIConnectorCard
	 */
	private AzureOpenAIConnectorCard $card;

	/**
	 * The original AZURE_OPENAI_ENDPOINT value before each test.
	 *
	 * @var string|false
	 */
	private $original_endpoint;

	protected function setUp(): void {
		parent::setUp();
		$this->card              = new AzureOpenAIConnectorCard();
		$this->original_endpoint = getenv( 'AZURE_OPENAI_ENDPOINT' );
		update_option(
			AzureOpenAISettings::OPTION_NAME,
			array(
				'endpoint'    => 'https://myresource.openai.azure.com',
				'deployments' => array(
					array(
						'name' => 'my-gpt4o',
						'type' => 'chat_multimodal',
					),
				),
			)
		);
	}

	protected function tearDown(): void {
		if ( false === $this->original_endpoint ) {
			putenv( 'AZURE_OPENAI_ENDPOINT' );
		} else {
			putenv( 'AZURE_OPENAI_ENDPOINT=' . $this->original_endpoint );
		}
		delete_option( AzureOpenAISettings::OPTION_NAME );
		parent::tearDown();
	}

	/**
	 * Tests that the card hooks in only where there is a Connectors screen.
	 */
	public function test_init_hooks_into_connectors_screen(): void {
		$this->card->init();
		$this->assertSame(
			AzureOpenAISettings::has_connectors_screen(),
			false !== has_action( 'options-connectors-wp-admin_init', array( $this->card, 'enqueue_card' ) )
		);
	}

	/**
	 * Tests that the card receives the saved endpoint, deployments and deployment types.
	 */
	public function test_card_data_includes_saved_settings(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com' );
		$data = $this->card->card_data( array() );

		$this->assertSame( AzureOpenAISettings::OPTION_NAME, $data['optionName'] );
		$this->assertSame( 'https://myresource.openai.azure.com', $data['endpoint'] );
		$this->assertFalse( $data['endpointLocked'] );
		$this->assertSame( 'my-gpt4o', $data['deployments'][0]['name'] );
		$this->assertArrayHasKey( 'chat_multimodal', $data['modelTypes'] );
	}

	/**
	 * Tests that an endpoint set in the environment locks the field and shows that value.
	 */
	public function test_card_data_locks_endpoint_set_in_environment(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=https://fromenv.openai.azure.com' );
		$data = $this->card->card_data( array() );

		$this->assertTrue( $data['endpointLocked'] );
		$this->assertSame( 'https://fromenv.openai.azure.com', $data['endpoint'] );
	}

	/**
	 * Tests that the status route is limited to administrators.
	 */
	public function test_status_route_requires_manage_options(): void {
		do_action( 'rest_api_init' );
		$this->card->register_status_route();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/ai-provider-for-azure-openai/v1/status' ) );
		$this->assertSame( 403, $response->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/ai-provider-for-azure-openai/v1/status' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'connected', $response->get_data() );
		wp_set_current_user( 0 );
	}
}
