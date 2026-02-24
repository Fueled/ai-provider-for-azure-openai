<?php

/**
 * AzureOpenAIModelMetadataDirectory class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Metadata;

use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProvider;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Class for the Azure OpenAI model metadata directory.
 *
 * Auto-discovers deployed models via the Azure OpenAI v1 /models endpoint.
 * Assigns capabilities based on deployment name patterns. Since Azure deployment
 * names are user-defined, unrecognized names fall back to text generation.
 *
 * @since 1.0.0
 *
 * @phpstan-type ModelsResponseData array{
 *     data: list<array{id: string, lifecycle_status?: string, capabilities?: array<string, bool>}>
 * }
 */
class AzureOpenAIModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request(
			$method,
			AzureOpenAIProvider::url( $path ),
			$headers,
			$data
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		/** @var ModelsResponseData $response_data */
		$response_data = $response->getData();
		if ( ! isset( $response_data['data'] ) || ! $response_data['data'] ) {
			throw ResponseException::fromMissingData( 'Azure OpenAI', 'data' );
		}

		$gpt_capabilities = array(
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		);
		$gpt_base_options = array(
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
		);
		$gpt_options      = array_merge(
			$gpt_base_options,
			array(
				new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
				new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
			)
		);

		$gpt_multimodal_input_options = array_merge(
			$gpt_base_options,
			array(
				new SupportedOption(
					OptionEnum::inputModalities(),
					array(
						array( ModalityEnum::text() ),
						array( ModalityEnum::text(), ModalityEnum::image() ),
						array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::audio() ),
						array( ModalityEnum::text(), ModalityEnum::document() ),
						array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::document() ),
					)
				),
				new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
			)
		);

		$gpt_multimodal_speech_output_options = array_merge(
			$gpt_base_options,
			array(
				new SupportedOption(
					OptionEnum::inputModalities(),
					array(
						array( ModalityEnum::text() ),
						array( ModalityEnum::text(), ModalityEnum::image() ),
						array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::audio() ),
						array( ModalityEnum::text(), ModalityEnum::document() ),
						array( ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::document() ),
					)
				),
				new SupportedOption(
					OptionEnum::outputModalities(),
					array(
						array( ModalityEnum::text() ),
						array( ModalityEnum::text(), ModalityEnum::audio() ),
					)
				),
			)
		);

		$gpt_search_options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);

		$image_capabilities  = array( CapabilityEnum::imageGeneration() );
		$dalle_image_options = array(
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
		$gpt_image_options   = array(
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

		$tts_capabilities = array( CapabilityEnum::textToSpeechConversion() );
		$tts_options      = array(
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::audio() ) ) ),
			new SupportedOption(
				OptionEnum::outputMimeType(),
				array( 'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/flac', 'audio/aac' )
			),
			new SupportedOption( OptionEnum::outputSpeechVoice() ),
			new SupportedOption( OptionEnum::customOptions() ),
		);

		// Only include models that are generally available.
		$models_data = array_values(
			array_filter(
				(array) $response_data['data'],
				static function ( array $model_data ): bool {
					return ! isset( $model_data['lifecycle_status'] ) ||
						'generally-available' === $model_data['lifecycle_status'];
				}
			)
		);

		$models = array_values(
			array_filter(
				array_map(
					static function ( array $model_data ) use (
						$gpt_capabilities,
						$gpt_options,
						$gpt_multimodal_input_options,
						$gpt_multimodal_speech_output_options,
						$gpt_search_options,
						$image_capabilities,
						$dalle_image_options,
						$gpt_image_options,
						$tts_capabilities,
						$tts_options
					): ?ModelMetadata {
						$model_id = $model_data['id'];

						if (
							0 === strpos( $model_id, 'dall-e-' ) ||
							0 === strpos( $model_id, 'gpt-image-' )
						) {
							$model_caps = $image_capabilities;

							if ( 0 === strpos( $model_id, 'gpt-image-' ) ) {
								$model_options = $gpt_image_options;
							} else {
								$model_options = $dalle_image_options;
							}
						} elseif (
							0 === strpos( $model_id, 'tts-' ) ||
							false !== strpos( $model_id, '-tts' )
						) {
							$model_caps    = $tts_capabilities;
							$model_options = $tts_options;
						} elseif (
							( str_starts_with( $model_id, 'gpt-' ) || str_starts_with( $model_id, 'o1-' ) ) &&
							! str_contains( $model_id, '-instruct' ) &&
							! str_contains( $model_id, '-realtime' )
						) {
							if ( str_starts_with( $model_id, 'gpt-4o' ) ) {
								$model_caps    = $gpt_capabilities;
								$model_options = $gpt_multimodal_input_options;

								// New multimodal output model for audio generation.
								if ( str_contains( $model_id, '-audio' ) ) {
									$model_options = $gpt_multimodal_speech_output_options;
								} elseif ( str_contains( $model_id, '-search' ) ) {
									$model_options = $gpt_search_options;
								}
							} elseif ( ! str_contains( $model_id, '-audio' ) ) {
								$model_caps    = $gpt_capabilities;
								$model_options = $gpt_options;
							} else {
								$model_caps    = array();
								$model_options = array();
							}
						} else {
							// Unknown model, only include if the model declares
							// the chat_completion capability in the API response.
							if ( empty( $model_data['capabilities']['chat_completion'] ) ) {
								return null;
							}

							$model_caps    = $gpt_capabilities;
							$model_options = $gpt_options;
						}

						return new ModelMetadata(
							$model_id,
							$model_id,
							$model_caps,
							$model_options
						);
					},
					$models_data
				)
			)
		);

		usort( $models, array( $this, 'modelSortCallback' ) );

		return $models;
	}

	/**
	 * Callback function for sorting models by ID, to be used with `usort()`.
	 *
	 * This method expresses preferences for certain models or model families
	 * within the provider by putting them earlier in the sorted list. The
	 * objective is not to be opinionated about which models are better, but
	 * to ensure that more commonly used, more recent, or flagship models
	 * are presented first to users.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $a First model.
	 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $b Second model.
	 * @return int Comparison result.
	 */
	protected function modelSortCallback( ModelMetadata $a, ModelMetadata $b ): int {
		$a_id = $a->getId();
		$b_id = $b->getId();

		// Prefer non-preview models over preview models.
		if ( str_contains( $a_id, '-preview' ) && ! str_contains( $b_id, '-preview' ) ) {
			return 1;
		}

		if ( str_contains( $b_id, '-preview' ) && ! str_contains( $a_id, '-preview' ) ) {
			return -1;
		}

		// Prefer GPT models over non-GPT models.
		if ( str_starts_with( $a_id, 'gpt-' ) && ! str_starts_with( $b_id, 'gpt-' ) ) {
			return -1;
		}

		if ( str_starts_with( $b_id, 'gpt-' ) && ! str_starts_with( $a_id, 'gpt-' ) ) {
			return 1;
		}

		// Prefer GPT models with version numbers (e.g. 'gpt-5.1', 'gpt-5') over those without.
		$a_match = preg_match( '/^gpt-([0-9.]+)(-[a-z0-9-]+)?$/', $a_id, $a_matches );
		$b_match = preg_match( '/^gpt-([0-9.]+)(-[a-z0-9-]+)?$/', $b_id, $b_matches );

		if ( $a_match && ! $b_match ) {
			return -1;
		}

		if ( $b_match && ! $a_match ) {
			return 1;
		}

		if ( $a_match && $b_match ) {
			// Prefer later model versions.
			$a_version = $a_matches[1];
			$b_version = $b_matches[1];

			if ( version_compare( $a_version, $b_version, '>' ) ) {
				return -1;
			}

			if ( version_compare( $b_version, $a_version, '>' ) ) {
				return 1;
			}

			// Prefer models without a suffix (i.e. base models) over those with a suffix.
			if ( ! isset( $a_matches[2] ) && isset( $b_matches[2] ) ) {
				return -1;
			}

			if ( ! isset( $b_matches[2] ) && isset( $a_matches[2] ) ) {
				return 1;
			}

			// Prefer '-mini' models over others with a suffix.
			if ( isset( $a_matches[2] ) && isset( $b_matches[2] ) ) {
				if ( '-mini' === $a_matches[2] && '-mini' !== $b_matches[2] ) {
					return -1;
				}

				if ( '-mini' === $b_matches[2] && '-mini' !== $a_matches[2] ) {
					return 1;
				}
			}
		}

		// Fallback: Sort alphabetically.
		return strcmp( $a->getId(), $b->getId() );
	}
}
