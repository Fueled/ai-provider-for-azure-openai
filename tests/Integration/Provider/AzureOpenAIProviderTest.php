<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForAzureOpenAI\Tests\Integration\Provider;

use Fueled\AiProviderForAzureOpenAI\Metadata\AzureOpenAIModelMetadataDirectory;
use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProvider;
use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProviderAvailability;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\AbstractProvider;

/**
 * Tests for AzureOpenAIProvider.
 *
 * @covers \Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProvider
 */
class AzureOpenAIProviderTest extends TestCase {

	/**
	 * The original AZURE_OPENAI_ENDPOINT value before each test.
	 *
	 * @var string|false
	 */
	private $original_endpoint;

	protected function setUp(): void {
		parent::setUp();
		$this->original_endpoint = getenv( 'AZURE_OPENAI_ENDPOINT' );
		$this->clear_provider_caches();
	}

	protected function tearDown(): void {
		$this->restore_endpoint();
		$this->clear_provider_caches();
		parent::tearDown();
	}

	/**
	 * Clears all static caches on AbstractProvider to ensure test isolation.
	 */
	private function clear_provider_caches(): void {
		$reflection = new \ReflectionClass( AbstractProvider::class );
		foreach ( array( 'metadataCache', 'availabilityCache', 'modelMetadataDirectoryCache' ) as $prop_name ) {
			$prop = $reflection->getProperty( $prop_name );
			$prop->setAccessible( true );
			$prop->setValue( null, array() );
		}
	}

	/**
	 * Restores AZURE_OPENAI_ENDPOINT to its pre-test value.
	 */
	private function restore_endpoint(): void {
		if ( false === $this->original_endpoint ) {
			putenv( 'AZURE_OPENAI_ENDPOINT' );
		} else {
			putenv( 'AZURE_OPENAI_ENDPOINT=' . $this->original_endpoint );
		}
	}

	// -----------------------------------------------------------------------
	// url() / baseUrl() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that url() returns an empty string when AZURE_OPENAI_ENDPOINT is not set.
	 */
	public function test_url_returns_empty_string_when_endpoint_not_set(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT' ); // Remove env var entirely.
		$url = AzureOpenAIProvider::url( '' );
		$this->assertSame( '', $url );
	}

	/**
	 * Tests that url() returns an empty string when AZURE_OPENAI_ENDPOINT is empty.
	 */
	public function test_url_returns_empty_string_when_endpoint_is_empty(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=' );
		$url = AzureOpenAIProvider::url( '' );
		$this->assertSame( '', $url );
	}

	/**
	 * Tests that url() uses AZURE_OPENAI_ENDPOINT and appends /openai/v1.
	 */
	public function test_url_uses_endpoint_env_var_with_openai_v1_path(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com' );
		$url = AzureOpenAIProvider::url( '' );
		$this->assertSame( 'https://myresource.openai.azure.com/openai/v1', $url );
	}

	/**
	 * Tests that url() strips a trailing slash from the endpoint before appending /openai/v1.
	 */
	public function test_url_strips_trailing_slash_from_endpoint(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com/' );
		$url = AzureOpenAIProvider::url( '' );
		$this->assertSame( 'https://myresource.openai.azure.com/openai/v1', $url );
	}

	/**
	 * Tests that url() appends the path after /openai/v1.
	 */
	public function test_url_appends_path_after_openai_v1(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com' );
		$url = AzureOpenAIProvider::url( 'models' );
		$this->assertSame( 'https://myresource.openai.azure.com/openai/v1/models', $url );
	}

	/**
	 * Tests that url() correctly builds the responses endpoint path.
	 */
	public function test_url_builds_responses_endpoint(): void {
		putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com' );
		$url = AzureOpenAIProvider::url( 'responses' );
		$this->assertSame( 'https://myresource.openai.azure.com/openai/v1/responses', $url );
	}

	// -----------------------------------------------------------------------
	// metadata() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that the provider metadata has the correct provider ID.
	 */
	public function test_metadata_has_correct_provider_id(): void {
		$metadata = AzureOpenAIProvider::metadata();
		$this->assertSame( 'azure_openai', $metadata->getId() );
	}

	/**
	 * Tests that the provider metadata has the correct display name.
	 */
	public function test_metadata_has_correct_name(): void {
		$metadata = AzureOpenAIProvider::metadata();
		$this->assertSame( 'Azure OpenAI', $metadata->getName() );
	}

	/**
	 * Tests that the provider metadata specifies API key as the authentication method.
	 */
	public function test_metadata_auth_method_is_api_key(): void {
		$metadata    = AzureOpenAIProvider::metadata();
		$auth_method = $metadata->getAuthenticationMethod();
		$this->assertNotNull( $auth_method );
		$this->assertTrue( $auth_method->isApiKey() );
	}

	// -----------------------------------------------------------------------
	// Factory method tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that availability() returns an AzureOpenAIProviderAvailability instance.
	 */
	public function test_availability_returns_azure_provider_availability(): void {
		$availability = AzureOpenAIProvider::availability();
		$this->assertInstanceOf( AzureOpenAIProviderAvailability::class, $availability );
	}

	/**
	 * Tests that modelMetadataDirectory() returns an AzureOpenAIModelMetadataDirectory instance.
	 */
	public function test_model_metadata_directory_returns_correct_type(): void {
		$directory = AzureOpenAIProvider::modelMetadataDirectory();
		$this->assertInstanceOf( AzureOpenAIModelMetadataDirectory::class, $directory );
	}
}
