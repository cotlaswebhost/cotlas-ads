# Cotlas Ads feature matrix

Cotlas Ads is an original implementation. It does not load or modify either AdRotate package.

## Included in 0.5.3

- Campaign and placement management
- HTML/ad-tag and Media Library image creatives
- Trusted placement fallback creatives with complete HTML, inline CSS, and JavaScript
- Same-dimension, automatic right-to-left image carousel creatives with adjustable intervals
- Persistent multi-image carousel editing with preloaded selections and explicit selection counts
- Full-width placement wrappers that prevent flex themes from shrinking campaign canvases
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
- Differential ad-block probing with a neutral control request and cache-safe probe versioning
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
- Per-campaign advertiser assignment with isolated reporting, protected self-service delivery editing, and CSV export
- Daily, weekly, or monthly advertiser summaries plus one-time expiry and cap alerts
- Optional GA4 and Matomo adapters that use existing site trackers without loading third-party scripts
- Configurable neutral first-party aliases for differential ad-block detection probes
- Responsive, dependency-free administration UI
- Automatic analytics retention cleanup
- Multiple independent injection rules for before/after content, numbered paragraphs, archive/feed items, visible headers, footers, and sidebar boundaries
- Responsive branded-card creatives with brand logo, background image, title, description, linked button, canvas adaptation, and a custom CSS class
- Link-click-triggered full-page interstitial placements with close controls and visitor cooldowns
- Closable fixed-bottom sticky placements with configurable cooldowns and a guarded 50–250px maximum height
- Collapsible injection-rule table with meaningful-paragraph counting that ignores empty and non-breaking-space paragraphs
- Branded-card desktop and 300×250 tablet/mobile layouts with full-aspect-ratio logos and upper-right mobile logo positioning
- Removable image previews and original-file rendering for campaign images and branded logos

## Planned compatibility work

These are not represented as complete in 0.5.3 and should be developed against explicit newsroom requirements rather than copied from a third-party implementation:

- A migration wizard for AdRotate free database records
- HTML5 ZIP validation/extraction and asset sandboxing
- MaxMind local database adapter and deny-list geo rules
- Multi-schedule campaign editor
- Budget pacing across a campaign window
- Multisite network administration
- REST API and WP-CLI commands
- Accessibility and load testing on the target portal theme stack

## Clean-room boundary

Public behavior and the GPL edition may be used for compatibility research. The proprietary Pro source must not be copied, renamed, relicensed, or used as the implementation basis.
