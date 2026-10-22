<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Provider;

use Fueled\AiProviderForAzureOpenAI\Metadata\AzureOpenAIModelMetadataDirectory;
use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProviderAvailability;
use Fueled\AiProviderForAzureOpenAI\Tests\Integration\Mocks\MockHttpTransporter;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;

/**
 * Tests for AzureOpenAIProviderAvailability.
 *
 * @covers \Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProviderAvailability
 */
class AzureOpenAIProviderAvailabilityTest extends \WP_UnitTestCase {

	/**
	 * The original AZURE_OPENAI_ENDPOINT value before each test.
	 *
	 * @var string|false
	 */
	private $original_endpoint;

	/**
	 * Shared mock transporter (fresh instance per test).
	 *
	 * @var MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();
		$this->original_endpoint = getenv( 'AZURE_OPENAI_ENDPOINT' );
		putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com' );
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array(
					'name' => 'my-dalle',
					'type' => 'image_dalle',
				),
				array(
					'name' => 'my-gpt4o',
					'type' => 'chat_multimodal',
				),
			)
		);
		$this->transporter = new MockHttpTransporter();
	}

	protected function tearDown(): void {
		if ( false === $this->original_endpoint ) {
			putenv( 'AZURE_OPENAI_ENDPOINT' );
		} else {
			putenv( 'AZURE_OPENAI_ENDPOINT=' . $this->original_endpoint );
		}
		AzureOpenAIModelMetadataDirectory::setDeployments( array() );
		parent::tearDown();
	}

	/**
	 * Builds an availability check with the mock transporter and the given key.
	 *
	 * @param string $api_key The API key.
	 * @return AzureOpenAIProviderAvailability
	 */
	private function make_availability( string $api_key = 'test-api-key' ): AzureOpenAIProviderAvailability {
		$availability = new AzureOpenAIProviderAvailability();
		$availability->setHttpTransporter( $this->transporter );
		$availability->setRequestAuthentication( new ApiKeyRequestAuthentication( $api_key ) );
		return $availability;
	}

	/**
	 * Tests that isConfigured() returns false without making a request when the endpoint is not set.
	 */
	public function test_is_configured_returns_false_when_env_var_not_set(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT' );
		$this->assertFalse( $this->make_availability()->isConfigured() );
		$this->assertNull( $this->transporter->get_last_request() );
	}

	/**
	 * Tests that isConfigured() returns false when AZURE_OPENAI_ENDPOINT is whitespace only.
	 */
	public function test_is_configured_returns_false_when_env_var_is_whitespace(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=   ' );
		$this->assertFalse( $this->make_availability()->isConfigured() );
	}

	/**
	 * Tests that isConfigured() returns false without making a request when there is no API key.
	 */
	public function test_is_configured_returns_false_without_api_key(): void {
		$availability = new AzureOpenAIProviderAvailability();
		$availability->setHttpTransporter( $this->transporter );

		$this->assertFalse( $availability->isConfigured() );
		$this->assertNull( $this->transporter->get_last_request() );
	}

	/**
	 * Tests that a successful call to the first text deployment counts as configured.
	 */
	public function test_is_configured_checks_the_first_text_deployment(): void {
		$this->assertTrue( $this->make_availability()->isConfigured() );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertSame( 'https://myresource.openai.azure.com/openai/v1/responses', $request->getUri() );
		$this->assertSame( 'my-gpt4o', $request->getData()['model'] ?? null );
		$this->assertSame( 'test-api-key', $request->getHeaderAsString( 'api-key' ) );
		$this->assertFalse( $request->hasHeader( 'Authorization' ) );
	}

	/**
	 * Tests that the models list is checked when no text deployment is configured.
	 */
	public function test_is_configured_lists_models_without_a_text_deployment(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array(
					'name' => 'my-dalle',
					'type' => 'image_dalle',
				),
			)
		);

		$this->assertTrue( $this->make_availability()->isConfigured() );
		$this->assertSame( 'https://myresource.openai.azure.com/openai/v1/models', $this->transporter->get_last_request()->getUri() );
	}

	/**
	 * Tests that an error from Azure means not configured, and is not remembered.
	 */
	public function test_is_configured_returns_false_when_azure_rejects_the_key(): void {
		$this->transporter->queue_response( new Response( 401, array(), '{"error":{"code":"401","message":"Access denied"}}' ) );
		$this->assertFalse( $this->make_availability( 'wrong-key' )->isConfigured() );

		// The next check asks again, and the mock now answers with success.
		$this->assertTrue( $this->make_availability( 'wrong-key' )->isConfigured() );
	}

	/**
	 * Tests that a success is remembered, so a repeat check makes no request.
	 */
	public function test_is_configured_remembers_a_success(): void {
		$this->assertTrue( $this->make_availability()->isConfigured() );

		$this->transporter = new MockHttpTransporter();
		$this->assertTrue( $this->make_availability()->isConfigured() );
		$this->assertNull( $this->transporter->get_last_request() );
	}

	/**
	 * Tests that changing the key checks again.
	 */
	public function test_is_configured_checks_again_for_a_different_key(): void {
		$this->assertTrue( $this->make_availability()->isConfigured() );

		$this->transporter = new MockHttpTransporter();
		$this->transporter->queue_response( new Response( 401, array(), '{}' ) );
		$this->assertFalse( $this->make_availability( 'other-key' )->isConfigured() );
	}
}
