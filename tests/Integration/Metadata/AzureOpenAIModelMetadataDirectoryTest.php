<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Metadata;

use Fueled\AiProviderForAzureOpenAI\Metadata\AzureOpenAIModelMetadataDirectory;
use Fueled\AiProviderForAzureOpenAI\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;

/**
 * Tests for AzureOpenAIModelMetadataDirectory.
 *
 * Uses a MockHttpTransporter with a pre-configured response matching the
 * Azure OpenAI v1 /models response shape.
 *
 * @covers \Fueled\AiProviderForAzureOpenAI\Metadata\AzureOpenAIModelMetadataDirectory
 */
class AzureOpenAIModelMetadataDirectoryTest extends TestCase {

	/**
	 * Directory under test.
	 *
	 * @var AzureOpenAIModelMetadataDirectory
	 */
	private AzureOpenAIModelMetadataDirectory $directory;

	/**
	 * Shared mock transporter (fresh instance per test).
	 *
	 * @var MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com' );
		$this->transporter = new MockHttpTransporter();
		$this->directory   = new AzureOpenAIModelMetadataDirectory();
		$this->directory->setHttpTransporter( $this->transporter );
		$this->directory->setRequestAuthentication( new ApiKeyRequestAuthentication( 'test-key' ) );
	}

	protected function tearDown(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT' );
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Response helpers
	// -----------------------------------------------------------------------

	/**
	 * Builds a fake /models 200 response containing the given model IDs.
	 *
	 * All models are set to 'generally-available' by default.
	 *
	 * @param list<string> $model_ids The model IDs (deployment names) to include.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Response
	 */
	private function make_models_response( array $model_ids ): Response {
		$data = array_map(
			static function ( string $id ): array {
				return array(
					'id'               => $id,
					'lifecycle_status' => 'generally-available',
				);
			},
			$model_ids
		);
		$body = (string) json_encode( array( 'data' => $data ) );
		return new Response( 200, array(), $body );
	}

	/**
	 * Builds a fake /models 200 response with explicit lifecycle statuses per model.
	 *
	 * @param list<array{id: string, lifecycle_status: string}> $models Model data with id and lifecycle_status.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Response
	 */
	private function make_models_response_with_status( array $models ): Response {
		$body = (string) json_encode( array( 'data' => $models ) );
		return new Response( 200, array(), $body );
	}

	/**
	 * Finds a SupportedOption by its is* method name, or returns null if not found.
	 *
	 * @param list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption> $options         Supported options.
	 * @param string                                                          $is_method_name The is* method name (e.g. 'isInputModalities').
	 * @return \WordPress\AiClient\Providers\Models\DTO\SupportedOption|null
	 */
	private function find_option( array $options, string $is_method_name ): ?\WordPress\AiClient\Providers\Models\DTO\SupportedOption {
		foreach ( $options as $opt ) {
			if ( $opt->getName()->$is_method_name() ) {
				return $opt;
			}
		}
		return null;
	}

	// -----------------------------------------------------------------------
	// Lifecycle status filtering tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that deprecated models are excluded from the list.
	 */
	public function test_deprecated_models_are_excluded(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response_with_status(
				array(
					array( 'id' => 'gpt-4o', 'lifecycle_status' => 'generally-available' ),
					array( 'id' => 'gpt-35-turbo', 'lifecycle_status' => 'deprecated' ),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'gpt-4o', $models[0]->getId() );
	}

	/**
	 * Tests that preview models are excluded from the list.
	 */
	public function test_preview_models_are_excluded(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response_with_status(
				array(
					array( 'id' => 'gpt-4o', 'lifecycle_status' => 'generally-available' ),
					array( 'id' => 'gpt-4o-preview', 'lifecycle_status' => 'preview' ),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'gpt-4o', $models[0]->getId() );
	}

	/**
	 * Tests that only generally-available models are included when the response contains all statuses.
	 */
	public function test_only_generally_available_models_are_returned(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response_with_status(
				array(
					array( 'id' => 'gpt-4o', 'lifecycle_status' => 'generally-available' ),
					array( 'id' => 'dall-e-3', 'lifecycle_status' => 'generally-available' ),
					array( 'id' => 'gpt-35-turbo', 'lifecycle_status' => 'deprecated' ),
					array( 'id' => 'gpt-4o-preview', 'lifecycle_status' => 'preview' ),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 2, $models );
		$ids = array_map( static function ( $m ) { return $m->getId(); }, $models );
		$this->assertContains( 'gpt-4o', $ids );
		$this->assertContains( 'dall-e-3', $ids );
	}

	/**
	 * Tests that models without a lifecycle_status field are included (safe fallback).
	 */
	public function test_models_without_lifecycle_status_are_included(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response_with_status(
				array(
					array( 'id' => 'gpt-4o' ),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'gpt-4o', $models[0]->getId() );
	}

	// -----------------------------------------------------------------------
	// Basic listing tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that listModelMetadata() returns models parsed from the API response.
	 */
	public function test_returns_models_from_api(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-4o' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'gpt-4o', $models[0]->getId() );
	}

	/**
	 * Tests that returned models are sorted alphabetically by model ID.
	 */
	public function test_models_are_sorted_alphabetically(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response( array( 'gpt-4o', 'gpt-35-turbo' ) )
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 2, $models );
		$this->assertSame( 'gpt-35-turbo', $models[0]->getId() );
		$this->assertSame( 'gpt-4o', $models[1]->getId() );
	}

	/**
	 * Tests that the model display name matches the model ID.
	 */
	public function test_model_display_name_matches_id(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-4o' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertSame( 'gpt-4o', $models[0]->getName() );
	}

	// -----------------------------------------------------------------------
	// Capability detection tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a dall-e model is detected as an image generation model.
	 */
	public function test_dall_e_model_has_image_generation_capability(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'dall-e-3' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$caps = $models[0]->getSupportedCapabilities();
		$this->assertCount( 1, $caps );
		$this->assertTrue( $caps[0]->isImageGeneration() );
	}

	/**
	 * Tests that a gpt-image model is detected as an image generation model.
	 */
	public function test_gpt_image_model_has_image_generation_capability(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-image-1' ) ) );

		$models = $this->directory->listModelMetadata();

		$caps = $models[0]->getSupportedCapabilities();
		$this->assertCount( 1, $caps );
		$this->assertTrue( $caps[0]->isImageGeneration() );
	}

	/**
	 * Tests that a tts- prefixed model is detected as a TTS model.
	 */
	public function test_tts_prefix_model_has_tts_capability(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'tts-1' ) ) );

		$models = $this->directory->listModelMetadata();

		$caps = $models[0]->getSupportedCapabilities();
		$this->assertCount( 1, $caps );
		$this->assertTrue( $caps[0]->isTextToSpeechConversion() );
	}

