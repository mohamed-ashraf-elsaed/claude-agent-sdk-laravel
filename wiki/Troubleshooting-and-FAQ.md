# Troubleshooting and FAQ

> Solutions for common issues, debugging strategies, and answers to frequently asked questions about the Claude Agent SDK for Laravel.

## Installation Issues

### "Claude CLI not found"

**Cause:** The SDK cannot locate the `claude` binary. The PHP process has a different `$PATH` than your terminal session.

**Diagnosis:**

```bash
# Verify the CLI is installed
which claude
claude --version

# Check what PHP sees
php -r "echo exec('which claude');"
```

**Solutions:**

1. **Install globally:** `npm install -g @anthropic-ai/claude-code`
2. **Set an explicit path** in `config/claude-agent.php`:
   ```php
   'cli_path' => '/usr/local/bin/claude',
   ```
3. **Symlink to a standard location** that PHP can access:
   ```bash
   sudo ln -s $(which claude) /usr/local/bin/claude
   ```

> **Note:** When PHP runs as `www-data` (Apache/Nginx), the user's npm global bin directory is typically not on its `$PATH`. An explicit `cli_path` is the most reliable fix.

### "Permission denied"

**Cause:** The PHP process user does not have execute permission on the `claude` binary, or does not have read/write access to the working directory.

**Solutions:**

```bash
# Make the CLI executable
chmod +x $(which claude)

# Verify the PHP user can access the working directory
sudo -u www-data ls /path/to/your/project

# Check file ownership
ls -la $(which claude)
```

If running in a container, ensure the binary is installed during the Docker build and is accessible to the runtime user.

### Composer Dependency Conflicts

**Cause:** Version constraints in your `composer.json` conflict with the SDK's requirements (PHP 8.1+, certain Symfony Process versions).

**Solutions:**

```bash
# Check what is conflicting
composer why-not anthropic/claude-agent-sdk

# Update with dependency resolution
composer require anthropic/claude-agent-sdk --with-all-dependencies

# If needed, update the conflicting package first
composer update symfony/process --with-all-dependencies
```

---

## Runtime Issues

### Query Hangs Indefinitely

**Possible causes:**

1. **No `process_timeout` set** -- the process runs until the CLI finishes or the PHP max execution time kills it.
2. **CLI waiting for interactive input** -- permission mode is `default` and the CLI prompts for tool confirmation.
3. **Network issue** -- the CLI cannot reach the Anthropic API.

**Solutions:**

```php
// Always set a timeout in production
'process_timeout' => 120, // Kill after 2 minutes

// Use non-interactive permission mode
'permission_mode' => 'dontAsk',
'allowed_tools'   => ['Read', 'Grep', 'Glob'],
```

> **Tip:** In queue workers, set `process_timeout` lower than your queue connection's `retry_after` to prevent overlapping retries.

### "Process failed with exit code 1"

This is a `ProcessException`. Common causes:

- **Authentication failure:** `ANTHROPIC_API_KEY` is missing or invalid
- **Invalid CLI flags:** an option the installed CLI version does not support
- **Model not available:** the requested model is not accessible with your API key

**Debug by inspecting stderr:**

```php
use ClaudeAgentSDK\Exceptions\ProcessException;

try {
    $result = ClaudeAgent::query('...');
} catch (ProcessException $e) {
    dd($e->exitCode, $e->stderr);
}
```

### Empty Result / Null Text

If `$result->text()` returns `null` or an empty string:

1. **Check for errors first:** `$result->isError()` may be `true`
2. **Max turns reached:** the agent ran out of turns before producing a final answer
3. **Budget exceeded:** the spend ceiling stopped the conversation early
4. **No ResultMessage:** if the messages array has no `ResultMessage`, `text()` returns `null`

```php
$result = ClaudeAgent::query('...');

if ($result->isError()) {
    Log::warning('Agent error', ['text' => $result->text(), 'turns' => $result->turns()]);
}

// Inspect all messages for debugging
foreach ($result->messages as $msg) {
    dump(get_class($msg), $msg);
}
```

