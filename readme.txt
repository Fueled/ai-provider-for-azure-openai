=== AI Provider for Azure OpenAI ===
Contributors: 10up
Tags: ai, azure, openai, llm, artificial-intelligence
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Azure OpenAI provider for the WordPress AI Client.

== Description ==

This plugin provides [Azure OpenAI](https://azure.microsoft.com/en-us/products/ai-services/openai-service) integration for the WordPress AI Client. It connects to your Azure OpenAI resource using your own endpoint URL and API key, giving you access to OpenAI models — including GPT-4o, DALL-E, GPT Image, and TTS — through Azure's enterprise infrastructure.

Because Azure OpenAI uses deployment-based model access, this provider lets you configure each of your deployments by name and model type. The WordPress AI Client then uses those deployments automatically when generating text, images, or speech.

**Features:**

* Text generation with Azure OpenAI chat models (GPT-3.5, GPT-4, o1, o3, o4-mini, and more)
* Multimodal input support for vision-capable models (GPT-4o, GPT-4o mini)
* Image generation with DALL-E 2, DALL-E 3, and GPT Image 1 deployments
* Text-to-speech with TTS-1 and TTS-1-HD deployments
* Function calling and structured output (JSON mode) support
* Settings page for configuring your resource endpoint URL and deployments
* Environment variable support (`AZURE_OPENAI_ENDPOINT`) for dev/staging overrides

**Requirements:**

* PHP 7.4 or higher
* WordPress AI Client plugin must be installed and activated
* An Azure subscription with an Azure OpenAI resource and at least one deployment

== Installation ==

1. Ensure the WordPress AI Client plugin is installed and activated.
2. Upload the plugin files to `/wp-content/plugins/ai-provider-for-azure-openai/`.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Go to **Settings > Azure OpenAI Settings** to configure your endpoint URL and deployments.
5. Enter your API key on the **Settings > AI Credentials** screen under the `azure_openai` provider.

== Frequently Asked Questions ==

= Where do I find my Azure OpenAI endpoint URL and API key? =

In the [Azure Portal](https://portal.azure.com/), open your Azure OpenAI resource and click **Keys and Endpoint** in the left navigation. Your endpoint URL looks like `https://myresource.openai.azure.com`. Copy either Key 1 or Key 2 as your API key.

= What is a deployment, and how do I create one? =

An Azure OpenAI deployment is a named instance of a model that you provision in your resource. You create and manage deployments in [Azure AI Foundry](https://oai.azure.com/). Once created, the deployment name you chose (e.g. `my-gpt4o`) is what you enter in the plugin settings.

= What model type should I choose for each deployment? =

Choose the type that matches the model you deployed:

* **Chat (Text Generation)** — GPT-3.5, GPT-4, o1, o3, o4-mini, and other text-only chat models.
* **Chat + Vision (Multimodal)** — GPT-4o, GPT-4o mini, and other models that accept images as input.
* **Image Generation (DALL-E)** — DALL-E 2 and DALL-E 3 deployments.
* **Image Generation (GPT Image)** — GPT Image 1 deployments.
* **Text-to-Speech** — TTS-1 and TTS-1-HD deployments.

= Can I override the endpoint URL without changing the settings? =

Yes. Set the `AZURE_OPENAI_ENDPOINT` environment variable in your server environment or `.env` file. When this variable is set, it takes precedence over the value saved in the WordPress settings. This is useful for local development or staging environments.

= Does this plugin work without the WordPress AI Client? =

No, this plugin requires the WordPress AI Client plugin to be installed and activated. It provides the Azure OpenAI-specific implementation that the WordPress AI Client uses.

== Changelog ==

= 1.0.0 =

* Initial release
* Text generation with Azure OpenAI chat and multimodal deployments
* Image generation with DALL-E and GPT Image deployments
* Text-to-speech with TTS deployments
* Settings page for endpoint URL and deployment configuration
* Function calling and structured output support

== Upgrade Notice ==

= 1.0.0 =

Initial release.
