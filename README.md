# AI Provider for Azure OpenAI

Azure OpenAI provider for the [PHP AI Client SDK](https://github.com/WordPress/php-ai-client). Works as both a Composer package with the `php-ai-client` package and as a WordPress plugin with the `wp-ai-client` plugin.

[Azure OpenAI](https://azure.microsoft.com/en-us/products/ai-services/openai-service) is Microsoft's cloud service that hosts OpenAI models — including GPT-4o, DALL-E, GPT Image, and TTS — through Azure infrastructure with enterprise security and compliance. This provider connects to your Azure OpenAI resource using your own endpoint URL, API key, and configured deployments.

## Requirements

- PHP 7.4+
- [php-ai-client](https://github.com/WordPress/php-ai-client) `^0.4` or [wp-ai-client](https://github.com/WordPress/wp-ai-client) `^0.2`
- An Azure subscription with an Azure OpenAI resource and at least one deployment

## Installation

### As a WordPress Plugin

1. Install and activate the [wp-ai-client](https://github.com/WordPress/wp-ai-client) plugin.
2. Place this plugin in your `wp-content/plugins/` directory.
3. Activate "AI Provider for Azure OpenAI" from the Plugins screen.

### As a Composer Package

```bash
composer require fueled/ai-provider-for-azure-openai
```

## Configuration

### 1. Endpoint URL

Go to **Settings > Azure OpenAI Settings** and enter the base URL of your Azure OpenAI resource. You can find this in the Azure Portal under your resource → **Keys and Endpoint**. It looks like:

```
https://myresource.openai.azure.com
```

Do not include `/openai/v1` — it is appended automatically. You can also set the `AZURE_OPENAI_ENDPOINT` environment variable to override this setting, which is useful for local development or staging environments.

### 2. API Key

Enter your Azure OpenAI API key on the **Settings > AI Credentials** screen under the `azure_openai` provider. You can find your API key in the Azure Portal under your resource → **Keys and Endpoint**.

### 3. Deployments

On the **Settings > Azure OpenAI Settings** screen, add each of your Azure OpenAI deployments by clicking **Add Deployment**. For each deployment, enter:

- **Deployment Name** — the name you gave the deployment in [Azure AI Foundry](https://oai.azure.com/) (e.g. `my-gpt4o`).
- **Model Type** — the capability set for this deployment:

| Type | Use for |
|---|---|
| Chat (Text Generation) | Text-only GPT deployments (GPT-3.5, GPT-4, o1, o3, o4-mini, etc.) |
| Chat + Vision (Multimodal) | GPT deployments that accept images and documents (GPT-4o, GPT-4o mini, etc.) |
| Image Generation (DALL-E) | DALL-E 2 and DALL-E 3 deployments |
| Image Generation (GPT Image) | GPT Image 1 deployments |
| Text-to-Speech | TTS-1 and TTS-1-HD deployments |

## Usage

### With WordPress (wp-ai-client)

```php
use WordPress\AI_Client\Prompt_Builder;

$result = Prompt_Builder::create()
    ->using_provider( 'azure_openai' )
    ->set_system_instruction( 'You are a helpful assistant.' )
    ->add_text_message( 'Hello, how are you?' )
    ->generate_text();
```

### Standalone PHP (php-ai-client)

```php
use Fueled\AiProviderForAzureOpenAI\Metadata\AzureOpenAIModelMetadataDirectory;
use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProvider;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

require_once 'vendor/autoload.php';

putenv( 'AZURE_OPENAI_ENDPOINT=https://myresource.openai.azure.com' );

AzureOpenAIModelMetadataDirectory::setDeployments( array(
    array( 'name' => 'my-gpt4o', 'type' => 'chat_multimodal' ),
) );

$registry = AiClient::defaultRegistry();
$registry->registerProvider( AzureOpenAIProvider::class );
$registry->setProviderRequestAuthentication( 'azure_openai', new ApiKeyRequestAuthentication( 'your-api-key' ) );

$result = AiClient::prompt( 'Hello!' )
    ->usingProvider( 'azure_openai' )
    ->usingModel( 'my-gpt4o' )
    ->generateText();
```

## Like what you see?

[![Work with the 10up WordPress Practice at Fueled](https://github.com/10up/.github/blob/trunk/profile/10up-github-banner.jpg)](http://10up.com/contact/)
