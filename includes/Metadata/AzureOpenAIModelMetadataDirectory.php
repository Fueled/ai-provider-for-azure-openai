<?php

/**
 * AzureOpenAIModelMetadataDirectory class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Metadata;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Class for the Azure OpenAI model metadata directory.
 *
 * Reads deployment configuration set via setDeployments() and returns model
 * metadata based on the configured deployment type. Deployments are loaded
 * from the WordPress option in Plugin::register_provider().
 *
 * @since 1.0.0
 */
class AzureOpenAIModelMetadataDirectory implements ModelMetadataDirectoryInterface {

	/**
	 * Configured deployments.
	 *
	 * Each entry is an associative array with 'name' (string) and 'type' (string) keys.
	 *
	 * @since 1.0.0
	 *
	 * @var array<mixed>
	 */
	private static array $deployments = array();

	/**
	 * Sets the deployments configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $deployments Deployments configuration.
	 */
	public static function setDeployments( array $deployments ): void {
		self::$deployments = $deployments;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata>
	 */
	public function listModelMetadata(): array {
		$models = array();

		foreach ( self::$deployments as $deployment ) {
			if ( ! is_array( $deployment ) ) {
				continue;
			}

			$name = isset( $deployment['name'] ) ? trim( (string) $deployment['name'] ) : '';
			$type = isset( $deployment['type'] ) ? (string) $deployment['type'] : 'chat';

			if ( '' === $name ) {
				continue;
			}

			$models[] = $this->buildModelMetadata( $name, $type );
		}

		return $models;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function hasModelMetadata( string $model_id ): bool {
		foreach ( self::$deployments as $deployment ) {
			if ( ! is_array( $deployment ) ) {
				continue;
			}

			$name = isset( $deployment['name'] ) ? trim( (string) $deployment['name'] ) : '';

			if ( $model_id === $name ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function getModelMetadata( string $model_id ): ModelMetadata {
		foreach ( self::$deployments as $deployment ) {
			if ( ! is_array( $deployment ) ) {
				continue;
			}

			$name = isset( $deployment['name'] ) ? trim( (string) $deployment['name'] ) : '';
			$type = isset( $deployment['type'] ) ? (string) $deployment['type'] : 'chat';

			if ( $model_id === $name ) {
				return $this->buildModelMetadata( $name, $type );
			}
		}

		throw new InvalidArgumentException(
			sprintf( 'No model metadata found for model ID: %s', $model_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		);
	}

	/**
	 * Builds model metadata for a deployment based on its type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Deployment name (used as both ID and display name).
	 * @param string $type Deployment type key.
	 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata Model metadata.
	 */
	private function buildModelMetadata( string $name, string $type ): ModelMetadata {
		switch ( $type ) {
			case 'chat_multimodal':
				return new ModelMetadata(
					$name,
					$name,
					array(
						CapabilityEnum::textGeneration(),
						CapabilityEnum::chatHistory(),
					),
					$this->getChatMultimodalOptions()
				);

			case 'image_dalle':
				return new ModelMetadata(
					$name,
					$name,
					array( CapabilityEnum::imageGeneration() ),
					$this->getDalleImageOptions()
				);

			case 'image_gpt':
				return new ModelMetadata(
					$name,
					$name,
					array( CapabilityEnum::imageGeneration() ),
					$this->getGptImageOptions()
				);

			case 'tts':
				return new ModelMetadata(
					$name,
					$name,
					array( CapabilityEnum::textToSpeechConversion() ),
					$this->getTtsOptions()
				);

			default:
				return new ModelMetadata(
					$name,
					$name,
					array(
						CapabilityEnum::textGeneration(),
						CapabilityEnum::chatHistory(),
					),
					$this->getChatOptions()
				);
		}
	}

	/**
	 * Returns the supported options for chat (text-only) deployments.
	 *
	 * @since 1.0.0
	 *
	 * @return list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption>
	 */
	private function getChatOptions(): array {
		return array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::webSearch() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);
	}

	/**
	 * Returns the supported options for chat multimodal deployments.
	 *
	 * @since 1.0.0
	 *
	 * @return list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption>
	 */
	private function getChatMultimodalOptions(): array {
		return array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::webSearch() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption(
				OptionEnum::inputModalities(),
				array(
					array( ModalityEnum::text() ),
					array( ModalityEnum::text(), ModalityEnum::image() ),
					array( ModalityEnum::text(), ModalityEnum::audio() ),
					array( ModalityEnum::text(), ModalityEnum::document() ),
					array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::audio() ),
					array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::document() ),
					array( ModalityEnum::text(), ModalityEnum::audio(), ModalityEnum::document() ),
					array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::audio(), ModalityEnum::document() ),
				)
			),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);
	}

	/**
	 * Returns the supported options for DALL-E image generation deployments.
	 *
	 * @since 1.0.0
	 *
	 * @return list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption>
	 */
	private function getDalleImageOptions(): array {
		return array(
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'image/png' ) ),
			new SupportedOption( OptionEnum::outputFileType(), array( FileTypeEnum::inline(), FileTypeEnum::remote() ) ),
			new SupportedOption(
				OptionEnum::outputMediaOrientation(),
				array(
					MediaOrientationEnum::square(),
					MediaOrientationEnum::landscape(),
					MediaOrientationEnum::portrait(),
				)
			),
			new SupportedOption( OptionEnum::outputMediaAspectRatio(), array( '1:1', '7:4', '4:7' ) ),
			new SupportedOption( OptionEnum::customOptions() ),
		);
	}

	/**
	 * Returns the supported options for GPT Image generation deployments.
	 *
	 * @since 1.0.0
	 *
	 * @return list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption>
	 */
	private function getGptImageOptions(): array {
		return array(
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'image/png', 'image/jpeg', 'image/webp' ) ),
			new SupportedOption( OptionEnum::outputFileType(), array( FileTypeEnum::inline() ) ),
			new SupportedOption(
				OptionEnum::outputMediaOrientation(),
				array(
					MediaOrientationEnum::square(),
					MediaOrientationEnum::landscape(),
					MediaOrientationEnum::portrait(),
				)
			),
			new SupportedOption( OptionEnum::outputMediaAspectRatio(), array( '1:1', '3:2', '2:3' ) ),
			new SupportedOption( OptionEnum::customOptions() ),
		);
	}

	/**
	 * Returns the supported options for text-to-speech deployments.
	 *
	 * @since 1.0.0
	 *
	 * @return list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption>
	 */
	private function getTtsOptions(): array {
		return array(
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::audio() ) ) ),
			new SupportedOption(
				OptionEnum::outputMimeType(),
				array( 'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/flac', 'audio/aac' )
			),
			new SupportedOption( OptionEnum::outputSpeechVoice() ),
			new SupportedOption( OptionEnum::customOptions() ),
		);
	}
}
