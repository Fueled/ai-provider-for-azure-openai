<?php

/**
 * AzureOpenAIProviderAvailability class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Provider;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * Availability check for the Azure OpenAI provider.
 *
 * Returns configured if the AZURE_OPENAI_ENDPOINT environment variable is set
 * and non-empty. Actual connectivity errors surface when listing models or
 * generating text.
 *
 * @since 1.0.0
 */
class AzureOpenAIProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		$endpoint = getenv( 'AZURE_OPENAI_ENDPOINT' );
		return false !== $endpoint && '' !== trim( $endpoint );
	}
}
