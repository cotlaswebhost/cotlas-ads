# Cotlas Ads

Lightweight, self-hosted advertising management for Cotlas news portals.

## Publishing an update

1. Update the `Version` header and `COTLAS_ADS_VERSION` in `cotlas-ads.php`.
2. Commit and push the changes to `main`.
3. In GitHub, open **Releases**, choose **Draft a new release**, and create a matching version tag, for example `v0.4.1`.
4. Create an installable `cotlas-ads.zip` whose top-level folder is `cotlas-ads`, attach it to the release, and publish the release manually.

Installed client sites check `cotlaswebhost/cotlas-ads` GitHub Releases through WordPress's normal plugin updater. Release tags must be valid versions and newer than the installed plugin version.

This repository intentionally has no GitHub Actions release workflow. Pushing commits or version tags does not create a release automatically.

## Advertiser access

Create a WordPress user with the **Ad Advertiser** role, then assign that user from a campaign editor. Advertisers see only campaigns assigned to them. Administrators can separately allow protected editing of the campaign name, destination, status, and schedule. Creative markup, placements, targeting, weight, and caps remain administrator-only. CSV downloads and optional scheduled email summaries use Cotlas Ads' private first-party statistics.

## Analytics adapters

GA4 and Matomo forwarding is optional and disabled by default. The adapters use an existing `gtag()` function or Matomo `_paq` queue already loaded by the site; Cotlas Ads does not install or load either analytics service. Its own first-party rollups remain the authoritative campaign totals.

## Detection asset aliases

The ad-block detector can serve its paired probe scripts through configurable, neutral first-party `.js` paths. Keep the probe and control aliases different. Changing these aliases does not alter campaign, placement, or analytics URLs.

For a private repository, define `COTLAS_GITHUB_TOKEN` in `wp-config.php`. The current public repository does not require a token.

## Placement examples

```text
[cotlas_ad zone="above-content"]
```

```php
<?php echo cotlas_ad_zone('above-content'); ?>
```

See `FEATURE-MATRIX.md` for the current feature scope.
