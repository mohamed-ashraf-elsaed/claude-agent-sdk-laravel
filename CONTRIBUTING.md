# Contributing to Claude Agent SDK for Laravel

Thank you for considering contributing! Here's how to get started.

---

## Development Setup

### 1. Fork & Clone

```bash
git clone git@github.com:YOUR_USERNAME/claude-agent-sdk-laravel.git
cd claude-agent-sdk-laravel
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Run Tests

```bash
vendor/bin/phpunit
```

All tests must pass before submitting a PR.

---

## Branching Strategy

| Branch     | Purpose                    |
|------------|----------------------------|
| `main`     | Stable releases only       |
| `develop`  | Active development         |
| `feature/*`| New features               |
| `fix/*`    | Bug fixes                  |
| `docs/*`   | Documentation changes only |

### Workflow

```bash
# Create a feature branch from develop
git checkout develop
git pull origin develop
git checkout -b feature/my-feature

# Work, commit, push
git add -A
git commit -m "feat: add my feature"
git push origin feature/my-feature
```

Then open a **Pull Request** into `develop`.

---

## Commit Message Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): short description
```

| Type       | When to use                        |
|------------|------------------------------------|
| `feat`     | New feature                        |
| `fix`      | Bug fix                            |
| `docs`     | Documentation only                 |
| `test`     | Adding or fixing tests             |
| `refactor` | Code change (no new feature/fix)   |
| `chore`    | Build, CI, tooling changes         |
| `breaking` | Breaking API change                |

Examples:
```
feat(options): add timeout option to ClaudeAgentOptions
fix(transport): handle empty stderr on process failure
docs(readme): add MCP server examples
test(messages): add AssistantMessage edge case tests
```

---

## Pull Request Guidelines

### Before Submitting

- [ ] All tests pass: `vendor/bin/phpunit`
- [ ] No unused variables or imports
- [ ] Follow existing code style and patterns
- [ ] Add tests for any new functionality
- [ ] Update README/docs if adding public API changes

### PR Title

Use the same convention as commit messages:
```
feat(scope): description
```

### PR Description

Include:
- **What** — what this PR does
- **Why** — why the change is needed
- **How** — brief explanation of approach (if non-obvious)
- **Testing** — how you tested it

---

## Code Style

- **PSR-12** coding standard
- **Readonly properties** where possible (PHP 8.1+)
- **Named arguments** in constructors
- **Return types** on all methods
- **No** unnecessary abstractions
- **No** unused code

### Naming Conventions

| Element         | Convention           | Example                   |
|-----------------|----------------------|---------------------------|
| Classes         | PascalCase           | `ClaudeAgentOptions`      |
| Methods         | camelCase            | `toolUses()`              |
| Properties      | camelCase            | `$sessionId`              |
| Config keys     | snake_case           | `permission_mode`         |
| CLI flags       | kebab-case           | `--max-turns`             |
| Test methods    | snake_case with test | `test_parses_text_block`  |

---

## Adding a New Feature

1. **Add the source code** in `src/`
2. **Add tests** in `tests/Unit/` or `tests/Feature/`
3. **Update options** if it's a new CLI flag (in `ClaudeAgentOptions`)
4. **Update README** with usage example
5. **Update wiki** if it's a major feature
6. **Update CHANGELOG.md** under `[Unreleased]`

---

## Testing

### Run full suite
```bash
vendor/bin/phpunit
```

### Run specific test
```bash
vendor/bin/phpunit --filter=test_parses_text_block
```

### Run specific suite
```bash
vendor/bin/phpunit --testsuite=Unit
```

### Run with coverage
```bash
vendor/bin/phpunit --coverage-html coverage/
```

### Test structure

```
tests/
├── Unit/
│   ├── Content/          # ContentBlock tests
│   ├── Messages/         # Message parsing tests
│   ├── Options/          # ClaudeAgentOptions tests
│   ├── Agents/           # AgentDefinition tests
│   ├── Tools/            # McpServerConfig tests
│   ├── Hooks/            # Hook system tests
│   ├── Exceptions/       # Exception tests
│   ├── QueryResultTest.php
│   ├── ClaudeAgentManagerTest.php
│   └── Transport/
├── Feature/
│   └── ServiceProviderTest.php
└── TestCase.php
```

---

## Reporting Bugs

Open an issue with:
- PHP and Laravel version
- Package version
- Steps to reproduce
- Expected vs actual behavior
- Error output / stack trace

---

## Questions?

Open a [Discussion](https://github.com/mohamed-ashraf-elsaed/claude-agent-sdk-laravel/discussions) on GitHub.

Thank you for contributing!