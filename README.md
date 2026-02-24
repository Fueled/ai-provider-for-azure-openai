# AI Provider for Azure OpenAI

## Requirements

- PHP 7.4+
- [php-ai-client](https://github.com/WordPress/php-ai-client) `^0.4` or [wp-ai-client](https://github.com/WordPress/wp-ai-client) `^0.2`

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

## Usage

### With WordPress (wp-ai-client)

```php
use WordPress\AI_Client\Prompt_Builder;

$result = Prompt_Builder::create()
    ->using_provider( 'azure-openai' )
    ->set_system_instruction( 'You are a helpful assistant.' )
    ->add_text_message( 'Hello, how are you?' )
    ->generate_text();
```

### Standalone PHP (php-ai-client)

```php
use Fueled\AiProviderForAzureOpenAI\Provider\AzureOpenAIProvider;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

require_once 'vendor/autoload.php';

$registry = AiClient::defaultRegistry();
$registry->registerProvider(AzureOpenAIProvider::class);
$registry->setProviderRequestAuthentication('azure-openai', new ApiKeyRequestAuthentication(''));

$result = AiClient::prompt('Hello!')
    ->usingProvider('azure-openai')
    ->generateText();
```

## Like what you see?

[![Work with the 10up WordPress Practice at Fueled](https://github.com/10up/.github/blob/trunk/profile/10up-github-banner.jpg)](http://10up.com/contact/)
