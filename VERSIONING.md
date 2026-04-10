# Versioning Guide — BlindPay PHP SDK

This document describes how to manage and release new versions of the `blindpay/php` package.

## Versioning Strategy

This package follows [Semantic Versioning (SemVer)](https://semver.org/):

```
vMAJOR.MINOR.PATCH
```

| Increment | When to use | Example |
|-----------|-------------|---------|
| **MAJOR** | Breaking changes that require consumers to update their code (e.g., removed classes, renamed methods, changed return types) | `v1.3.0` → `v2.0.0` |
| **MINOR** | New features or functionality added in a backward-compatible manner (e.g., new enums, new optional fields, new endpoints) | `v1.3.0` → `v1.4.0` |
| **PATCH** | Backward-compatible bug fixes, refactors, documentation, or small corrections | `v1.3.0` → `v1.3.1` |

### Decision Examples

| Change | Version Bump |
|--------|-------------|
| Add a new enum (e.g., `BankingPartner`) | **MINOR** |
| Add a new optional field to an input class | **MINOR** |
| Add a new API resource/endpoint | **MINOR** |
| Make a previously required field optional | **MINOR** (backward-compatible relaxation) |
| Remove a field from an input class | **MAJOR** (breaking) |
| Add a new required field to an input class | **MAJOR** (breaking) |
| Rename a class or method | **MAJOR** (breaking) |
| Fix a bug in serialization/deserialization | **PATCH** |
| Fix a typo in code or docs | **PATCH** |
| Refactor internals without changing public API | **PATCH** |

> **Note:** While we are on `v1.x`, we treat breaking changes with care. If a PR introduces both new features **and** breaking changes, bump **MAJOR**. If the breaking change is minor and agreed upon by the team, a **MINOR** bump may be acceptable — but it must be explicitly called out in the release notes.

## How to Release a New Version

### Step 1: Ensure All Changes Are Merged to `main`

All feature branches must be merged into `main` via Pull Request before creating a release.

### Step 2: Determine the Version Number

Based on the changes since the last release (`v1.3.0`), determine the appropriate version bump using the SemVer rules above.

Check the current latest release:

```bash
gh release list --limit 1
```

Review commits since the last release:

```bash
git log v1.3.0..HEAD --oneline
```

### Step 3: Create the GitHub Release

We use **GitHub Releases** to tag and publish versions. The git tag is created automatically by GitHub when you create a release.

#### Via GitHub CLI

```bash
gh release create v1.4.0 \
  --title "v1.4.0" \
  --target main \
  --notes "$(cat <<'EOF'
## Installation
```bash
composer require blindpay/php
```

## What's Changed

- Add new feature X
- Update input type Y
- Fix bug in Z

**Full Changelog**: https://github.com/blindpaylabs/blindpay-php/compare/v1.3.0...v1.4.0
EOF
)"
```

#### Via GitHub Web UI

1. Go to the [Releases page](https://github.com/blindpaylabs/blindpay-php/releases)
2. Click **"Draft a new release"**
3. In **"Choose a tag"**, type the new version (e.g., `v1.4.0`) and select **"Create new tag on publish"**
4. Set **Target** to `main`
5. Set **Release title** to the version (e.g., `v1.4.0`)
6. Write the release notes following the template below
7. Click **"Publish release"**

### Release Notes Template

Every release should follow this format (consistent with all previous releases):

```markdown
## Installation
```bash
composer require blindpay/php
```

## What's Changed

- Brief description of change 1
- Brief description of change 2
- Brief description of change 3

**Full Changelog**: https://github.com/blindpaylabs/blindpay-php/compare/vPREVIOUS...vNEW
```

## Release Checklist

Before publishing a release, make sure:

- [ ] All changes are merged to `main`
- [ ] Tests pass (`composer run test`)
- [ ] Linting passes (`composer run lint:check`)
- [ ] CI pipeline is green
- [ ] Version number follows SemVer rules
- [ ] Release notes are written and follow the template
- [ ] The `Full Changelog` comparison link points to the correct previous version

## Version History

| Version | Date | Type | Summary |
|---------|------|------|---------|
| v1.3.0 | 2025-11-13 | Minor | New TOS and Solana endpoints |
| v1.2.0 | 2025-11-13 | Minor | SWIFT code bank details endpoint |
| v1.1.5 | 2025-10-21 | Patch | Add TOS_ACCEPTED webhook event type |
| v1.1.4 | 2025-10-21 | Patch | Nullable fields and wrapper classes for autocomplete |
| v1.1.3 | 2025-10-21 | Patch | Update API endpoint paths |
| v1.1.2 | 2025-10-21 | Patch | PSR-4 autoloading compliance |
| v1.1.1 | 2025-10-21 | Patch | Fix namespace |
| v1.0.0 | 2025-10-21 | Major | Initial release |

## FAQ

### How does Composer resolve the package version?

Composer resolves the version from **git tags**. There is no `version` field in `composer.json` — the tag name (e.g., `v1.4.0`) is the version. This is the standard approach for Composer packages.

### Do I need to update `composer.json` when releasing?

No. The version is derived from the git tag. You do **not** need to add or modify a `version` field in `composer.json`.

### What if my PR has both features and breaking changes?

If your PR introduces a **breaking change** (removing a field, adding a required parameter, changing a type signature), the release must be at least a **MINOR** bump (or **MAJOR** for significant breaks). Document all breaking changes clearly in the release notes so consumers know what to update.

### Who can create releases?

Any maintainer with write access to the repository can create releases via the GitHub UI or CLI.
