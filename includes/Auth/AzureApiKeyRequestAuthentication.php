<?php

/**
 * AzureApiKeyRequestAuthentication class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Auth;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Class for Azure OpenAI API key authentication.
 *
 * Azure OpenAI uses the `api-key` header instead of the standard
 * `Authorization: Bearer` header used by OpenAI.
 *
 * @since 1.0.0
 */
class AzureApiKeyRequestAuthentication extends ApiKeyRequestAuthentication {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function authenticateRequest( Request $request ): Request {
		return $request->withHeader( 'api-key', $this->getApiKey() );
	}
}
