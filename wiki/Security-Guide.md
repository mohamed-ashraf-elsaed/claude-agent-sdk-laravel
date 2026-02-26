# Security Guide

> The Claude Agent SDK gives an AI agent powerful capabilities -- reading files, writing code, executing shell commands, and accessing the web. This guide covers the security controls you should apply at every layer to keep your application and infrastructure safe.

## Overview

When you run a query through the SDK, the Claude Code CLI subprocess can:

- Read and write files on the filesystem
- Execute arbitrary shell commands via the Bash tool
- Fetch URLs and search the web
- Interact with MCP servers and external services

Each of these capabilities is a potential attack surface. The SDK provides multiple layers of defence -- permission modes, tool restrictions, directory isolation, sandboxing, hooks, and input validation -- that should be configured according to your threat model.

## API Key Management

Your Anthropic API key (or provider credentials) grants access to a paid service and must be treated as a secret.

- **Never commit keys to version control.** Use `.env` files or a secrets manager (AWS Secrets Manager, HashiCorp Vault, etc.).
- **Rotate keys periodically.** If a key is compromised, revoke it immediately in the Anthropic dashboard.
- **Use scoped keys** when your provider supports them. Prefer keys with the narrowest permissions needed.
- **Restrict access** to `.env` files with filesystem permissions (`chmod 600`).

> **Warning:** Never expose API keys in client-side code, JavaScript bundles, or public API responses. The SDK runs server-side only -- keep it that way.

```dotenv
# .env -- never committed to git
ANTHROPIC_API_KEY=sk-ant-...
```

```gitignore
# .gitignore
.env
.env.*
```

## Permission Modes

Permission modes are your first line of defence. They control which tool-use requests the CLI auto-approves, prompts for, or rejects outright.

| Mode | CLI Flag | Behaviour | Security Level |
|------|----------|-----------|----------------|
| `default` | *(none)* | Prompts for every tool use | Highest |
| `acceptEdits` | `--allowedTools Edit` | Auto-approves file edits; prompts for the rest | High |
| `dontAsk` | `--allowedTools ...` | Auto-approves tools in `allowed_tools`; rejects all others | Medium |
| `bypassPermissions` | `--dangerously-skip-permissions` | Skips all permission checks | **None** |

### Recommendations by Environment

| Environment | Recommended Mode | Allowed Tools | Rationale |
|-------------|-----------------|---------------|-----------|
| **Development** | `acceptEdits` | `Read, Grep, Glob, Edit, Write` | Developer oversight present |
| **CI / Testing** | `bypassPermissions` | All (disposable container) | No persistent state at risk |
| **Staging** | `dontAsk` | `Read, Grep, Glob` | Mirrors production with read-only access |
| **Production** | `dontAsk` | `Read, Grep, Glob` | Principle of least privilege |

> **Warning:** `bypassPermissions` disables every safety gate. Only use it inside disposable containers or CI runners with no access to production data or secrets.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Production-safe defaults
$options = ClaudeAgentOptions::make()
    ->permission('dontAsk')
    ->tools(['Read', 'Grep', 'Glob']);
```

## Tool Restrictions

Apply the principle of least privilege: grant only the tools the agent actually needs.

- **Start read-only.** Begin with `Read`, `Grep`, and `Glob`. Add write tools only when the use case demands it.
- **Use `disallow()`** to explicitly block dangerous tools even if the permission mode would allow them.
- **Audit tool usage** by inspecting `$result->toolUses()` after each query.

> **Warning:** The `Bash` tool can execute arbitrary shell commands with the permissions of the worker process. Granting it is equivalent to giving the agent a shell session. Never enable it in production unless the process runs in a sandboxed, disposable environment.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Read-only analysis agent
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob'])
    ->disallow(['Bash', 'Write', 'Edit']);
```

## Working Directory Isolation

The `cwd()` and `addDir()` options control which parts of the filesystem the agent can access.

- **Set `cwd()`** to the narrowest directory the agent needs. Never point it at `/` or your home directory.
- **Use `addDir()`** to grant access to specific additional directories without widening the working directory.
- **Create temporary workspaces** for untrusted operations.

> **Warning:** Never set the working directory to `/`, `/etc`, `/home`, or any directory containing secrets, credentials, or system configuration files.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Isolate the agent to a specific project directory
$options = ClaudeAgentOptions::make()
    ->cwd(storage_path('agent-workspace'))
    ->addDir(base_path('app/Models'));

// Temporary workspace for untrusted tasks
$tempDir = sys_get_temp_dir() . '/agent-' . uniqid();
mkdir($tempDir, 0700);

$options = ClaudeAgentOptions::make()
    ->cwd($tempDir)
    ->tools(['Read', 'Write', 'Edit']);
```

## Sandboxing

For maximum isolation, run the agent inside a sandbox that restricts network access, filesystem writes, or both.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->sandbox(['network' => false, 'filesystem' => 'read-only']);
```

### Docker Sandbox Example

Run the entire queue worker inside a Docker container with limited capabilities:

```dockerfile
FROM php:8.2-cli
# Install Claude CLI
RUN npm install -g @anthropic-ai/claude-code

# Drop privileges
USER www-data

# No network access to internal services
# Mount only the directories the agent needs
```

```yaml
# docker-compose.yml
agent-worker:
  build: ./docker/agent-worker
  volumes:
    - ./storage/agent-workspace:/workspace:rw
    - ./app:/app:ro
  networks:
    - agent-net  # isolated network with no access to databases
  deploy:
    resources:
      limits:
        memory: 512M
```

