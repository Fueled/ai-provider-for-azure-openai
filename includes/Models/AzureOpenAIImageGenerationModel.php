<?php

/**
 * AzureOpenAIImageGenerationModel class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Models;

use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProvider;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;

/**
 * Class for an Azure OpenAI image generation model using the Images API.
 *
 * Supports DALL-E and GPT Image deployments via the Azure OpenAI v1
 * `/images/generations` endpoint.
 *
 * @since 1.0.0
 */
class AzureOpenAIImageGenerationModel extends AbstractOpenAiCompatibleImageGenerationModel {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = array(),
		$data = null
	): Request {
		return new Request(
			$method,
			AzureOpenAIProvider::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function prepareGenerateImageParams( array $prompt ): array {
		$params = parent::prepareGenerateImageParams( $prompt );

		/*
		 * Only the newer 'gpt-image-' models support passing a MIME type ('output_format').
		 * Conversely, they do not support 'response_format', but always return a base64 encoded image.
		 */
		if ( $this->isGptImageModel( (string) $params['model'] ) ) {
			unset( $params['response_format'] );
		} else {
			unset( $params['output_format'] );
		}

		return $params;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function prepareSizeParam( ?MediaOrientationEnum $orientation, ?string $aspect_ratio ): string {
		$model_id = $this->metadata()->getId();

		if ( $this->isGptImageModel( $model_id ) ) {
			return $this->prepareGptImageSizeParam( $orientation, $aspect_ratio );
		}

		return $this->prepareDalleSizeParam( $model_id, $orientation, $aspect_ratio );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function getResultId( array $response_data ): string {
		// The Images API returns `created` timestamp instead of `id`.
		return isset( $response_data['created'] ) && is_int( $response_data['created'] )
			? 'img-' . $response_data['created']
			: '';
	}

	/**
	 * Checks if the given model ID is a GPT image model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id The model ID to check.
	 * @return bool True if it's a GPT image model, false otherwise.
	 */
	protected function isGptImageModel( string $model_id ): bool {
		return 0 === strpos( $model_id, 'gpt-image-' );
	}

	/**
	 * Prepares the size parameter for GPT image models.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Files\Enums\MediaOrientationEnum|null $orientation  The desired media orientation.
	 * @param string|null               $aspect_ratio The desired media aspect ratio.
	 * @return string The size parameter value.
	 */
	protected function prepareGptImageSizeParam( ?MediaOrientationEnum $orientation, ?string $aspect_ratio ): string {
		// If aspect ratio is provided, map it to size format.
		if ( null !== $aspect_ratio ) {
			$aspect_ratio_map = array(
				'1:1' => '1024x1024',
				'3:2' => '1536x1024',
				'2:3' => '1024x1536',
			);

			if ( isset( $aspect_ratio_map[ $aspect_ratio ] ) ) {
				return $aspect_ratio_map[ $aspect_ratio ];
			}
		}

		// Map orientation to size.
		if ( null !== $orientation ) {
			if ( $orientation->isLandscape() ) {
				return '1536x1024';
			}

			if ( $orientation->isPortrait() ) {
				return '1024x1536';
			}
		}

		// Default to square.
		return '1024x1024';
	}

	/**
	 * Prepares the size parameter for DALL-E models.
	 *
	 * @since 1.0.0
	 *
	 * @param string                    $model_id     The model ID (dall-e-2 or dall-e-3).
	 * @param \WordPress\AiClient\Files\Enums\MediaOrientationEnum|null $orientation  The desired media orientation.
	 * @param string|null               $aspect_ratio The desired media aspect ratio.
	 * @return string The size parameter value.
	 */
	protected function prepareDalleSizeParam( string $model_id, ?MediaOrientationEnum $orientation, ?string $aspect_ratio ): string {
		$is_dalle3 = 'dall-e-3' === $model_id;

		// If aspect ratio is provided, map it to size.
		if ( null !== $aspect_ratio ) {
			if ( $is_dalle3 ) {
				$aspect_ratio_map = array(
					'1:1' => '1024x1024',
					'7:4' => '1792x1024',
					'4:7' => '1024x1792',
				);
			} else {
				// DALL-E 2 only supports square images at various resolutions.
				$aspect_ratio_map = array( '1:1' => '1024x1024' );
			}

			if ( isset( $aspect_ratio_map[ $aspect_ratio ] ) ) {
				return $aspect_ratio_map[ $aspect_ratio ];
			}
		}

		// Map orientation to size.
		if ( null !== $orientation && $is_dalle3 ) {
			if ( $orientation->isLandscape() ) {
				return '1792x1024';
			}

			if ( $orientation->isPortrait() ) {
				return '1024x1792';
			}
		}

		// Default to square.
		return '1024x1024';
	}
}
