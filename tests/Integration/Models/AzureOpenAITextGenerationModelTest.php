<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Models;

use Fueled\AiProviderForAzureOpenAI\Auth\AzureApiKeyRequestAuthentication;
use Fueled\AiProviderForAzureOpenAI\Models\AzureOpenAITextGenerationModel;
use Fueled\AiProviderForAzureOpenAI\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

/**
 * Tests for AzureOpenAITextGenerationModel.
 *
 * Tests the request construction and response parsing behavior of the
 * Azure OpenAI Responses API text generation model.
 *
 * @covers \Fueled\AiProviderForAzureOpenAI\Models\AzureOpenAITextGenerationModel
 */
class AzureOpenAITextGenerationModelTest extends TestCase {

	/**
	 * The model under test.
	 *
	 * @var AzureOpenAITextGenerationModel
	 */
	private AzureOpenAITextGenerationModel $model;

	/**
	 * Shared mock transporter (fresh instance per test).
	 *
	 * @var MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com' );

		$model_metadata    = new ModelMetadata( 'gpt-4o', 'gpt-4o', array(), array() );
		$provider_metadata = new ProviderMetadata( 'azure_openai', 'Azure OpenAI', ProviderTypeEnum::cloud(), null, null );

		$this->transporter = new MockHttpTransporter();
		$this->model       = new AzureOpenAITextGenerationModel( $model_metadata, $provider_metadata );
		$this->model->setHttpTransporter( $this->transporter );
		$this->model->setRequestAuthentication( new AzureApiKeyRequestAuthentication( 'test-api-key' ) );
	}

	protected function tearDown(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT' );
		parent::tearDown();
	}

	/**
	 * Builds a minimal valid Responses API response body.
	 *
	 * @param string $text The text content to include in the response.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Response
	 */
	private function make_text_response( string $text ): Response {
		$body = (string) json_encode(
			array(
				'id'     => 'resp_123',
				'status' => 'completed',
				'output' => array(
					array(
						'type'    => 'message',
						'id'      => 'msg_1',
						'role'    => 'assistant',
						'status'  => 'completed',
						'content' => array(
							array(
								'type' => 'output_text',
								'text' => $text,
							),
						),
					),
				),
				'usage'  => array(
					'input_tokens'  => 10,
					'output_tokens' => 5,
					'total_tokens'  => 15,
				),
			)
		);
		return new Response( 200, array(), $body );
	}

	/**
	 * Builds a simple single-message user prompt.
	 *
	 * @param string $text The prompt text.
	 * @return list<\WordPress\AiClient\Messages\DTO\UserMessage>
	 */
	private function make_prompt( string $text ): array {
		return array( new UserMessage( array( new MessagePart( $text ) ) ) );
	}

	// -----------------------------------------------------------------------
	// Request construction tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that generateTextResult() sends a POST request to the /responses endpoint.
	 */
	public function test_generates_request_to_responses_endpoint(): void {
		$this->transporter->set_response_to_return( $this->make_text_response( 'Hello!' ) );

		$this->model->generateTextResult( $this->make_prompt( 'Say hello.' ) );

		$last_request = $this->transporter->get_last_request();
		$this->assertNotNull( $last_request );
		$this->assertStringContainsString( '/openai/v1/responses', $last_request->getUri() );
	}

	/**
	 * Tests that the request URI starts with the Azure endpoint.
	 */
	public function test_request_uses_azure_endpoint(): void {
		$this->transporter->set_response_to_return( $this->make_text_response( 'Hello!' ) );

		$this->model->generateTextResult( $this->make_prompt( 'Say hello.' ) );

		$last_request = $this->transporter->get_last_request();
		$this->assertNotNull( $last_request );
		$this->assertStringStartsWith( 'https://myresource.openai.azure.com', $last_request->getUri() );
	}

	/**
	 * Tests that the request includes the api-key header (Azure auth format).
	 */
	public function test_request_includes_api_key_header(): void {
		$this->transporter->set_response_to_return( $this->make_text_response( 'Hello!' ) );

		$this->model->generateTextResult( $this->make_prompt( 'Say hello.' ) );

		$last_request = $this->transporter->get_last_request();
		$this->assertNotNull( $last_request );
		$this->assertSame( 'test-api-key', $last_request->getHeaderAsString( 'api-key' ) );
	}

	// -----------------------------------------------------------------------
	// Response parsing tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that generateTextResult() returns a GenerativeAiResult.
	 */
	public function test_returns_generative_ai_result(): void {
		$this->transporter->set_response_to_return( $this->make_text_response( 'Hello!' ) );

		$result = $this->model->generateTextResult( $this->make_prompt( 'Say hello.' ) );

		$this->assertInstanceOf( GenerativeAiResult::class, $result );
	}

	/**
	 * Tests that the result contains at least one candidate.
	 */
	public function test_result_has_at_least_one_candidate(): void {
		$this->transporter->set_response_to_return( $this->make_text_response( 'Hello!' ) );

		$result = $this->model->generateTextResult( $this->make_prompt( 'Say hello.' ) );

		$this->assertGreaterThan( 0, $result->getCandidateCount() );
	}

	/**
	 * Tests that the candidate message contains the expected text from the response.
	 */
	public function test_candidate_contains_response_text(): void {
		$this->transporter->set_response_to_return( $this->make_text_response( 'Hello, world!' ) );

		$result     = $this->model->generateTextResult( $this->make_prompt( 'Say hello.' ) );
		$candidates = $result->getCandidates();
		$parts      = $candidates[0]->getMessage()->getParts();

		$this->assertNotEmpty( $parts );
		$this->assertSame( 'Hello, world!', $parts[0]->getText() );
	}

	/**
	 * Tests that token usage from the response is reported in the result.
	 */
	public function test_result_reports_token_usage(): void {
		$this->transporter->set_response_to_return( $this->make_text_response( 'Hello!' ) );

		$result = $this->model->generateTextResult( $this->make_prompt( 'Say hello.' ) );
		$usage  = $result->getTokenUsage();

		$this->assertSame( 10, $usage->getPromptTokens() );
		$this->assertSame( 5, $usage->getCompletionTokens() );
	}
}
