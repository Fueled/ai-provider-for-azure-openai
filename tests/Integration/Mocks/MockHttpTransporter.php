<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Mocks;

use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;

/**
 * Mock HTTP transporter for testing.
 *
 * Supports both a single configurable response and a FIFO queue of responses
 * for multi-call scenarios.
 */
class MockHttpTransporter implements HttpTransporterInterface {

	/**
	 * The last request that was sent.
	 *
	 * @var \WordPress\AiClient\Providers\Http\DTO\Request|null
	 */
	private ?Request $last_request = null;

	/**
	 * The fallback response to return when the queue is empty.
	 *
	 * @var \WordPress\AiClient\Providers\Http\DTO\Response|null
	 */
	private ?Response $response_to_return = null;

	/**
	 * FIFO queue of responses. Popped before falling back to $response_to_return.
	 *
	 * @var list<\WordPress\AiClient\Providers\Http\DTO\Response>
	 */
	private array $responses_queue = array();

	/**
	 * {@inheritDoc}
	 */
	public function send( Request $request, ?RequestOptions $options = null ): Response {
		$this->last_request = $request;

		if ( ! empty( $this->responses_queue ) ) {
			return array_shift( $this->responses_queue );
		}

		return $this->response_to_return ?? new Response( 200, array(), '{"status":"success"}' );
	}

	/**
	 * Returns the last request that was sent.
	 *
	 * @return \WordPress\AiClient\Providers\Http\DTO\Request|null The last request, or null if none was sent.
	 */
	public function get_last_request(): ?Request {
		return $this->last_request;
	}

	/**
	 * Sets the fallback response to return when the queue is empty.
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The response to return.
	 */
	public function set_response_to_return( Response $response ): void {
		$this->response_to_return = $response;
	}

	/**
	 * Adds a response to the end of the FIFO queue.
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The response to enqueue.
	 */
	public function queue_response( Response $response ): void {
		$this->responses_queue[] = $response;
	}
}
