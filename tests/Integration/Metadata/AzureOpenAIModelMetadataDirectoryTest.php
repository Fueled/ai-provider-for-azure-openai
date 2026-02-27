<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Metadata;

use Fueled\AiProviderForAzureOpenAI\Metadata\AzureOpenAIModelMetadataDirectory;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;

/**
 * Tests for AzureOpenAIModelMetadataDirectory.
 *
 * Uses static deployment configuration rather than HTTP discovery.
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

	protected function setUp(): void {
		parent::setUp();
		$this->directory = new AzureOpenAIModelMetadataDirectory();
	}

	protected function tearDown(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments( array() );
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helper
	// -----------------------------------------------------------------------

	/**
	 * Finds a SupportedOption by its is* method name, or returns null if not found.
	 *
	 * @param list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption> $options        Supported options.
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
	// Basic listing tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that listModelMetadata() returns an empty array when no deployments are configured.
	 */
	public function test_returns_empty_array_when_no_deployments_configured(): void {
		$models = $this->directory->listModelMetadata();
		$this->assertSame( array(), $models );
	}

	/**
	 * Tests that listModelMetadata() returns models from the configured deployments.
	 */
	public function test_returns_models_from_configured_deployments(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-gpt4o', 'type' => 'chat' ),
				array( 'name' => 'my-dall-e', 'type' => 'image_dalle' ),
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 2, $models );
		$ids = array_map( static function ( $m ) { return $m->getId(); }, $models );
		$this->assertContains( 'my-gpt4o', $ids );
		$this->assertContains( 'my-dall-e', $ids );
	}

	/**
	 * Tests that blank deployment names are skipped.
	 */
	public function test_blank_deployment_names_are_skipped(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => '', 'type' => 'chat' ),
				array( 'name' => 'valid-name', 'type' => 'chat' ),
				array( 'name' => '   ', 'type' => 'chat' ),
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'valid-name', $models[0]->getId() );
	}

	/**
	 * Tests that the model display name matches the deployment name.
	 */
	public function test_model_display_name_matches_deployment_name(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-custom-deployment', 'type' => 'chat' ),
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertSame( 'my-custom-deployment', $models[0]->getName() );
	}

	// -----------------------------------------------------------------------
	// Chat deployment capability tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a chat deployment has textGeneration and chatHistory capabilities.
	 */
	public function test_chat_deployment_has_text_generation_and_chat_history_capabilities(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-chat', 'type' => 'chat' ),
			)
		);

		$models = $this->directory->listModelMetadata();
		$caps   = $models[0]->getSupportedCapabilities();

		$has_text_gen   = false;
		$has_chat_history = false;
		foreach ( $caps as $cap ) {
			if ( $cap->isTextGeneration() ) {
				$has_text_gen = true;
			}
			if ( $cap->isChatHistory() ) {
				$has_chat_history = true;
			}
		}

		$this->assertTrue( $has_text_gen, 'Expected textGeneration capability' );
		$this->assertTrue( $has_chat_history, 'Expected chatHistory capability' );
	}

	/**
	 * Tests that a chat deployment has text-only input modality (1 combination).
	 */
	public function test_chat_deployment_has_text_only_input_modality(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-chat', 'type' => 'chat' ),
			)
		);

		$models              = $this->directory->listModelMetadata();
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );

		$this->assertNotNull( $input_modalities_opt, 'Expected inputModalities option' );
		$this->assertCount( 1, (array) $input_modalities_opt->getSupportedValues() );
	}

	// -----------------------------------------------------------------------
	// Chat multimodal deployment capability tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a chat_multimodal deployment has textGeneration capability.
	 */
	public function test_chat_multimodal_deployment_has_text_generation_capability(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-multimodal', 'type' => 'chat_multimodal' ),
			)
		);

		$models = $this->directory->listModelMetadata();
		$caps   = $models[0]->getSupportedCapabilities();

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
	 * Tests that a chat_multimodal deployment has 5 input modality combinations.
	 */
	public function test_chat_multimodal_deployment_has_multimodal_input_modalities(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-multimodal', 'type' => 'chat_multimodal' ),
			)
		);

		$models              = $this->directory->listModelMetadata();
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );

		$this->assertNotNull( $input_modalities_opt, 'Expected inputModalities option' );
		$this->assertCount( 8, (array) $input_modalities_opt->getSupportedValues() );
	}

	// -----------------------------------------------------------------------
	// Image deployment capability tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that an image_dalle deployment has imageGeneration capability.
	 */
	public function test_image_dalle_deployment_has_image_generation_capability(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-dalle', 'type' => 'image_dalle' ),
			)
		);

		$models = $this->directory->listModelMetadata();
		$caps   = $models[0]->getSupportedCapabilities();

		$this->assertCount( 1, $caps );
		$this->assertTrue( $caps[0]->isImageGeneration() );
	}

	/**
	 * Tests that an image_gpt deployment has imageGeneration capability.
	 */
	public function test_image_gpt_deployment_has_image_generation_capability(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-gpt-image', 'type' => 'image_gpt' ),
			)
		);

		$models = $this->directory->listModelMetadata();
		$caps   = $models[0]->getSupportedCapabilities();

		$this->assertCount( 1, $caps );
		$this->assertTrue( $caps[0]->isImageGeneration() );
	}

	// -----------------------------------------------------------------------
	// TTS deployment capability tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a tts deployment has textToSpeechConversion capability.
	 */
	public function test_tts_deployment_has_tts_capability(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-tts', 'type' => 'tts' ),
			)
		);

		$models = $this->directory->listModelMetadata();
		$caps   = $models[0]->getSupportedCapabilities();

		$this->assertCount( 1, $caps );
		$this->assertTrue( $caps[0]->isTextToSpeechConversion() );
	}

	// -----------------------------------------------------------------------
	// hasModelMetadata() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that hasModelMetadata() returns true for a configured deployment.
	 */
	public function test_has_model_metadata_returns_true_for_configured_deployment(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-gpt4o', 'type' => 'chat' ),
			)
		);

		$this->assertTrue( $this->directory->hasModelMetadata( 'my-gpt4o' ) );
	}

	/**
	 * Tests that hasModelMetadata() returns false for an unconfigured deployment.
	 */
	public function test_has_model_metadata_returns_false_for_unconfigured_deployment(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-gpt4o', 'type' => 'chat' ),
			)
		);

		$this->assertFalse( $this->directory->hasModelMetadata( 'nonexistent' ) );
	}

	// -----------------------------------------------------------------------
	// getModelMetadata() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that getModelMetadata() returns the correct metadata for a configured deployment.
	 */
	public function test_get_model_metadata_returns_correct_metadata(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-gpt4o', 'type' => 'chat' ),
			)
		);

		$metadata = $this->directory->getModelMetadata( 'my-gpt4o' );

		$this->assertSame( 'my-gpt4o', $metadata->getId() );
		$this->assertSame( 'my-gpt4o', $metadata->getName() );
	}

	/**
	 * Tests that getModelMetadata() throws an exception for an unconfigured deployment.
	 */
	public function test_get_model_metadata_throws_for_unconfigured_deployment(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->directory->getModelMetadata( 'nonexistent' );
	}

	// -----------------------------------------------------------------------
	// Default type fallback test
	// -----------------------------------------------------------------------

	/**
	 * Tests that an unknown deployment type falls back to the chat (text generation) type.
	 */
	public function test_unknown_type_falls_back_to_chat(): void {
		AzureOpenAIModelMetadataDirectory::setDeployments(
			array(
				array( 'name' => 'my-deployment', 'type' => 'unknown_type' ),
			)
		);

		$models = $this->directory->listModelMetadata();
		$caps   = $models[0]->getSupportedCapabilities();

		$has_text_gen = false;
		foreach ( $caps as $cap ) {
			if ( $cap->isTextGeneration() ) {
				$has_text_gen = true;
				break;
			}
		}

		$this->assertTrue( $has_text_gen, 'Unknown type should fall back to chat (textGeneration)' );
	}
}
