# Release Checklist (Maintainer)

Personal checklist for publishing a new version.

---

## Pre-Release

### 1. Ensure Clean State

```bash
git checkout main
git pull origin main
git status  # Must be clean
```

### 2. Run Full Test Suite

```bash
composer install
vendor/bin/phpunit
```

**All tests MUST pass. Do not release with failing tests.**

### 3. Check for Breaking Changes

- [ ] Any public method signatures changed?
- [ ] Any class/interface renamed or removed?
- [ ] Any config keys changed?
- [ ] Any constructor parameters changed?

If yes → this is a **major** version bump.

### 4. Update CHANGELOG.md

Move items from `[Unreleased]` to the new version:

```markdown
## [1.2.0] - 2025-XX-XX

### Added
- New `timeout()` option for ClaudeAgentOptions

### Fixed
- Fixed JsonParseException property conflict

### Changed
- Renamed `$line` to `$rawLine` in JsonParseException
```

### 5. Update Version References (if any)

Check these files for hardcoded versions:
- `README.md` (badges, install examples)
- `CHANGELOG.md`
- Any documentation referencing specific versions

### 6. Commit Changelog

```bash
git add CHANGELOG.md
git commit -m "chore: prepare release vX.Y.Z"
git push origin main
```

---

## Release

### 7. Tag the Release

```bash
# Determine version using semver:
#   MAJOR.MINOR.PATCH
#   - MAJOR: breaking changes
#   - MINOR: new features (backward compatible)
#   - PATCH: bug fixes only

git tag -a vX.Y.Z -m "vX.Y.Z — Short description"
git push origin --tags
```

### 8. Create GitHub Release

1. Go to: https://github.com/mohamed-ashraf-elsaed/claude-agent-sdk-laravel/releases
2. Click **Draft a new release**
3. Select the tag you just pushed
4. Title: `vX.Y.Z`
5. Description: Copy from CHANGELOG.md for this version
6. Check **Set as the latest release**
7. Click **Publish release**

### 9. Verify Packagist

1. Go to: https://packagist.org/packages/mohamed-ashraf-elsaed/claude-agent-sdk-laravel
2. Confirm the new version appears
3. If webhook is set up, it should update automatically within a few minutes
4. If NOT updated: click **Update** button on Packagist

### 10. Verify Installation

```bash
# In a fresh test project
mkdir /tmp/test-install && cd /tmp/test-install
composer init --no-interaction
composer require mohamed-ashraf-elsaed/claude-agent-sdk-laravel:^X.Y
```

---

## Post-Release

### 11. Update Wiki (if needed)

- Any new features need wiki pages
- Update API Reference if public API changed
- Update Installation page if requirements changed

### 12. Announce (optional)

- GitHub Discussion
- Twitter/X
- Laravel News community

---

## Hotfix Process

For urgent fixes on a released version:

```bash
# Branch from the tag
git checkout -b fix/critical-bug vX.Y.Z

# Fix, test, commit
vendor/bin/phpunit
git commit -m "fix: critical bug description"

# Merge to main
git checkout main
git merge fix/critical-bug
git push origin main

# Tag patch release
git tag -a vX.Y.(Z+1) -m "vX.Y.(Z+1) — Hotfix: description"
git push origin --tags
```

---

## Semver Quick Reference

| Change Type                          | Version Bump | Example         |
|--------------------------------------|-------------|-----------------|
| Bug fix, no API change               | PATCH       | 1.0.0 → 1.0.1  |
| New feature, backward compatible     | MINOR       | 1.0.1 → 1.1.0  |
| Breaking change (rename, remove, etc)| MAJOR       | 1.1.0 → 2.0.0  |

### What counts as breaking?

- Removing a public method or class
- Changing a method signature (parameters, return type)
- Renaming a config key
- Changing constructor required parameters
- Dropping PHP or Laravel version support

### What is NOT breaking?

- Adding a new method
- Adding a new optional parameter with default
- Adding a new config key with default
- Adding a new class
- Internal refactoring (no public API change)

---

## Files to Check Before Every Release

```
[ ] CHANGELOG.md        — Updated with new entries
[ ] README.md           — Examples still accurate
[ ] composer.json        — Dependencies correct
[ ] config/claude-agent.php — New config keys have defaults
[ ] phpunit.xml          — Test suites configured
[ ] .github/workflows/   — CI still covers correct PHP/Laravel versions
[ ] wiki/                — Docs match current API
```