<?php

/**
 * AzureOpenAIProviderAvailability class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Provider;

use Fueled\AiProviderForAzureOpenAI\Auth\AzureApiKeyRequestAuthentication;
use Fueled\AiProviderForAzureOpenAI\Metadata\AzureOpenAIModelMetadataDirectory;
use Throwable;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;

/**
 * Availability check for the Azure OpenAI provider.
 *
 * Asks Azure whether the endpoint and API key work, the way ClassifAI authenticates its Azure
 * OpenAI provider: a tiny Responses API call against the first text deployment, or a models
 * list when only non-text deployments are configured.
 *
 * WordPress checks availability when a key is saved and on every load of the Connectors screen,
 * so in WordPress a success is remembered for a day per endpoint, deployment and key. Failures
 * are not remembered, so fixing a setting is picked up right away.
 *
 * @since 1.0.0
 */
class AzureOpenAIProviderAvailability implements ProviderAvailabilityInterface, WithHttpTransporterInterface, WithRequestAuthenticationInterface {

	use WithHttpTransporterTrait;
	use WithRequestAuthenticationTrait;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		$endpoint = getenv( 'AZURE_OPENAI_ENDPOINT' );
		if ( false === $endpoint || '' === trim( $endpoint ) ) {
			return false;
		}

		try {
			$authentication = $this->getRequestAuthentication();
		} catch ( Throwable $e ) {
			// No key has been handed to the provider yet.
			return false;
		}
		if ( ! $authentication instanceof ApiKeyRequestAuthentication || '' === $authentication->getApiKey() ) {
			return false;
		}

		$deployment = $this->get_text_deployment();
		$cache_key  = 'azure_openai_ok_' . md5( implode( '|', array( $endpoint, $deployment, $authentication->getApiKey() ) ) );

		if ( function_exists( 'get_transient' ) && get_transient( $cache_key ) ) {
			return true;
		}

		try {
			$request = '' !== $deployment
				? new Request(
					HttpMethodEnum::POST(),
					AzureOpenAIProvider::url( 'responses' ),
					array( 'Content-Type' => 'application/json' ),
					array(
						'model'             => $deployment,
						'input'             => 'Once upon a time',
						'max_output_tokens' => 16,
					)
				)
				: new Request( HttpMethodEnum::GET(), AzureOpenAIProvider::url( 'models' ) );

			// WordPress hands over a plain API key; Azure wants it in the `api-key` header.
			$request = ( new AzureApiKeyRequestAuthentication( $authentication->getApiKey() ) )->authenticateRequest( $request );

			ResponseUtil::throwIfNotSuccessful( $this->getHttpTransporter()->send( $request ) );
		} catch ( Throwable $e ) {
			return false;
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( $cache_key, 1, DAY_IN_SECONDS );
		}

		return true;
	}

	/**
	 * Returns the first configured text deployment, or an empty string when there is none.
	 *
	 * @since 1.1.0
	 *
	 * @return string The deployment name.
	 */
	private function get_text_deployment(): string {
		foreach ( ( new AzureOpenAIModelMetadataDirectory() )->listModelMetadata() as $model ) {
			foreach ( $model->getSupportedCapabilities() as $capability ) {
				if ( $capability->isTextGeneration() ) {
					return $model->getId();
				}
			}
		}

		return '';
	}
}
