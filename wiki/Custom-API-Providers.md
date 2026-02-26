# Custom API Providers

> Route Claude Agent SDK requests through alternative backends -- AWS Bedrock, Google Vertex AI, Azure Foundry, or your own self-hosted proxy. The SDK forwards environment variables to the Claude Code CLI subprocess, so switching providers is a matter of configuration.

## Overview

By default the SDK sends requests to the Anthropic API using your `ANTHROPIC_API_KEY`. The underlying Claude Code CLI also supports cloud-provider endpoints and custom base URLs. The SDK exposes these options through:

1. **Config keys** in `config/claude-agent.php` (application-wide)
2. **Environment variables** in `.env` (deployment-wide)
3. **Per-query overrides** via `ClaudeAgentOptions::make()->env()` (per-call)

Because the SDK spawns the CLI as a subprocess, every environment variable set on the process is available to the CLI's own provider-routing logic.

## Anthropic API (Default)

The standard setup requires only an API key:

```dotenv
# .env
ANTHROPIC_API_KEY=sk-ant-...
```

```php
// config/claude-agent.php
'api_key' => env('ANTHROPIC_API_KEY'),
```

No additional flags are needed. The CLI connects to `https://api.anthropic.com` automatically.

## AWS Bedrock

Route requests through [Amazon Bedrock](https://aws.amazon.com/bedrock/) to keep traffic within your AWS account.

### 1. Enable the Provider

```dotenv
# .env
CLAUDE_CODE_USE_BEDROCK=true
```

```php
// config/claude-agent.php
'providers' => [
    'bedrock' => env('CLAUDE_CODE_USE_BEDROCK', false),
],
```

### 2. Configure AWS Credentials

The CLI reads standard AWS environment variables. Set them in `.env` or use an IAM instance role:

```dotenv
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_REGION=us-east-1
```

> **Tip:** On EC2 or ECS, attach an IAM role with `bedrock:InvokeModel` permissions instead of static credentials. The CLI picks up the instance metadata automatically.

### 3. IAM Policy

The executing role needs at minimum:

```json
{
  "Effect": "Allow",
  "Action": ["bedrock:InvokeModel", "bedrock:InvokeModelWithResponseStream"],
  "Resource": "arn:aws:bedrock:*:*:inference-profile/*"
}
```

### 4. Code Example

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep'])
    ->maxBudgetUsd(1.00);

$result = ClaudeAgent::query('Summarize the README', $options);
```

No code changes are needed beyond the environment configuration -- the SDK passes `CLAUDE_CODE_USE_BEDROCK=true` to the CLI process.

## Google Vertex AI

Route requests through [Vertex AI](https://cloud.google.com/vertex-ai) on Google Cloud Platform.

### 1. Enable the Provider

```dotenv
# .env
CLAUDE_CODE_USE_VERTEX=true
```

```php
// config/claude-agent.php
'providers' => [
    'vertex' => env('CLAUDE_CODE_USE_VERTEX', false),
],
```

### 2. Configure GCP Authentication

```dotenv
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json
CLOUD_ML_REGION=us-central1
ANTHROPIC_VERTEX_PROJECT_ID=my-gcp-project
```

> **Tip:** On GKE or Cloud Run, use Workload Identity instead of a service-account key file for tighter security.

### 3. Code Example

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;

$result = ClaudeAgent::query('Analyze the database schema');
echo $result->text();
```

## Azure Foundry

Route requests through Azure's Foundry endpoint.

### 1. Enable the Provider

```dotenv
# .env
CLAUDE_CODE_USE_FOUNDRY=true
```

```php
// config/claude-agent.php
'providers' => [
    'foundry' => env('CLAUDE_CODE_USE_FOUNDRY', false),
],
```

### 2. Configure Azure Credentials

Set the credentials expected by the CLI:

```dotenv
AZURE_OPENAI_API_KEY=...
AZURE_OPENAI_ENDPOINT=https://my-resource.openai.azure.com
```

### 3. Code Example

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;

$result = ClaudeAgent::query('Generate a migration for the orders table');
echo $result->text();
```

> **Note:** Enable only one provider at a time. The SDK passes these flags directly to the Claude Code CLI, which determines the routing logic.

## Custom Base URL

For self-hosted proxies, enterprise API gateways, or any Anthropic-compatible endpoint, set a custom base URL instead of enabling a named provider.

```dotenv
# .env
ANTHROPIC_BASE_URL=https://claude-proxy.internal.example.com/v1
ANTHROPIC_AUTH_TOKEN=your-proxy-bearer-token
```

```php
// config/claude-agent.php
'api_base_url' => env('ANTHROPIC_BASE_URL', null),
'auth_token'   => env('ANTHROPIC_AUTH_TOKEN', null),
```

**Common use cases:**

- Corporate API gateways that log and rate-limit requests
- Self-hosted proxy servers that add caching or load balancing
- On-premise deployments with no direct internet access
- Testing with a mock server

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep'])
    ->maxBudgetUsd(0.50);

$result = ClaudeAgent::query('List all API endpoints', $options);
```

> **Warning:** When using a custom base URL, ensure your proxy forwards all required headers (`x-api-key` or `Authorization: Bearer`) and supports the streaming JSON output format used by the CLI.

## Per-Query Provider Override

Use `env()` on `ClaudeAgentOptions` to switch providers dynamically without changing global configuration. This is useful when different queries should route through different backends.

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Route this specific query through a custom proxy
$options = ClaudeAgentOptions::make()
    ->env('ANTHROPIC_BASE_URL', 'https://eu-proxy.example.com/v1')
    ->env('ANTHROPIC_AUTH_TOKEN', config('services.eu_proxy.token'))
    ->tools(['Read', 'Grep']);

$result = ClaudeAgent::query('Analyze EU customer data', $options);

// Route another query through Bedrock
$bedrockOptions = ClaudeAgentOptions::make()
    ->env('CLAUDE_CODE_USE_BEDROCK', 'true')
    ->env('AWS_REGION', 'eu-west-1')
    ->tools(['Read']);

$result = ClaudeAgent::query('Process UK compliance report', $bedrockOptions);
```

> **Tip:** Per-query `env()` values override the process-level environment for that single CLI invocation. The global config remains unchanged.

## Provider Comparison

| Feature | Anthropic API | AWS Bedrock | Google Vertex AI | Azure Foundry | Custom URL |
|---------|:---:|:---:|:---:|:---:|:---:|
| Setup complexity | Low | Medium | Medium | Medium | Low |
| Credential type | API key | IAM role / keys | Service account | Azure AD / key | Varies |
| Data residency control | No | Yes (region) | Yes (region) | Yes (region) | Yes |
| VPC / private network | No | Yes | Yes | Yes | Yes |
| Enterprise billing integration | No | AWS billing | GCP billing | Azure billing | Custom |
| Model availability | All models | Subset | Subset | Subset | Depends on proxy |

## Troubleshooting

### Authentication Failures

- **Anthropic API:** Verify `ANTHROPIC_API_KEY` starts with `sk-ant-`. Check for trailing whitespace in `.env`.
- **Bedrock:** Run `aws sts get-caller-identity` from the same user context as the queue worker to confirm credentials.
- **Vertex AI:** Ensure `GOOGLE_APPLICATION_CREDENTIALS` points to a valid JSON key file readable by the web/worker user.
- **Foundry:** Confirm the Azure endpoint URL includes the resource name and API version.

### Model Availability

Not all models are available on every provider. If you receive a model-not-found error:

1. Check the provider's documentation for supported model identifiers.
2. Use `fallbackModel()` to gracefully degrade:

```php
$options = ClaudeAgentOptions::make()
    ->model('claude-opus-4-20250514')
    ->fallbackModel('claude-sonnet-4-20250514');
```

### Environment Variables Not Reaching the CLI

The SDK passes environment variables via the process constructor. If a variable is not taking effect:

1. Confirm it is set in `.env` and not cached (`php artisan config:clear`).
2. For per-query overrides, verify you are using `->env('KEY', 'value')` on the options object.
3. Check that no other middleware or wrapper is stripping environment variables from the subprocess.

## Next Steps

- [[Configuration]] -- Full config reference including all provider keys
- [[Options Reference]] -- Per-query overrides with `ClaudeAgentOptions`
- [[Security Guide]] -- Protect API keys and restrict agent capabilities
- [[Production Deployment]] -- Queue integration, scaling, and monitoring