> **Tip:** Use sandboxing whenever the agent has write access to the filesystem or the `Bash` tool is enabled. The overhead is minimal compared to the risk of an unconstrained agent.

## Input Validation

User-supplied prompts are passed directly to the CLI. A malicious prompt can attempt to manipulate the agent into performing unintended actions (prompt injection).

- **Sanitize user input** before passing it to `query()` or `stream()`. Remove or escape control characters and suspicious patterns.
- **Set strict tool restrictions** so that even a successful injection cannot cause harm.
- **Validate output** before acting on structured results.

> **Warning:** Never pass raw, unvalidated user input as a prompt in production. Always combine input validation with restrictive tool permissions to create defence in depth.

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

function sanitizePrompt(string $input): string
{
    // Remove null bytes and control characters
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $input);
    // Truncate to a reasonable length
    return mb_substr($clean, 0, 5000);
}

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep'])           // read-only even if injection succeeds
    ->maxTurns(5)                       // limit runaway conversations
    ->maxBudgetUsd(0.50);              // cap spend

$result = ClaudeAgent::query(sanitizePrompt($userInput), $options);
```

## Hooks for Security Enforcement

[[Hooks]] run shell commands before or after tool use. Use them to validate, block, or audit agent actions.

### Block Dangerous Commands

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Hooks\HookEvent;
use ClaudeAgentSDK\Hooks\HookMatcher;

$options = ClaudeAgentOptions::make()
    ->preToolUse('php /hooks/validate-bash.php', '/Bash/', 10)
    ->hook(HookEvent::PreToolUse, new HookMatcher(
        matcher: '/Write|Edit/',
        hooks: ['php /hooks/validate-file-path.php'],
        timeout: 10,
    ));
```

Example validation script (`/hooks/validate-bash.php`):

```php
<?php
// Read the hook input from stdin
$input = json_decode(file_get_contents('php://stdin'), true);
$command = $input['tool_input']['command'] ?? '';

// Block destructive patterns
$blocked = ['rm -rf', 'mkfs', 'dd if=', ':(){', 'chmod -R 777', '> /dev/sda'];
foreach ($blocked as $pattern) {
    if (str_contains($command, $pattern)) {
        echo json_encode(['decision' => 'block', 'reason' => "Blocked dangerous command: {$pattern}"]);
        exit(0);
    }
}

echo json_encode(['decision' => 'approve']);
```

### Audit Logging

```php
$options = ClaudeAgentOptions::make()
    ->postToolUse('php /hooks/audit-log.php');
```

This records every tool invocation for later review, compliance, or incident investigation.

## Network Security

The agent can access the network through `WebFetch`, `WebSearch`, and MCP servers.

- **Disable web tools** if the agent does not need internet access: `->disallow(['WebFetch', 'WebSearch'])`.
- **Use MCP server authentication** to control access to external services. See [[MCP Servers]].
- **Apply firewall rules** to the worker process or container to restrict outbound connections.
- **Block internal endpoints** -- ensure the agent cannot reach `169.254.169.254` (cloud metadata), internal APIs, or database ports.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// No network access at all
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob'])
    ->disallow(['WebFetch', 'WebSearch']);
```

## Logging and Auditing

Maintain a complete audit trail of all agent interactions.

- **Log every query** with the prompt, options, cost, duration, and result status.
- **Store message history** from `$result->messages()` for post-incident analysis.
- **Track tool usage** via `$result->toolUses()` to detect unusual patterns.
- **Set user identifiers** with `->user(auth()->id())` so logs are attributable.

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->user((string) auth()->id())
    ->tools(['Read', 'Grep']);

$result = ClaudeAgent::query($prompt, $options);

Log::info('Agent query completed', [
    'user_id'  => auth()->id(),
    'prompt'   => mb_substr($prompt, 0, 200),
    'success'  => $result->isSuccess(),
    'cost_usd' => $result->costUsd(),
    'duration' => $result->durationMs(),
    'turns'    => $result->turns(),
    'tools'    => collect($result->toolUses())->pluck('name')->countBy()->toArray(),
]);
```

> **Note:** If your application processes personal data, consider GDPR and data-retention obligations. Prompts and responses may contain PII -- apply the same retention policies as you would to any user-generated content.

## Environment-Specific Recommendations

| Setting | Development | Staging | Production |
|---------|:-----------:|:-------:|:----------:|
| Permission mode | `acceptEdits` | `dontAsk` | `dontAsk` |
| Allowed tools | Read, Grep, Glob, Edit, Write, Bash | Read, Grep, Glob | Read, Grep, Glob |
| `max_budget_usd` | 2.00 | 0.50 | 1.00 |
| `max_turns` | 25 | 10 | 15 |
| `process_timeout` | 300 | 120 | 120 |
| Sandboxing | Optional | Recommended | Required |
| Audit logging | Optional | Enabled | Enabled |
| Input validation | Basic | Full | Full |
| API key source | `.env` file | Secrets manager | Secrets manager |
| Working directory | `base_path()` | Scoped directory | Scoped directory |

## Next Steps

- [[Configuration]] -- Full config reference for permission modes, tools, and limits
- [[Hooks]] -- Advanced hook patterns for security enforcement
- [[Custom API Providers]] -- Secure credential management for Bedrock, Vertex AI, and proxies
- [[Production Deployment]] -- Queue integration, monitoring, and scaling
- [[Error Handling]] -- Catch and respond to failures gracefully