### JSON Parse Errors

**Cause:** The CLI outputs a line that looks like JSON (starts with `{` or `[`) but is malformed. This typically happens with:

- A CLI version mismatch (SDK expects a different output format)
- Corrupt or truncated output from a killed process
- Interleaved debug output from verbose mode

**Solutions:**

```bash
# Update the CLI to the latest version
npm update -g @anthropic-ai/claude-code

# Verify CLI output format
claude --print "Hello" --output-format stream-json --verbose 2>/dev/null
```

### Out of Memory

**Cause:** The agent reads a very large codebase or produces extensive output, causing PHP memory exhaustion.

**Solutions:**

- **Use streaming:** `stream()` uses constant memory via generators; `query()` holds everything in memory
- **Reduce tool scope:** limit `allowedTools` and set `cwd` to a subdirectory
- **Increase PHP memory limit** if the response itself is legitimately large
- **Set `max_turns`** to prevent unbounded conversation growth

```php
// Stream instead of query for large tasks
foreach (ClaudeAgent::stream('Analyze all files', $options) as $message) {
    // Process each message individually -- previous messages can be GC'd
}
```

---

## Cost Issues

### Query Costs More Than Expected

**Possible causes:**

- **Extended thinking tokens:** if `max_thinking_tokens` is set, thinking tokens add to cost
- **Cache misses:** first queries are more expensive; subsequent queries benefit from prompt caching
- **Multiple turns:** the agent may take many tool-use turns, each incurring API calls
- **Large context:** reading many files pushes input token count up

**Diagnosis:**

```php
$result = ClaudeAgent::query('...');
echo "Cost: $" . $result->costUsd() . "\n";
echo "Turns: " . $result->turns() . "\n";
echo "Cache read: " . $result->cacheReadTokens() . "\n";
echo "Cache created: " . $result->cacheCreationTokens() . "\n";

foreach ($result->modelUsage() as $model => $usage) {
    echo "{$model}: input={$usage->inputTokens}, output={$usage->outputTokens}, "
       . "cacheHit={$usage->cacheHitRate()}\n";
}
```

### Budget Exceeded Error

When `max_budget_usd` is exceeded, the agent stops and returns a result-level error (not an exception):

```php
$result = ClaudeAgent::query('...', ClaudeAgentOptions::make()->maxBudgetUsd(0.10));

if ($result->isError()) {
    // Likely budget exceeded or max turns
    echo $result->text(); // "Budget exceeded"
}
```

**Solutions:** Increase `max_budget_usd`, reduce `max_turns`, limit tools, or use a cheaper model.

---

## Session Issues

### Cannot Resume Session

**Possible causes:**

- **Session expired:** old sessions may be cleaned up by the CLI
- **Different user:** sessions are tied to the OS user that created them
- **Different machine:** session data is stored locally in `~/.claude/sessions/`
- **Invalid session ID:** the stored ID is corrupted or from a different environment

**Solution:** Treat sessions as ephemeral. Store session IDs with a TTL (2-4 hours) and handle resume failures gracefully:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Exceptions\ProcessException;

$sessionId = cache()->get("agent_session:{$userId}");

try {
    $options = $sessionId
        ? ClaudeAgentOptions::make()->resume($sessionId)
        : ClaudeAgentOptions::make();

    $result = ClaudeAgent::query('Continue the analysis', $options);
} catch (ProcessException $e) {
    // Session may have expired -- start fresh
    $result = ClaudeAgent::query('Start a new analysis');
}

