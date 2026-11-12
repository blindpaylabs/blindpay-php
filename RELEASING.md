# Releasing blindpay/php

## Version source of truth

`private const VERSION = 'X.Y.Z';` in `src/BlindPay.php`. It is sent in the `User-Agent` and it decides the git tag. `composer.json` has no `version`: Packagist and Composer read versions from git tags only. Bump `VERSION` in the PR itself (SemVer table in `CLAUDE.md` section 9); `scripts/api-sync.php --apply` bumps it in sync PRs. Keep the exact form of the line, both the workflow and api-sync parse it with a regex.

## What a merge to `main` triggers

1. **Main** (`main.yaml`): lint, tests on PHP 8.2/8.4/8.5 (`composer validate --strict`, Pest, PHPStan), contract check, API sync check, Snyk.
2. **Release** (`release.yaml`, also `workflow_dispatch`): reads `VERSION`. If `vX.Y.Z` is not on the remote yet it runs `composer validate --strict`, `composer install`, the tests and the contract check on PHP 8.2, then pushes the tag and runs `gh release create vX.Y.Z --generate-notes`. If the tag exists, it does nothing, so a merge without a bump releases nothing.
3. **Packagist** picks the tag up through the repo's GitHub webhook (`https://packagist.org/api/github`); no workflow publishes anything.

Nothing gates the merge: `main` has no branch protection and `api-sync.yml` auto-merges sync PRs. Merge only after Main is green.

## Secrets and owners

| What | Used by | Where it lives |
|---|---|---|
| `GITHUB_TOKEN` (`contents: write`) | `release.yaml` tag + GitHub release | issued by GitHub per run |
| `SDK_SYNC_PAT` | `api-sync.yml` push + auto-merge, `api-sync-merged.yml` | GitHub Actions secret |
| `SNYK_TOKEN` | `snyk.yaml` | GitHub Actions secret |
| `SLACK_WEBHOOK_URL` | `pipeline-alert.yml` | GitHub Actions secret |
| Packagist GitHub webhook | publishing | repo Settings > Webhooks (`https://packagist.org/api/github`); the `blindpay/php` package maintainers on packagist.org |

Who administers the secrets, the webhook and the Packagist package is not recorded here; ask the repository admins. No OIDC is involved: Packagist publishes from tags.

## Re-running a stuck release

**Known trap: tag-on-push has never completed on a real push event.** The push-triggered runs for the merge of #46 sat `queued` with no jobs, and v4.0.0 was tagged by a manual dispatch. After every release merge, check that the Release run starts:

```bash
gh run list -R blindpaylabs/blindpay-php --workflow release.yaml -L 3
```

If it stays `queued` with no jobs, cancel it and dispatch the workflow on `main` (it is idempotent: it tags only a missing version):

```bash
gh run cancel <run-id> -R blindpaylabs/blindpay-php
gh workflow run release.yaml -R blindpaylabs/blindpay-php --ref main
```

If the tag exists but Packagist does not show it, re-deliver the last webhook delivery (repo Settings > Webhooks) or press "Update" on the package page.

## Verifying the published package

```bash
curl -s https://repo.packagist.org/p2/blindpay/php.json | jq -r '.packages["blindpay/php"][0].version'   # expect vX.Y.Z
mkdir /tmp/bp && cd /tmp/bp && composer require blindpay/php:X.Y.Z && php -r 'require "vendor/autoload.php"; new BlindPay\SDK\BlindPay(apiKey: "k", instanceId: "in_000000000000"); echo "ok\n";'
```

## Other traps

- `release.yaml` tags only `HEAD` of `main`. An older line (for example v3.5.0 at `c7f26f8`, never tagged) needs a manual `gh release create v3.5.0 --target c7f26f8 --latest=false`, an owner decision.
- Update `composer.lock` under PHP 8.2 (the floor). A lock resolved on a newer PHP can pin dev packages that do not install on 8.2, or (as paratest 7.8.4 did) on 8.5.
- A tag is a release: never move or delete a pushed tag; ship a new patch instead.
