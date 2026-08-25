# Cotlas Ads feature matrix

Cotlas Ads is an original implementation. It does not load or modify either AdRotate package.

## Included in 0.3.8

- Campaign and placement management
- HTML/ad-tag and Media Library image creatives
- Same-dimension, automatic right-to-left image carousel creatives with adjustable intervals
- Video creatives from campaign-only MP4 uploads, direct URLs, YouTube/Vimeo oEmbed URLs, or trusted embed code; configurable 20 MB default and 50 MB hard maximum
- Configurable responsive canvas dimensions using non-cropping `object-fit: contain`
- Campaign-to-placement assignment from either editor
- Scheduled and automatically expired runtime statuses
- Per-campaign 30-day impression, click, and CTR reporting
- GitHub Releases update integration
- Customizable advertisement disclosure labels
- Optional local ad-block detection with dismissible and blocking modes
- Searchable, dependency-free multi-select assignment controls
- Native WordPress Update URI discovery with strict version comparison and post-update cache invalidation
- Automatic immutable placement slugs and improved searchable assignment filtering
- Layered ad-block detection using a first-party script probe, a bait element, and real creative visibility
- Weighted, equal-random, and show-all rotation
- Start/end scheduling, weekday and hour windows
- Desktop, mobile, and tablet targeting
- Reverse-proxy country allowlists (Cloudflare and `X-Country-Code`)
- Logged-in reader suppression
- Lifetime impression and click caps
- Cookie-free hourly analytics and crawler filtering
- Shortcode, PHP, dynamic block, and automatic content placement
- Custom-post-type injection (including `product` when configured)
- `ads.txt` and trusted header snippets
- JSON export/import
- Granular WordPress capabilities and advertiser reporting role
- Responsive, dependency-free administration UI
- Automatic analytics retention cleanup

## Planned compatibility work

These are not represented as complete in 0.3.8 and should be developed against explicit newsroom requirements rather than copied from a third-party implementation:

- A migration wizard for AdRotate free database records
- Dedicated advertiser self-service campaign editing and email notifications
- HTML5 ZIP validation/extraction and asset sandboxing
- Google Analytics and Matomo event adapters
- MaxMind local database adapter and deny-list geo rules
- Multi-schedule campaign editor
- Budget pacing across a campaign window
- CSV report export and scheduled email reports
- Multisite network administration
- REST API and WP-CLI commands
- Adblock-resistant asset aliases
- Accessibility and load testing on the target portal theme stack

## Clean-room boundary

Public behavior and the GPL edition may be used for compatibility research. The proprietary Pro source must not be copied, renamed, relicensed, or used as the implementation basis.
