# Cotlas Ads

Lightweight, self-hosted advertising management for Cotlas news portals.

## Publishing an update

1. Update the `Version` header and `COTLAS_ADS_VERSION` in `cotlas-ads.php`.
2. Commit and push the changes to `main`.
3. In GitHub, open **Releases**, choose **Draft a new release**, and create a matching version tag, for example `v0.5.6`.
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

## Automatic injection

The **Injection** tab supports multiple independent rules. Standard placements can be inserted before or after singular content, after a numbered paragraph, before a numbered archive/feed item, after the opening body tag as a visible header, in the footer, before the first sidebar widget, or after the last sidebar widget. Existing single-rule settings are shown in the new editor and migrated on save.

The visible-header rule deliberately uses WordPress's `wp_body_open` hook. Campaign markup cannot safely be placed inside the document `<head>`.

## Branded, interstitial, and sticky ads

The branded-card creative combines a brand logo, optional background image, title, description, linked button, canvas dimensions, and an optional custom CSS class. Its layout switches from horizontal to stacked when the available canvas becomes narrow.

Interstitial placements appear after a configurable number of ordinary link clicks. Sticky placements attach to the bottom of the viewport. Both include close buttons and per-browser cooldowns; sticky height is limited to 250px and 90px is recommended.

## Placement examples

```text
[cotlas_ad zone="above-content"]
```

```php
<?php echo cotlas_ad_zone('above-content'); ?>
```

See `FEATURE-MATRIX.md` for the current feature scope.