cache()->put("agent_session:{$userId}", $result->sessionId, now()->addHours(2));
```

### continueConversation() Not Working

`continueConversation()` uses the CLI's `--continue` flag, which resumes the last session for the **current CLI user on the current machine**. In a web server context, this means the `www-data` user's last session -- which is almost certainly not what you want.

**Solution:** Always use `resume($sessionId)` with an explicit session ID in multi-user or server environments. Reserve `continueConversation()` for local development and single-user CLI scripts.

---

## Provider Issues

### Bedrock / Vertex / Custom Auth Failures

When using cloud providers, authentication is handled by the CLI through environment variables:

**AWS Bedrock:**

```dotenv
CLAUDE_CODE_USE_BEDROCK=true
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_REGION=us-east-1
```

**Google Vertex AI:**

```dotenv
CLAUDE_CODE_USE_VERTEX=true
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json
CLOUD_ML_REGION=us-central1
```

**Custom API endpoint:**

```dotenv
ANTHROPIC_BASE_URL=https://your-proxy.example.com/v1
ANTHROPIC_AUTH_TOKEN=your-bearer-token
```

> **Note:** Enable only one provider at a time. If auth fails, verify credentials work with the CLI directly: `claude --print "Hello"`.

---

## Frequently Asked Questions

**Does the SDK support PHP 8.0?**
No. PHP 8.1 or higher is required due to the use of enums, readonly properties, fibers, and intersection types.

**Can I use the SDK without Laravel?**
Yes. Instantiate `ClaudeAgentManager` directly without the service provider:

```php
use ClaudeAgentSDK\ClaudeAgentManager;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$agent = new ClaudeAgentManager([
    'model'           => 'claude-sonnet-4-5-20250929',
    'permission_mode' => 'dontAsk',
    'allowed_tools'   => ['Read', 'Grep'],
]);

$result = $agent->query('Analyze this code');
```

**How much does each query cost?**
Cost varies by model, input size, output length, thinking tokens, and cache hit rate. Use `$result->costUsd()` to track actual costs. A simple question typically costs $0.001-$0.01; complex multi-turn tasks can cost $0.10-$1.00+.

**Can the agent access the internet?**
Yes, if you include `WebFetch` and/or `WebSearch` in the allowed tools. By default, no web tools are enabled.

**Is work persisted across HTTP requests?**
Not automatically. Use [[Session Management]] to store the `sessionId` and resume conversations across requests.

**Can I run multiple queries concurrently?**
Yes. Each `query()` or `stream()` call spawns a separate CLI process. You can run them in parallel using Laravel jobs, async processes, or concurrent HTTP requests. Each process is independent.

**How do I limit which files the agent can access?**
Set `cwd` to a specific directory and restrict tools. The agent operates within the working directory. Use `addDir()` to grant access to additional directories without changing `cwd`. See [[Options Reference]] for details.

**Can I use this in serverless environments (AWS Lambda)?**
Yes, provided the Claude Code CLI is installed in the Lambda runtime (e.g. via a Lambda layer with Node.js). Be aware of cold start times -- the CLI binary needs to initialize on each cold start. Set a reasonable `process_timeout` to account for this.

**What happens if the CLI crashes mid-query?**
A `ProcessException` is thrown with the exit code and stderr. If you are using `stream()`, you may have received partial `AssistantMessage` objects before the crash -- these are still usable but the conversation did not complete successfully.

**How do I update the SDK?**

```bash
composer update anthropic/claude-agent-sdk
```

Check the changelog for breaking changes before upgrading major versions.

**Can I use a custom or fine-tuned model?**
Yes, pass any model identifier the CLI supports via `ClaudeAgentOptions::make()->model('your-model-id')` or set it in config. The SDK passes the model string directly to the CLI.

**How do I see what CLI command the SDK is running?**
Enable verbose logging or inspect the options object:

```php
$options = ClaudeAgentOptions::make()->model('claude-sonnet-4-5-20250929')->tools(['Read']);
dd($options->toCliArgs());
```

---

## Getting Help

- **GitHub Issues:** Report bugs and request features at the [claude-agent-sdk-laravel repository](https://github.com/anthropic/claude-agent-sdk-laravel/issues)
- **Contributing:** See `CONTRIBUTING.md` in the repository root for development setup and pull request guidelines

## Next Steps

- [[Error Handling]] -- exception hierarchy and comprehensive error patterns
- [[Testing Your Integration]] -- mock the SDK for fast, free tests
- [[Configuration]] -- full config reference and environment-specific setup
- [[Home]] -- back to the documentation index
