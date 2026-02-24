<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Provider;

use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProviderAvailability;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AzureOpenAIProviderAvailability.
 *
 * @covers \Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProviderAvailability
 */
class AzureOpenAIProviderAvailabilityTest extends TestCase {

	/**
	 * The original AZURE_OPENAI_ENDPOINT value before each test.
	 *
	 * @var string|false
	 */
	private $original_endpoint;

	protected function setUp(): void {
		parent::setUp();
		$this->original_endpoint = getenv( 'AZURE_OPENAI_ENDPOINT' );
	}

	protected function tearDown(): void {
		if ( false === $this->original_endpoint ) {
			putenv( 'AZURE_OPENAI_ENDPOINT' );
		} else {
			putenv( 'AZURE_OPENAI_ENDPOINT=' . $this->original_endpoint );
		}
		parent::tearDown();
	}

	/**
	 * Tests that isConfigured() returns true when AZURE_OPENAI_ENDPOINT is set.
	 */
	public function test_is_configured_returns_true_when_endpoint_is_set(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com' );
		$availability = new AzureOpenAIProviderAvailability();
		$this->assertTrue( $availability->isConfigured() );
	}

	/**
	 * Tests that isConfigured() returns false when AZURE_OPENAI_ENDPOINT is not set.
	 */
	public function test_is_configured_returns_false_when_env_var_not_set(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT' ); // Removes the env var entirely.
		$availability = new AzureOpenAIProviderAvailability();
		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Tests that isConfigured() returns false when AZURE_OPENAI_ENDPOINT is an empty string.
	 */
	public function test_is_configured_returns_false_when_env_var_is_empty_string(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=' );
		$availability = new AzureOpenAIProviderAvailability();
		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Tests that isConfigured() returns false when AZURE_OPENAI_ENDPOINT is whitespace only.
	 */
	public function test_is_configured_returns_false_when_env_var_is_whitespace(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=   ' );
		$availability = new AzureOpenAIProviderAvailability();
		$this->assertFalse( $availability->isConfigured() );
	}
}
