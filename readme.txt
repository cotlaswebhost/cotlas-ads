=== Cotlas Ads ===
Contributors: cotlas
Tags: advertising, ad manager, banner, newsroom
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 0.3.11
License: GPLv2 or later

A lightweight, private advertising manager built for in-house news portals.

== Features ==

* Unlimited campaigns and placements.
* Image, HTML, affiliate, and ad-network creatives.
* Automatic right-to-left same-size image carousels with desktop/mobile targeting.
* Scoped MP4 video ad uploads with a configurable limit up to 50 MB, direct URLs, oEmbed URLs, and trusted embed code.
* Non-cropping configurable campaign canvases.
* Configurable advertisement labels below creatives.
* Optional dismissible or blocking ad-blocker request popup.
* Searchable multi-select campaign and placement assignment.
* Automatic, locked placement slugs so saved shortcodes stay stable.
* Trusted fallback creatives with complete HTML, inline CSS, and JavaScript.
* Weighted, random, and show-all rotation.
* Date, weekday, hour, device, country, login-state, impression, and click targeting.
* Privacy-friendly hourly impression and click rollups with bot filtering.
* Shortcode, PHP, block rendering, and automatic post-type injection.
* ads.txt and trusted header-snippet management.
* JSON backup and migration.
* Updates from GitHub Releases.
* Advertiser reporting role and separate WordPress capabilities.
* No license service, telemetry, SaaS dependency, or remote update service.

== Placement ==

Use `[cotlas_ad zone="homepage-leaderboard"]` or `<?php echo cotlas_ad_zone('homepage-leaderboard'); ?>`.
For one campaign use `[cotlas_ad id="123"]` or `<?php echo cotlas_ad(123); ?>`.

== Privacy ==

Analytics are aggregated by hour. Cotlas Ads does not store visitor IP addresses, cookies, or user identifiers.

The optional ad-blocker notice uses a local browser-side bait element. No visitor data is sent to an external detection service.

== Notes ==

Country targeting reads a country code supplied by the reverse proxy (`CF-IPCountry` or `X-Country-Code`). If no trusted country header exists, the campaign remains eligible.
