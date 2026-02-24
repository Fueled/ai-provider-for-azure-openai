<?php

/**
 * AzureOpenAITextGenerationModel class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Models;

use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProvider;
use Generator;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\WebSearch;

/**
 * Class for an Azure OpenAI text generation model using the Responses API.
 *
 * Uses the Azure OpenAI v1 Responses API (`/openai/v1/responses`), which
 * shares the same request/response format as the OpenAI Responses API.
 *
 * @since 1.0.0
 *
 * @phpstan-type OutputContentData array{
 *     type: string,
 *     text?: string,
 *     call_id?: string,
 *     name?: string,
 *     arguments?: string
 * }
 * @phpstan-type OutputItemData array{
 *     type: string,
 *     id?: string,
 *     role?: string,
 *     status?: string,
 *     content?: list<OutputContentData>
 * }
 * @phpstan-type UsageData array{
 *     input_tokens?: int,
 *     output_tokens?: int,
 *     total_tokens?: int
 * }
 * @phpstan-type ResponseData array{
 *     id?: string,
 *     status?: string,
 *     output?: list<OutputItemData>,
 *     output_text?: string,
 *     usage?: UsageData
 * }
 */
class AzureOpenAITextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	final public function generateTextResult( array $prompt ): GenerativeAiResult {
		$http_transporter = $this->getHttpTransporter();

		$params = $this->prepareGenerateTextParams( $prompt );

		$request = new Request(
			HttpMethodEnum::POST(),
			AzureOpenAIProvider::url( 'responses' ),
			array( 'Content-Type' => 'application/json' ),
			$params,
			$this->getRequestOptions()
		);

		// Add authentication credentials to the request.
		$request = $this->getRequestAuthentication()->authenticateRequest( $request );

		// Send and process the request.
		$response = $http_transporter->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToGenerativeAiResult( $response );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt to generate text for.
	 * @return \Generator<\WordPress\AiClient\Results\DTO\GenerativeAiResult>
	 */
	final public function streamGenerateTextResult( array $prompt ): Generator {
		// TODO: Implement streaming support.
		throw new RuntimeException( 'Streaming is not yet implemented.' );
	}

