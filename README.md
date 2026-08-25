# Cotlas Ads

Lightweight, self-hosted advertising management for Cotlas news portals.

## Publishing an update

1. Update the `Version` header and `COTLAS_ADS_VERSION` in `cotlas-ads.php`.
2. Commit and push the changes to `main`.
3. Create and push a matching version tag, for example `v0.3.5`.
4. The release workflow creates a GitHub Release and attaches `cotlas-ads.zip`.

Installed client sites check `cotlaswebhost/cotlas-ads` GitHub Releases through WordPress's normal plugin updater. Release tags must be valid versions and newer than the installed plugin version.

For a private repository, define `COTLAS_GITHUB_TOKEN` in `wp-config.php`. The current public repository does not require a token.

## Placement examples

```text
[cotlas_ad zone="above-content"]
```

```php
<?php echo cotlas_ad_zone('above-content'); ?>
```

See `FEATURE-MATRIX.md` for the current feature scope.