	/**
	 * Tests that a model with -tts suffix is detected as a TTS model.
	 */
	public function test_tts_suffix_model_has_tts_capability(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-4o-mini-tts' ) ) );

		$models = $this->directory->listModelMetadata();

		$caps = $models[0]->getSupportedCapabilities();
		$this->assertCount( 1, $caps );
		$this->assertTrue( $caps[0]->isTextToSpeechConversion() );
	}

	/**
	 * Tests that a gpt-4o model has text generation capability.
	 */
	public function test_gpt4o_model_has_text_generation_capability(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-4o' ) ) );

		$models = $this->directory->listModelMetadata();

		$caps = $models[0]->getSupportedCapabilities();
		$has_text_gen = false;
		foreach ( $caps as $cap ) {
			if ( $cap->isTextGeneration() ) {
				$has_text_gen = true;
				break;
			}
		}
		$this->assertTrue( $has_text_gen );
	}

	/**
	 * Tests that a gpt-4o model gets multimodal input modalities (5 combinations).
	 */
	public function test_gpt4o_model_has_multimodal_input_modalities(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-4o' ) ) );

		$models              = $this->directory->listModelMetadata();
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );

		$this->assertNotNull( $input_modalities_opt, 'Expected inputModalities supported option' );
		// gpt-4o: text, text+image, text+image+audio, text+document, text+image+document.
		$this->assertCount( 5, (array) $input_modalities_opt->getSupportedValues() );
	}

	/**
	 * Tests that a gpt-4o-mini model also gets multimodal input modalities.
	 */
	public function test_gpt4o_mini_model_has_multimodal_input_modalities(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-4o-mini' ) ) );

		$models              = $this->directory->listModelMetadata();
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );

		$this->assertNotNull( $input_modalities_opt );
		$this->assertCount( 5, (array) $input_modalities_opt->getSupportedValues() );
	}

	/**
	 * Tests that a gpt-3.5 model has text-only input modalities (not multimodal).
	 */
	public function test_gpt35_model_has_text_only_input_modalities(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-35-turbo' ) ) );

		$models              = $this->directory->listModelMetadata();
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );

		$this->assertNotNull( $input_modalities_opt, 'Expected inputModalities supported option' );
		$this->assertCount( 1, (array) $input_modalities_opt->getSupportedValues() );
	}

	/**
	 * Tests that an o1- prefixed model has text generation capability.
	 */
	public function test_o1_model_has_text_generation_capability(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'o1-mini' ) ) );

		$models = $this->directory->listModelMetadata();

		$has_text_gen = false;
		foreach ( $models[0]->getSupportedCapabilities() as $cap ) {
			if ( $cap->isTextGeneration() ) {
				$has_text_gen = true;
				break;
			}
		}
		$this->assertTrue( $has_text_gen );
	}

	/**
	 * Tests that an o3- prefixed model has text generation capability.
	 */
	public function test_o3_model_has_text_generation_capability(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response_with_status(
				array(
					array(
						'id'               => 'o3-mini',
						'lifecycle_status' => 'generally-available',
						'capabilities'     => array( 'chat_completion' => true ),
					),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$has_text_gen = false;
		foreach ( $models[0]->getSupportedCapabilities() as $cap ) {
			if ( $cap->isTextGeneration() ) {
				$has_text_gen = true;
				break;
			}
		}
		$this->assertTrue( $has_text_gen );
	}

	/**
	 * Tests that an o4- prefixed model has text generation capability.
	 */
	public function test_o4_model_has_text_generation_capability(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response_with_status(
				array(
					array(
						'id'               => 'o4-mini',
						'lifecycle_status' => 'generally-available',
						'capabilities'     => array( 'chat_completion' => true ),
					),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$has_text_gen = false;
		foreach ( $models[0]->getSupportedCapabilities() as $cap ) {
			if ( $cap->isTextGeneration() ) {
				$has_text_gen = true;
				break;
			}
		}
		$this->assertTrue( $has_text_gen );
	}

	/**
	 * Tests that an unrecognized deployment name with chat_completion capability is included as text generation.
	 */
	public function test_unknown_model_name_with_chat_completion_capability_is_included(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response_with_status(
				array(
					array(
						'id'               => 'my-custom-deployment',
						'lifecycle_status' => 'generally-available',
						'capabilities'     => array( 'chat_completion' => true ),
					),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$has_text_gen = false;
		foreach ( $models[0]->getSupportedCapabilities() as $cap ) {
			if ( $cap->isTextGeneration() ) {
				$has_text_gen = true;
				break;
			}
		}
		$this->assertTrue( $has_text_gen, 'Unknown deployment with chat_completion should be included as text generation' );
	}

	/**
	 * Tests that an unrecognized deployment name without chat_completion capability is excluded.
	 */
	public function test_unknown_model_name_without_chat_completion_capability_is_excluded(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response_with_status(
				array(
					array(
						'id'               => 'my-embedding-model',
						'lifecycle_status' => 'generally-available',
						'capabilities'     => array( 'chat_completion' => false ),
					),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 0, $models );
	}

	/**
	 * Tests that an unrecognized deployment name with no capabilities field is excluded.
	 */
	public function test_unknown_model_name_with_no_capabilities_is_excluded(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response_with_status(
				array(
					array(
						'id'               => 'my-unknown-model',
						'lifecycle_status' => 'generally-available',
					),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 0, $models );
	}

	// -----------------------------------------------------------------------
	// Error handling tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a response missing the 'data' key throws a ResponseException.
	 */
	public function test_missing_data_key_throws_exception(): void {
		$this->transporter->set_response_to_return(
			new Response( 200, array(), (string) json_encode( array( 'not_data' => array() ) ) )
		);

		$this->expectException( ResponseException::class );
		$this->directory->listModelMetadata();
	}

	/**
	 * Tests that an empty 'data' array throws a ResponseException.
	 */
	public function test_empty_data_array_throws_exception(): void {
		$this->transporter->set_response_to_return(
			new Response( 200, array(), (string) json_encode( array( 'data' => array() ) ) )
		);

		$this->expectException( ResponseException::class );
		$this->directory->listModelMetadata();
	}

	/**
	 * Tests that a failed /models request propagates the exception.
	 */
	public function test_failed_models_request_throws_exception(): void {
		$this->transporter->set_response_to_return(
			new Response( 401, array(), '{"error":"Unauthorized"}' )
		);

		$this->expectException( \Throwable::class );
		$this->directory->listModelMetadata();
	}

	// -----------------------------------------------------------------------
	// Options completeness tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that all standard text generation options are present on a GPT model.
	 */
	public function test_all_standard_gpt_options_are_present(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-4o' ) ) );

		$models = $this->directory->listModelMetadata();
		$this->assertCount( 1, $models );

		$option_names = array_map(
			static function ( $opt ): string {
				return (string) $opt->getName();
			},
			$models[0]->getSupportedOptions()
		);

		$expected_options = array(
			'systemInstruction',
			'candidateCount',
			'maxTokens',
			'temperature',
			'topP',
			'stopSequences',
			'presencePenalty',
			'frequencyPenalty',
			'outputMimeType',
			'outputSchema',
			'functionDeclarations',
			'webSearch',
			'customOptions',
			'inputModalities',
			'outputModalities',
		);

		foreach ( $expected_options as $expected ) {
			$this->assertContains(
				$expected,
				$option_names,
				sprintf( 'Expected option "%s" to be present in model metadata', $expected )
			);
		}
	}

	/**
	 * Tests that the request is sent to the correct Azure /models endpoint.
	 */
	public function test_request_is_sent_to_correct_endpoint(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'gpt-4o' ) ) );

		$this->directory->listModelMetadata();

		$last_request = $this->transporter->get_last_request();
		$this->assertNotNull( $last_request );
		$this->assertStringContainsString( '/openai/v1/models', $last_request->getUri() );
	}
}