	/**
	 * Prepares the given prompt and the model configuration into parameters for the API request.
	 *
	 * @since 1.0.0
	 *
	 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt to generate text for. Either a single message or a list of messages
	 *                              from a chat.
	 * @return array<string, mixed> The parameters for the API request.
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$config = $this->getConfig();

		$params = array(
			'model' => $this->metadata()->getId(),
			'input' => $this->prepareInputParam( $prompt ),
		);

		$system_instruction = $config->getSystemInstruction();
		if ( $system_instruction ) {
			$params['instructions'] = $system_instruction;
		}

		$max_tokens = $config->getMaxTokens();
		if ( null !== $max_tokens ) {
			$params['max_output_tokens'] = $max_tokens;
		}

		$temperature = $config->getTemperature();
		if ( null !== $temperature ) {
			$params['temperature'] = $temperature;
		}

		$top_p = $config->getTopP();
		if ( null !== $top_p ) {
			$params['top_p'] = $top_p;
		}

		// Note: Azure OpenAI does not support top_k parameter.

		$output_mime_type = $config->getOutputMimeType();
		$output_schema    = $config->getOutputSchema();
		if ( 'application/json' === $output_mime_type && $output_schema ) {
			$params['text'] = array(
				'format' => array(
					'type'   => 'json_schema',
					'name'   => 'response_schema',
					'schema' => $output_schema,
					'strict' => true,
				),
			);
		}

		$function_declarations = $config->getFunctionDeclarations();
		$web_search            = $config->getWebSearch();

		if ( is_array( $function_declarations ) || $web_search ) {
			$params['tools'] = $this->prepareToolsParam(
				$function_declarations,
				$web_search
			);
		}

		/*
		 * Any custom options are added to the parameters as well.
		 * This allows developers to pass other options that may be more niche or not yet supported by the SDK.
		 */
		$custom_options = $config->getCustomOptions();
		foreach ( $custom_options as $key => $value ) {
			if ( isset( $params[ $key ] ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'The custom option "%s" conflicts with an existing parameter.',
						$key // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
					)
				);
			}
			$params[ $key ] = $value;
		}

		return $params;
	}

	/**
	 * Prepares the input parameter for the API request.
	 *
	 * @since 1.0.0
	 *
	 * @param list<\WordPress\AiClient\Messages\DTO\Message> $messages The messages to prepare.
	 * @return list<array<string, mixed>> The prepared input parameter.
	 */
	protected function prepareInputParam( array $messages ): array {
		$this->validateMessages( $messages );

		$input = array();
		foreach ( $messages as $message ) {
			$input_item = $this->getMessageInputItem( $message );
			if ( null === $input_item ) {
				continue;
			}

			$input[] = $input_item;
		}

		return $input;
	}

	/**
	 * Validates that the messages are appropriate for the Responses API.
	 *
	 * The Responses API requires function calls and function responses to be
	 * sent as top-level input items rather than nested in message content. As such,
	 * they must be the only part in a message.
	 *
	 * @since 1.0.0
	 *
	 * @param list<\WordPress\AiClient\Messages\DTO\Message> $messages The messages to validate.
	 * @return void
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If validation fails.
	 */
	protected function validateMessages( array $messages ): void {
		foreach ( $messages as $message ) {
			$parts = $message->getParts();

			if ( count( $parts ) <= 1 ) {
				continue;
			}

			foreach ( $parts as $part ) {
				$type = $part->getType();

				if ( $type->isFunctionCall() ) {
					throw new InvalidArgumentException(
						'Function call parts must be the only part in a message for the Responses API.'
					);
				}

				if ( $type->isFunctionResponse() ) {
					throw new InvalidArgumentException(
						'Function response parts must be the only part in a message for the Responses API.'
					);
				}
			}
		}
	}

	/**
	 * Converts a Message object to a Responses API input item.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Messages\DTO\Message $message The message to convert.
	 * @return array<string, mixed>|null The input item, or null if the message is empty.
	 */
	protected function getMessageInputItem( Message $message ): ?array {
		$parts = $message->getParts();

		if ( empty( $parts ) ) {
			return null;
		}

		$role    = $message->getRole();
		$content = array();

		foreach ( $parts as $part ) {
			$part_data = $this->getMessagePartData( $part, $role );

			// Function calls and responses are top-level items, not wrapped in a message.
			// validateMessages() ensures these are the only part in a message.
			$part_type = isset( $part_data['type'] ) ? $part_data['type'] : '';
			if ( 'function_call' === $part_type || 'function_call_output' === $part_type ) {
				return $part_data;
			}

			$content[] = $part_data;
		}

		return array(
			'role'    => $this->getMessageRoleString( $role ),
			'content' => $content,
		);
	}

	/**
	 * Returns the API-specific role string for the given message role.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Messages\Enums\MessageRoleEnum $role The message role.
	 * @return string The role for the API request.
	 */
	protected function getMessageRoleString( MessageRoleEnum $role ): string {
		if ( MessageRoleEnum::model() === $role ) {
			return 'assistant';
		}

		return 'user';
	}

	/**
	 * Returns the API-specific data for a message part.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Messages\DTO\MessagePart     $part The message part to get the data for.
	 * @param \WordPress\AiClient\Messages\Enums\MessageRoleEnum $role The role of the message containing the part.
	 * @return array<string, mixed> The data for the message part.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the message part type or data is unsupported.
	 */
	protected function getMessagePartData( MessagePart $part, MessageRoleEnum $role ): array {
		$type = $part->getType();

		if ( $type->isText() ) {
			return array(
				'type' => $role->isModel() ? 'output_text' : 'input_text',
				'text' => $part->getText(),
			);
		}

		if ( $type->isFile() ) {
			$file = $part->getFile();

			if ( ! $file ) {
				// This should be impossible due to class internals, but still needs to be checked.
				throw new RuntimeException(
					'The file typed message part must contain a file.'
				);
			}

			if ( $file->isRemote() ) {
				$file_url = $file->getUrl();

				if ( ! $file_url ) {
					// This should be impossible due to class internals, but still needs to be checked.
					throw new RuntimeException(
						'The remote file must contain a URL.'
					);
				}

				if ( $file->isImage() ) {
					return array(
						'type'      => 'input_image',
						'image_url' => $file_url,
					);
				}

				// For other file types, use input_file with URL.
				return array(
					'type'     => 'input_file',
					'file_url' => $file_url,
				);
			}

			// Else, it is an inline file.
			$data_uri = $file->getDataUri();

			if ( ! $data_uri ) {
				// This should be impossible due to class internals, but still needs to be checked.
				throw new RuntimeException(
					'The inline file must contain base64 data.'
				);
			}

			if ( $file->isImage() ) {
				return array(
					'type'      => 'input_image',
					'image_url' => $data_uri,
				);
			}

			// For other file types (like PDF), use input_file.
			return array(
				'type'      => 'input_file',
				'filename'  => 'file',
				'file_data' => $data_uri,
			);
		}

		if ( $type->isFunctionCall() ) {
			$function_call = $part->getFunctionCall();

			if ( ! $function_call ) {
				throw new RuntimeException(
					'The function_call typed message part must contain a function call.'
				);
			}

			return array(
				'type'      => 'function_call',
				'call_id'   => $function_call->getId(),
				'name'      => $function_call->getName(),
				'arguments' => wp_json_encode( $function_call->getArgs() ),
			);
		}

		if ( $type->isFunctionResponse() ) {
			$function_response = $part->getFunctionResponse();

			if ( ! $function_response ) {
				throw new RuntimeException(
					'The function_response typed message part must contain a function response.'
				);
			}

			return array(
				'type'    => 'function_call_output',
				'call_id' => $function_response->getId(),
				'output'  => wp_json_encode( $function_response->getResponse() ),
			);
		}

		throw new InvalidArgumentException(
			sprintf(
				'Unsupported message part type "%s".',
				$type // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			)
		);
	}

	/**
	 * Prepares the tools parameter for the API request.
	 *
	 * @since 1.0.0
	 *
	 * @param list<\WordPress\AiClient\Tools\DTO\FunctionDeclaration>|null $function_declarations The function declarations, or null if none.
	 * @param \WordPress\AiClient\Tools\DTO\WebSearch|null                 $web_search            The web search config, or null if none.
	 * @return list<array<string, mixed>> The prepared tools parameter.
	 */
	protected function prepareToolsParam( ?array $function_declarations, ?WebSearch $web_search ): array {
		$tools = array();

		if ( is_array( $function_declarations ) ) {
			foreach ( $function_declarations as $function_declaration ) {
				$tools[] = array(
					'type'        => 'function',
					'name'        => $function_declaration->getName(),
					'description' => $function_declaration->getDescription(),
					'parameters'  => $function_declaration->getParameters(),
				);
			}
		}

		if ( $web_search ) {
			$tools[] = array( 'type' => 'web_search' );
		}

		return $tools;
	}

	/**
	 * Parses the response from the API endpoint to a generative AI result.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The response from the API endpoint.
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The parsed generative AI result.
	 */
	protected function parseResponseToGenerativeAiResult( Response $response ): GenerativeAiResult {
		/** @var ResponseData $response_data */
		$response_data = $response->getData();

		if ( ! isset( $response_data['output'] ) || ! $response_data['output'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'output' );
		}

		if ( ! is_array( $response_data['output'] ) ) {
			throw ResponseException::fromInvalidData(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
				$this->providerMetadata()->getName(),
				'output',
				'The value must be an indexed array.'
			);
		}

		$response_status = isset( $response_data['status'] ) ? (string) $response_data['status'] : 'completed';
		$candidates      = array();

		foreach ( $response_data['output'] as $index => $output_item ) {
			if ( ! is_array( $output_item ) || ! isset( $output_item['type'] ) ) {
				throw ResponseException::fromInvalidData(
					$this->providerMetadata()->getName(), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
					"output[{$index}]", // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
					'The value must be an associative array.'
				);
			}

			$candidate = $this->parseOutputItemToCandidate( $output_item, $index, $response_status );

			if ( null === $candidate ) {
				continue;
			}

			$candidates[] = $candidate;
		}

		$id = isset( $response_data['id'] ) && is_string( $response_data['id'] ) ? $response_data['id'] : '';

		if ( isset( $response_data['usage'] ) && is_array( $response_data['usage'] ) ) {
			$usage         = $response_data['usage'];
			$input_tokens  = isset( $usage['input_tokens'] ) ? (int) $usage['input_tokens'] : 0;
			$output_tokens = isset( $usage['output_tokens'] ) ? (int) $usage['output_tokens'] : 0;
			$total_tokens  = isset( $usage['total_tokens'] ) ? (int) $usage['total_tokens'] : $input_tokens + $output_tokens;
			$token_usage   = new TokenUsage( $input_tokens, $output_tokens, $total_tokens );
		} else {
			$token_usage = new TokenUsage( 0, 0, 0 );
		}

		// Use any other data from the response as provider-specific response metadata.
		$additional_data = $response_data;
		unset( $additional_data['id'], $additional_data['output'], $additional_data['usage'] );

		return new GenerativeAiResult(
			$id,
			$candidates,
			$token_usage,
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data
		);
	}

	/**
	 * Parses a single output item from the API response into a Candidate object.
	 *
	 * @since 1.0.0
	 *
	 * @param OutputItemData $output_item     The output item data from the API response.
	 * @param int            $index           The index of the output item in the output array.
	 * @param string         $response_status The overall response status.
	 * @return \WordPress\AiClient\Results\DTO\Candidate|null The parsed candidate, or null if the output item should be skipped.
	 */
	protected function parseOutputItemToCandidate( array $output_item, int $index, string $response_status ): ?Candidate {
		$type = isset( $output_item['type'] ) ? $output_item['type'] : '';

		// Handle message output type.
		if ( 'message' === $type ) {
			return $this->parseMessageOutputToCandidate( $output_item, $index, $response_status );
		}

		// Handle function_call output type (top-level function call).
		if ( 'function_call' === $type ) {
			return $this->parseFunctionCallOutputToCandidate( $output_item, $index );
		}

		// Skip other output types.
		return null;
	}

	/**
	 * Parses a message output item into a Candidate object.
	 *
	 * @since 1.0.0
	 *
	 * @param OutputItemData $output_item     The output item data.
	 * @param int            $index           The index of the output item.
	 * @param string         $response_status The overall response status.
	 * @return \WordPress\AiClient\Results\DTO\Candidate The parsed candidate.
	 */
	protected function parseMessageOutputToCandidate( array $output_item, int $index, string $response_status ): Candidate {
		$role = isset( $output_item['role'] ) && 'user' === $output_item['role']
			? MessageRoleEnum::user()
			: MessageRoleEnum::model();

		$parts              = array();
		$has_function_calls = false;

		if ( isset( $output_item['content'] ) && is_array( $output_item['content'] ) ) {
			foreach ( $output_item['content'] as $content_index => $content_item ) {
				try {
					$part = $this->parseOutputContentToPart( $content_item );
					if ( null !== $part ) {
						$parts[] = $part;
						if ( $part->getType()->isFunctionCall() ) {
							$has_function_calls = true;
						}
					}
				} catch ( InvalidArgumentException $e ) {
					throw ResponseException::fromInvalidData(
						$this->providerMetadata()->getName(), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
						"output[{$index}].content[{$content_index}]", // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
						$e->getMessage() // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
					);
				}
			}
		}

		$message       = new Message( $role, $parts );
		$finish_reason = $this->parseStatusToFinishReason( $response_status, $has_function_calls );

		return new Candidate( $message, $finish_reason );
	}

	/**
	 * Parses a function_call output item into a Candidate object.
	 *
	 * @since 1.0.0
	 *
	 * @param OutputItemData $output_item The output item data.
	 * @param int            $index       The index of the output item.
	 * @return \WordPress\AiClient\Results\DTO\Candidate The parsed candidate.
	 */
	protected function parseFunctionCallOutputToCandidate( array $output_item, int $index ): Candidate {
		if ( ! isset( $output_item['call_id'] ) || ! is_string( $output_item['call_id'] ) ) {
			throw ResponseException::fromMissingData(
				$this->providerMetadata()->getName(), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
				"output[{$index}].call_id" // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			);
		}

		if ( ! isset( $output_item['name'] ) || ! is_string( $output_item['name'] ) ) {
			throw ResponseException::fromMissingData(
				$this->providerMetadata()->getName(), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
				"output[{$index}].name" // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			);
		}

		/*
		 * Parse and normalize function arguments.
		 * Returns arguments as a JSON string. An empty object "{}"
		 * decodes to an empty array, which semantically means "no arguments"
		 * and should be normalized to null.
		 */
		$args = null;
		if ( isset( $output_item['arguments'] ) && is_string( $output_item['arguments'] ) ) {
			$decoded = json_decode( $output_item['arguments'], true );
			if ( is_array( $decoded ) && count( $decoded ) > 0 ) {
				$args = $decoded;
			}
		}

		$function_call = new FunctionCall(
			$output_item['call_id'],
			$output_item['name'],
			$args
		);

		$part    = new MessagePart( $function_call );
		$message = new Message( MessageRoleEnum::model(), array( $part ) );

		return new Candidate( $message, FinishReasonEnum::toolCalls() );
	}

	/**
	 * Parses an output content item into a MessagePart.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $content_item The content item data.
	 * @return \WordPress\AiClient\Messages\DTO\MessagePart|null The parsed message part, or null to skip.
	 */
	protected function parseOutputContentToPart( array $content_item ): ?MessagePart {
		$type = isset( $content_item['type'] ) ? $content_item['type'] : '';

		if ( 'output_text' === $type ) {
			if ( ! isset( $content_item['text'] ) || ! is_string( $content_item['text'] ) ) {
				throw new InvalidArgumentException( 'Content has an invalid output_text shape.' );
			}
			return new MessagePart( $content_item['text'] );
		}

		if ( 'function_call' === $type ) {
			if (
				! isset( $content_item['call_id'] ) ||
				! is_string( $content_item['call_id'] ) ||
				! isset( $content_item['name'] ) ||
				! is_string( $content_item['name'] )
			) {
				throw new InvalidArgumentException( 'Content has an invalid function_call shape.' );
			}

			/*
			 * Parse and normalize function arguments.
			 * Returns arguments as a JSON string. An empty object "{}"
			 * decodes to an empty array, which semantically means "no arguments"
			 * and should be normalized to null.
			 */
			$args = null;
			if ( isset( $content_item['arguments'] ) && is_string( $content_item['arguments'] ) ) {
				$decoded = json_decode( $content_item['arguments'], true );
				if ( is_array( $decoded ) && count( $decoded ) > 0 ) {
					$args = $decoded;
				}
			}

			return new MessagePart(
				new FunctionCall(
					$content_item['call_id'],
					$content_item['name'],
					$args
				)
			);
		}

		// Skip unknown content types.
		return null;
	}

	/**
	 * Parses the response status to a finish reason.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status            The response status.
	 * @param bool   $has_function_calls Whether the response contains function calls.
	 * @return \WordPress\AiClient\Results\Enums\FinishReasonEnum The finish reason.
	 */
	protected function parseStatusToFinishReason( string $status, bool $has_function_calls ): FinishReasonEnum {
		switch ( $status ) {
			case 'completed':
				return $has_function_calls ? FinishReasonEnum::toolCalls() : FinishReasonEnum::stop();
			case 'incomplete':
				return FinishReasonEnum::length();
			case 'failed':
			case 'cancelled':
				return FinishReasonEnum::error();
			default:
				// Default to stop for unknown statuses.
				return FinishReasonEnum::stop();
		}
	}
}
