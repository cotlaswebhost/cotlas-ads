<?php
defined('ABSPATH') || exit;

final class Cotlas_Ads_Admin {
	private Cotlas_Ads_Repository $repository;

	public function __construct(Cotlas_Ads_Repository $repository) {
		$this->repository = $repository;
		add_action('admin_menu', array($this, 'menu'));
		add_action('admin_enqueue_scripts', array($this, 'assets'));
		add_action('admin_post_cotlas_save_ad', array($this, 'save_ad'));
		add_action('admin_post_cotlas_delete_ad', array($this, 'delete_ad'));
		add_action('admin_post_cotlas_save_zone', array($this, 'save_zone'));
		add_action('admin_post_cotlas_delete_zone', array($this, 'delete_zone'));
		add_action('admin_post_cotlas_save_settings', array($this, 'save_settings'));
		add_action('admin_post_cotlas_export', array($this, 'export'));
		add_action('admin_post_cotlas_import', array($this, 'import'));
		add_filter('upload_mimes', array($this, 'allow_scoped_video_mime'), 999);
		add_filter('wp_handle_upload_prefilter', array($this, 'validate_scoped_video_upload'), 999);
		add_action('wp_ajax_cotlas_ads_upload_video', array($this, 'ajax_upload_video'));
	}

	public function menu(): void {
		add_menu_page(__('Cotlas Ads', 'cotlas-ads'), __('Cotlas Ads', 'cotlas-ads'), 'cotlas_ads_manage', 'cotlas-ads', array($this, 'page'), 'dashicons-megaphone', 25);
	}

	public function assets(string $hook): void {
		if ($hook !== 'toplevel_page_cotlas-ads') return;
		wp_enqueue_style('cotlas-ads-admin', COTLAS_ADS_URL . 'assets/admin.css', array(), COTLAS_ADS_VERSION);
		wp_add_inline_style('cotlas-ads-admin', '.media-modal .attachment .check{opacity:0}.media-modal .attachment:hover .check,.media-modal .attachment.selected .check{opacity:1}');
		wp_enqueue_script('cotlas-ads-admin', COTLAS_ADS_URL . 'assets/admin.js', array(), COTLAS_ADS_VERSION, true);
		wp_enqueue_media();
		$settings = wp_parse_args(get_option('cotlas_ads_settings', array()), array('video_upload_enabled' => 0, 'video_max_mb' => 20));
		wp_localize_script('cotlas-ads-admin', 'cotlasAdsAdmin', array('videoEnabled' => !empty($settings['video_upload_enabled']), 'videoMaxBytes' => min(50, max(1, absint($settings['video_max_mb']))) * MB_IN_BYTES, 'videoUploadNonce' => wp_create_nonce('cotlas_ads_video_upload'), 'ajaxUrl' => admin_url('admin-ajax.php')));
	}

	public function page(): void {
		if (!current_user_can('cotlas_ads_manage')) wp_die(esc_html__('You cannot manage advertising.', 'cotlas-ads'));
		$tab = sanitize_key($_GET['tab'] ?? 'overview');
		$tabs = array('overview' => 'Overview', 'ads' => 'Campaigns', 'zones' => 'Placements', 'reports' => 'Analytics', 'settings' => 'Settings', 'tools' => 'Import / Export');
		?>
		<div class="wrap cotlas-shell">
			<header class="cotlas-header">
				<div><span class="cotlas-kicker">NEWSROOM AD OPERATIONS</span><h1>Cotlas Ads</h1><p>Fast, private campaign delivery from your own WordPress database.</p></div>
				<a class="cotlas-primary" href="<?php echo esc_url($this->url('ads', array('action' => 'new'))); ?>">+ New campaign</a>
			</header>
			<nav class="cotlas-tabs" aria-label="Cotlas Ads sections">
				<?php foreach ($tabs as $slug => $label): ?><a class="<?php echo $tab === $slug ? 'active' : ''; ?>" href="<?php echo esc_url($this->url($slug)); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?>
			</nav>
			<?php if (isset($_GET['saved'])): ?><div class="cotlas-notice">Changes saved.</div><?php endif; ?>
			<?php
			if ($tab === 'ads') $this->ads_page();
			elseif ($tab === 'zones') $this->zones_page();
			elseif ($tab === 'reports') $this->reports_page();
			elseif ($tab === 'settings') $this->settings_page();
			elseif ($tab === 'tools') $this->tools_page();
			else $this->overview_page();
			?>
		</div>
		<?php
	}

	private function overview_page(): void {
		$ads = $this->repository->ads();
		$zones = $this->repository->zones();
		$totals = $this->repository->totals(0, 30);
		$active = count(array_filter($ads, fn($ad) => $ad['status'] === 'active'));
		$ctr = $totals['impression'] ? round($totals['click'] / $totals['impression'] * 100, 2) : 0;
		?>
		<section class="cotlas-metrics">
			<?php foreach (array(array($active, 'Live campaigns'), array(number_format_i18n($totals['impression']), '30-day impressions'), array(number_format_i18n($totals['click']), '30-day clicks'), array($ctr . '%', 'Click-through rate')) as $metric): ?>
			<div class="cotlas-card metric"><strong><?php echo esc_html($metric[0]); ?></strong><span><?php echo esc_html($metric[1]); ?></span></div>
			<?php endforeach; ?>
		</section>
		<section class="cotlas-grid">
			<div class="cotlas-card"><div class="card-title"><h2>Recent campaigns</h2><a href="<?php echo esc_url($this->url('ads')); ?>">View all</a></div><?php $this->campaign_table(array_slice($ads, 0, 6)); ?></div>
				<aside class="cotlas-card"><h2>Placement overview</h2><p class="cotlas-big"><?php echo number_format_i18n(count($zones)); ?></p><p>Configured placements</p><hr><p>This is an informational count of the reusable ad locations you created. It does not run a separate health check. A placement displays an eligible assigned campaign, or its fallback when none are currently eligible.</p></aside>
		</section>
		<?php
	}

	private function ads_page(): void {
		$id = absint($_GET['id'] ?? 0);
		if (($_GET['action'] ?? '') === 'new' || $id) {
			$this->ad_form($id ? $this->repository->ad($id) : null);
			return;
		}
		echo '<section class="cotlas-card"><div class="card-title"><div><h2>Campaigns</h2><p class="muted">Campaigns and their 30-day delivery totals.</p></div></div>';
		$this->campaign_table($this->repository->ads(), true);
		echo '</section>';
	}

	private function campaign_table(array $ads, bool $actions = false): void {
		if (!$ads) { echo '<div class="cotlas-empty"><p>No campaigns yet.</p></div>'; return; }
		$statistics = $this->repository->totals_by_ad(30);
		echo '<div class="table-scroll"><table class="cotlas-table"><thead><tr><th>Campaign</th><th>Status</th><th>Delivery</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Weight</th>' . ($actions ? '<th></th>' : '') . '</tr></thead><tbody>';
		foreach ($ads as $ad) {
			$window = ($ad['start_at'] ? wp_date('M j', strtotime($ad['start_at'])) : 'Now') . ' → ' . ($ad['end_at'] ? wp_date('M j', strtotime($ad['end_at'])) : 'Open');
			$numbers = $statistics[(int) $ad['id']] ?? array('impression' => 0, 'click' => 0);
			$ctr = $numbers['impression'] ? round($numbers['click'] / $numbers['impression'] * 100, 2) : 0;
			$status = $this->effective_status($ad);
			echo '<tr><td><a class="campaign-name" href="' . esc_url($this->url('ads', array('id' => $ad['id']))) . '">' . esc_html($ad['name']) . '</a><small>' . esc_html(ucfirst($ad['creative_type'])) . '</small></td><td><span class="status ' . esc_attr($status) . '">' . esc_html($status) . '</span></td><td>' . esc_html($window) . '</td><td>' . number_format_i18n($numbers['impression']) . '</td><td>' . number_format_i18n($numbers['click']) . '</td><td>' . esc_html($ctr . '%') . '</td><td>' . absint($ad['weight']) . '</td>';
			if ($actions) echo '<td class="row-actions"><a href="' . esc_url($this->url('ads', array('id' => $ad['id']))) . '">Edit</a><a class="danger" data-confirm="Delete this campaign and its analytics?" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=cotlas_delete_ad&id=' . absint($ad['id'])), 'cotlas_delete_ad_' . absint($ad['id']))) . '">Delete</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private function ad_form(?array $ad): void {
		$ad = wp_parse_args($ad ?: array(), array('id' => 0, 'name' => '', 'status' => 'draft', 'creative_type' => 'html', 'content' => '', 'image_id' => 0, 'video_id' => 0, 'video_source' => 'upload', 'video_url' => '', 'video_embed' => '', 'slider_image_ids' => '', 'target_url' => '', 'canvas_width' => 0, 'canvas_height' => 0, 'slider_interval' => 5, 'weight' => 10, 'start_at' => '', 'end_at' => '', 'days' => '', 'hours' => '', 'device' => 'all', 'countries' => '', 'include_logged_in' => 1, 'max_impressions' => 0, 'max_clicks' => 0));
		$zone_ids = $ad['id'] ? $this->repository->zone_ids_for_ad((int) $ad['id']) : array();
		$image_url = $ad['image_id'] ? wp_get_attachment_image_url((int) $ad['image_id'], 'medium') : '';
		$slider_ids = array_filter(array_map('absint', explode(',', (string) $ad['slider_image_ids'])));
		$video_url = $ad['video_id'] ? wp_get_attachment_url((int) $ad['video_id']) : '';
		$video_settings = wp_parse_args(get_option('cotlas_ads_settings', array()), array('video_upload_enabled' => 0, 'video_max_mb' => 20));
		?>
		<?php if ($ad['id']): $diagnostics = $this->delivery_diagnostics($ad); ?>
			<div class="cotlas-notice <?php echo $diagnostics ? 'warning' : ''; ?>"><b>Current delivery check:</b> <?php echo esc_html($diagnostics ? implode(' ', $diagnostics) : 'This campaign passes its server-side status, date, weekday, hour, and cap checks right now. Device and country rules are evaluated for each visitor.'); ?></div>
		<?php endif; ?>
		<form class="cotlas-editor" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('cotlas_save_ad'); ?><input type="hidden" name="action" value="cotlas_save_ad"><input type="hidden" name="id" value="<?php echo absint($ad['id']); ?>">
			<div class="cotlas-card editor-main"><div class="card-title"><h2><?php echo $ad['id'] ? 'Edit campaign' : 'New campaign'; ?></h2><a href="<?php echo esc_url($this->url('ads')); ?>">Cancel</a></div>
				<label>Campaign name<input required name="name" value="<?php echo esc_attr($ad['name']); ?>" placeholder="Homepage sponsor — September"><small>An internal label used only in the dashboard.</small></label>
				<div class="field-row"><label>Creative type<select name="creative_type" data-creative-type><option value="html" <?php selected($ad['creative_type'], 'html'); ?>>HTML / ad tag</option><option value="image" <?php selected($ad['creative_type'], 'image'); ?>>Single image</option><option value="slider" <?php selected($ad['creative_type'], 'slider'); ?>>Image carousel</option><option value="video" <?php selected($ad['creative_type'], 'video'); ?> <?php disabled(empty($video_settings['video_upload_enabled']) && $ad['creative_type'] !== 'video'); ?>>MP4 video</option></select><small>Choose an image, automatic image carousel, MP4 video, or custom HTML from an ad network.</small></label><label>Destination URL<input type="text" name="target_url" value="<?php echo esc_attr($ad['target_url']); ?>" placeholder="https://advertiser.example/ or tel:+91..."><small>Optional link opened when a reader clicks the creative.</small></label></div>
				<div data-creative-panel="html"><label>Creative HTML<textarea name="content" rows="10" placeholder="Paste an ad-network tag or accessible HTML creative"><?php echo esc_textarea($ad['content']); ?></textarea><small>Used for HTML campaigns and as a fallback if an image is unavailable.</small></label></div>
				<div data-creative-panel="image"><label>Campaign image<input type="hidden" name="image_id" value="<?php echo absint($ad['image_id']); ?>" data-media-id><span class="media-button-row"><button type="button" class="button" data-media-single>Select image</button></span><small>Select an image from the WordPress Media Library. Its preview appears below at full size when space permits.</small><div class="media-preview" data-media-preview><?php if ($image_url): ?><img src="<?php echo esc_url($image_url); ?>" alt=""><?php endif; ?></div></label></div>
				<div data-creative-panel="slider"><label>Carousel images<input type="hidden" name="slider_image_ids" value="<?php echo esc_attr(implode(',', $slider_ids)); ?>" data-slider-ids><span class="media-button-row"><button type="button" class="button" data-media-slider>Select multiple images</button></span><small class="field-warning">Upload images with exactly the same dimensions. For example, use four 728×90 images for a desktop campaign or four 300×250 images for a mobile campaign.</small><div class="media-preview slider-preview" data-slider-preview><?php foreach ($slider_ids as $slider_id): echo wp_get_attachment_image($slider_id, 'thumbnail'); endforeach; ?></div></label><label>Carousel interval (seconds)<input type="number" min="2" max="60" name="slider_interval" value="<?php echo absint($ad['slider_interval']); ?>"><small>How long each image remains visible before sliding to the next image.</small></label></div>
				<div data-creative-panel="video"><label>Video source<select name="video_source" data-video-source><option value="upload" <?php selected($ad['video_source'], 'upload'); ?>>Upload or Media Library MP4</option><option value="url" <?php selected($ad['video_source'], 'url'); ?>>Video, YouTube, or Vimeo URL</option><option value="embed" <?php selected($ad['video_source'], 'embed'); ?>>Embed code</option></select><small>Select how this video advertisement is supplied.</small></label><div data-video-source-panel="upload"><input type="hidden" name="video_id" value="<?php echo absint($ad['video_id']); ?>" data-video-id><label>Upload MP4<input type="file" accept="video/mp4,.mp4" data-video-file <?php disabled(empty($video_settings['video_upload_enabled'])); ?>><span class="media-button-row"><button type="button" class="button" data-upload-video <?php disabled(empty($video_settings['video_upload_enabled'])); ?>>Upload selected MP4</button> <button type="button" class="button" data-media-video>Select existing MP4</button></span><small><?php echo empty($video_settings['video_upload_enabled']) ? 'Video uploads are disabled in Cotlas Ads settings.' : 'Campaign-only upload. Maximum size: ' . absint(min(50, $video_settings['video_max_mb'])) . ' MB.'; ?></small><div class="media-preview" data-video-preview><?php if ($video_url): ?><video style="display:block;max-width:100%;height:auto" src="<?php echo esc_url($video_url); ?>" controls muted playsinline></video><?php endif; ?></div></div><div data-video-source-panel="url"><label>Video URL<input type="url" name="video_url" value="<?php echo esc_attr($ad['video_url']); ?>" placeholder="https://example.com/video.mp4 or YouTube/Vimeo URL"><small>Direct MP4 files play natively. Supported YouTube and Vimeo URLs use WordPress oEmbed.</small></label></div><div data-video-source-panel="embed"><label>Video embed code<textarea name="video_embed" rows="7" placeholder="Paste trusted iframe or player embed code"><?php echo esc_textarea($ad['video_embed']); ?></textarea><small>Trusted administrators only. Scripts and iframes execute on the public site.</small></label></div></div>
				<div class="field-row"><label>Canvas width (px)<input type="number" min="0" max="4096" name="canvas_width" value="<?php echo absint($ad['canvas_width']); ?>" placeholder="728"><small>Maximum display width. Use 0 for the image’s natural responsive width.</small></label><label>Canvas height (px)<input type="number" min="0" max="4096" name="canvas_height" value="<?php echo absint($ad['canvas_height']); ?>" placeholder="90"><small>The image is contained within this canvas without cropping, stretching, or using cover.</small></label></div>
			</div>
			<aside class="cotlas-card editor-side"><h2>Delivery</h2><label>Status<select name="status"><option value="active" <?php selected($ad['status'], 'active'); ?>>Active</option><option value="paused" <?php selected($ad['status'], 'paused'); ?>>Paused</option><option value="draft" <?php selected($ad['status'], 'draft'); ?>>Draft</option></select><small>Active campaigns still obey dates, caps, device, country, weekday, and hour rules. Expired and Scheduled are calculated automatically.</small></label>
				<fieldset><legend>Placements</legend><small>Select every placement where this campaign may appear. Search and select multiple placements.</small><?php $this->multi_select('zone_ids', $this->repository->zones(), $zone_ids, 'Search placements…'); ?></fieldset>
				<label>Weight<input type="number" min="1" max="100" name="weight" value="<?php echo absint($ad['weight']); ?>"><small>Relative chance of selection when a placement uses weighted rotation.</small></label><label>Starts (optional)<input type="datetime-local" name="start_at" value="<?php echo esc_attr($this->local_date($ad['start_at'])); ?>"><small>Leave blank to start immediately.</small></label><label>Ends (optional)<input type="datetime-local" name="end_at" value="<?php echo esc_attr($this->local_date($ad['end_at'])); ?>"><small>Leave blank to run indefinitely until paused or deleted.</small></label>
				<label>Device<select name="device"><?php foreach (array('all' => 'All devices', 'desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet') as $value => $label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($ad['device'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><small>Mobile sliders commonly use 300×250 images; desktop sliders commonly use 728×90.</small></label>
				<label>Country allowlist<input name="countries" value="<?php echo esc_attr($ad['countries']); ?>" placeholder="IN, US, GB"><small>ISO codes; blank allows everywhere. Cloudflare country header supported.</small></label>
				<label>Weekdays<input name="days" value="<?php echo esc_attr($ad['days']); ?>" placeholder="1,2,3,4,5"><small>0 Sunday through 6 Saturday; blank allows every day.</small></label><label>Hours<input name="hours" value="<?php echo esc_attr($ad['hours']); ?>" placeholder="8,9,10,17,18"><small>Site-local hours; blank allows all.</small></label>
				<label>Impression cap<input type="number" min="0" name="max_impressions" value="<?php echo absint($ad['max_impressions']); ?>"><small>At this lifetime total the campaign becomes Expired. Use 0 for no cap.</small></label><label>Click cap<input type="number" min="0" name="max_clicks" value="<?php echo absint($ad['max_clicks']); ?>"><small>At this lifetime total the campaign becomes Expired. Use 0 for no cap.</small></label>
				<label class="check"><input type="checkbox" name="include_logged_in" value="1" <?php checked($ad['include_logged_in']); ?>> Show to logged-in readers</label><small>Turn off to hide the campaign from editors and other logged-in visitors.</small><button class="cotlas-primary" type="submit">Save campaign</button>
			</aside>
		</form>
		<?php
	}

	private function zones_page(): void {
		$id = absint($_GET['id'] ?? 0); $zone = $id ? $this->repository->zone($id) : null;
		$zone = wp_parse_args($zone ?: array(), array('id' => 0, 'name' => '', 'slug' => '', 'mode' => 'weighted', 'ad_ids' => '', 'css_class' => '', 'fallback' => ''));
		$selected = array_map('absint', explode(',', $zone['ad_ids']));
		?>
		<div class="cotlas-grid"><section class="cotlas-card"><h2>Placements</h2><?php foreach ($this->repository->zones() as $item): ?><div class="zone-row"><div><b><?php echo esc_html($item['name']); ?></b><code>[cotlas_ad zone="<?php echo esc_attr($item['slug']); ?>"]</code></div><div><a href="<?php echo esc_url($this->url('zones', array('id' => $item['id']))); ?>">Edit</a> <a class="danger" data-confirm="Delete this placement?" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cotlas_delete_zone&id=' . absint($item['id'])), 'cotlas_delete_zone_' . absint($item['id']))); ?>">Delete</a></div></div><?php endforeach; ?></section>
		<form class="cotlas-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('cotlas_save_zone'); ?><input type="hidden" name="action" value="cotlas_save_zone"><input type="hidden" name="id" value="<?php echo absint($zone['id']); ?>"><h2><?php echo $zone['id'] ? 'Edit placement' : 'New placement'; ?></h2>
		<label>Name<input required name="name" value="<?php echo esc_attr($zone['name']); ?>"><small>A dashboard label for this reusable ad location.</small></label><?php if ($zone['id']): ?><label>Slug<input readonly name="slug" value="<?php echo esc_attr($zone['slug']); ?>"><small>Generated automatically when the placement was created. It is locked so existing shortcodes do not break.</small></label><?php else: ?><input type="hidden" name="slug" value=""><p class="muted"><b>Slug:</b> Generated automatically from the placement name when saved.</p><?php endif; ?><label>Delivery<select name="mode"><option value="weighted" <?php selected($zone['mode'], 'weighted'); ?>>Weighted rotation</option><option value="random" <?php selected($zone['mode'], 'random'); ?>>Equal random</option><option value="all" <?php selected($zone['mode'], 'all'); ?>>Show all</option></select><small>Weighted uses campaign weights; random gives equal chances; show all prints every eligible campaign.</small></label>
		<fieldset><legend>Campaigns</legend><small>Only eligible selected campaigns can display here. Search and select multiple campaigns.</small><?php $this->multi_select('ad_ids', $this->repository->ads(), $selected, 'Search campaigns…'); ?></fieldset><label>CSS class<input name="css_class" value="<?php echo esc_attr($zone['css_class']); ?>"><small>Optional class added to the placement wrapper for theme-specific styling.</small></label><label>Fallback HTML<textarea name="fallback" rows="8" placeholder="Paste HTML, inline CSS, or trusted JavaScript"><?php echo esc_textarea($zone['fallback']); ?></textarea><small>Displayed only when every assigned campaign is ineligible. Complete HTML, <code>&lt;style&gt;</code>, and <code>&lt;script&gt;</code> markup is accepted; scripts execute on the public site, so paste code only from sources you trust.</small></label><button class="cotlas-primary">Save placement</button></form></div>
		<?php
	}

	private function reports_page(): void {
		$totals = $this->repository->totals(0, 30); $values = array_map(fn($row) => (int) $row['count'], $totals['series']); $max = $values ? max(1, max($values)) : 1;
		?><section class="cotlas-card"><div class="card-title"><h2>30-day analytics</h2><span>Aggregated hourly · privacy-friendly</span></div><div class="report-summary"><b><?php echo number_format_i18n($totals['impression']); ?></b> impressions <b><?php echo number_format_i18n($totals['click']); ?></b> clicks</div><div class="chart" aria-label="Daily ad events"><?php foreach ($totals['series'] as $row): ?><div class="bar <?php echo esc_attr($row['event_type']); ?>" style="--h:<?php echo esc_attr(round((int) $row['count'] / $max * 100)); ?>%" title="<?php echo esc_attr($row['event_date'] . ': ' . $row['count'] . ' ' . $row['event_type']); ?>"></div><?php endforeach; ?></div></section><?php
	}

	private function settings_page(): void {
		$s = wp_parse_args(get_option('cotlas_ads_settings', array()), array('track_impressions' => 1, 'track_clicks' => 1, 'retention_days' => 365, 'bot_filter' => 1, 'header_code' => '', 'ads_txt' => '', 'injections' => array(), 'ad_label' => 'Advertisement', 'adblock_enabled' => 0, 'adblock_dismissible' => 1, 'adblock_title' => 'Please disable your ad blocker', 'adblock_message' => 'Advertising supports our newsroom. Please disable your ad blocker and reload this page to continue.', 'video_upload_enabled' => 0, 'video_max_mb' => 20));
		$rule = wp_parse_args($s['injections'][0] ?? array(), array('zone' => '', 'position' => 'after', 'paragraph' => 2, 'post_types' => array('post')));
		?><form class="cotlas-grid" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('cotlas_save_settings'); ?><input type="hidden" name="action" value="cotlas_save_settings"><section class="cotlas-card"><h2>Tracking & privacy</h2><label class="check"><input type="checkbox" name="track_impressions" value="1" <?php checked($s['track_impressions']); ?>> Track impressions</label><small>Counts a view when the campaign tracking pixel loads.</small><label class="check"><input type="checkbox" name="track_clicks" value="1" <?php checked($s['track_clicks']); ?>> Track clicks</label><small>Routes linked campaign clicks through a secure first-party counter.</small><label class="check"><input type="checkbox" name="bot_filter" value="1" <?php checked($s['bot_filter']); ?>> Ignore known crawlers</label><small>Prevents common bots and link preview tools from inflating totals.</small><label>Retention days<input type="number" min="1" max="3650" name="retention_days" value="<?php echo absint($s['retention_days']); ?>"><small>Hourly analytics older than this are deleted automatically.</small></label><h2>Post injection</h2><p class="muted">Automatically inserts the selected placement into singular content without editing each post or theme template.</p><label>Placement<select name="injection_zone"><option value="">Disabled</option><?php foreach ($this->repository->zones() as $zone): ?><option value="<?php echo esc_attr($zone['slug']); ?>" <?php selected($rule['zone'], $zone['slug']); ?>><?php echo esc_html($zone['name']); ?></option><?php endforeach; ?></select><small>Select Disabled to turn automatic insertion off.</small></label><label>Position<select name="injection_position"><option value="before" <?php selected($rule['position'], 'before'); ?>>Before content</option><option value="after" <?php selected($rule['position'], 'after'); ?>>After content</option><option value="paragraph" <?php selected($rule['position'], 'paragraph'); ?>>After paragraph</option></select><small>Controls where the placement is inserted in the content.</small></label><label>Paragraph<input type="number" min="1" name="injection_paragraph" value="<?php echo absint($rule['paragraph']); ?>"><small>Used only with “After paragraph”; 3 inserts after the third closing paragraph.</small></label><label>Post types<input name="injection_post_types" value="<?php echo esc_attr(implode(',', $rule['post_types'])); ?>" placeholder="post,page,product"><small>Accepts comma-separated post-type slugs, for example: <code>post,page,product</code>.</small></label></section>
		<section class="cotlas-card"><h2>Advertisement display</h2><label>Advertisement label<input name="ad_label" value="<?php echo esc_attr($s['ad_label']); ?>" placeholder="Advertisement"><small>Displayed directly below every served campaign. Leave blank to hide the label.</small></label><h2>Video creatives</h2><label class="check"><input type="checkbox" name="video_upload_enabled" value="1" <?php checked($s['video_upload_enabled']); ?>> Enable MP4 video creatives</label><small>Permits MP4 uploads only through the Cotlas Ads campaign editor—not in posts or the normal Media Library.</small><label>Maximum video upload size (MB)<input type="number" min="1" max="20" name="video_max_mb" value="<?php echo absint(min(20, max(1, $s['video_max_mb']))); ?>"><small>Hard maximum: 20 MB. Your server may enforce a smaller limit.</small></label><h2>Ad blocker notice</h2><label class="check"><input type="checkbox" name="adblock_enabled" value="1" <?php checked($s['adblock_enabled']); ?>> Detect browser ad blockers</label><small>Shows a full-screen request when a common ad-blocking rule hides the local detection element.</small><label class="check"><input type="checkbox" name="adblock_dismissible" value="1" <?php checked($s['adblock_dismissible']); ?>> Allow visitors to dismiss the notice</label><small>When disabled, visitors must disable their blocker and reload before reading the site. Test carefully to avoid accidental lockouts.</small><label>Popup title<input name="adblock_title" value="<?php echo esc_attr($s['adblock_title']); ?>"><small>Main heading shown in the ad-blocker request.</small></label><label>Popup message<textarea name="adblock_message" rows="4"><?php echo esc_textarea($s['adblock_message']); ?></textarea><small>Explain why advertising supports the publication and how readers can continue.</small></label><h2>Publisher integrations</h2><label>ads.txt<textarea name="ads_txt" rows="8" placeholder="google.com, pub-..., DIRECT, ..."><?php echo esc_textarea($s['ads_txt']); ?></textarea></label><label>Header snippets<textarea name="header_code" rows="8" placeholder="Verification meta tags or network scripts"><?php echo esc_textarea($s['header_code']); ?></textarea><small>Only trusted administrators should edit this field.</small></label><button class="cotlas-primary">Save settings</button></section></form><?php
	}

	private function tools_page(): void { ?>
		<div class="cotlas-grid"><section class="cotlas-card"><h2>Export</h2><p>Download campaigns, placements, and settings as a portable JSON package.</p><a class="cotlas-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cotlas_export'), 'cotlas_export')); ?>">Download export</a></section><form class="cotlas-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('cotlas_import'); ?><input type="hidden" name="action" value="cotlas_import"><h2>Import</h2><p>Add records from a Cotlas Ads JSON export. Existing records are not overwritten.</p><input required type="file" name="package" accept="application/json"><button class="cotlas-primary">Import package</button></form></div>
	<?php }

	public function save_ad(): void {
		$this->guard('cotlas_ads_manage', 'cotlas_save_ad');
		$data = wp_unslash($_POST);
		if (($data['creative_type'] ?? '') === 'video' && ($data['video_source'] ?? 'upload') === 'upload') $this->validate_video_attachment(absint($data['video_id'] ?? 0));
		if (($data['creative_type'] ?? '') === 'video' && ($data['video_source'] ?? '') === 'url' && empty($data['video_url'])) wp_die('Enter a video, YouTube, or Vimeo URL.');
		if (($data['creative_type'] ?? '') === 'video' && ($data['video_source'] ?? '') === 'embed' && empty($data['video_embed'])) wp_die('Paste the video embed code.');
		$id = $this->repository->save_ad($data);
		$this->repository->assign_ad_to_zones($id, (array) ($_POST['zone_ids'] ?? array()));
		$this->redirect('ads');
	}
	public function delete_ad(): void { $id = absint($_GET['id'] ?? 0); $this->guard('cotlas_ads_manage', 'cotlas_delete_ad_' . $id, '_wpnonce', 'get'); $this->repository->delete_ad($id); $this->redirect('ads'); }
	public function save_zone(): void { $this->guard('cotlas_ads_manage', 'cotlas_save_zone'); $this->repository->save_zone(wp_unslash($_POST)); $this->redirect('zones'); }
	public function delete_zone(): void { $id = absint($_GET['id'] ?? 0); $this->guard('cotlas_ads_manage', 'cotlas_delete_zone_' . $id, '_wpnonce', 'get'); $this->repository->delete_zone($id); $this->redirect('zones'); }
	public function save_settings(): void {
		$this->guard('cotlas_ads_settings', 'cotlas_save_settings');
		$post_types = array_filter(array_map('sanitize_key', explode(',', wp_unslash($_POST['injection_post_types'] ?? 'post'))));
		$settings = array('track_impressions' => empty($_POST['track_impressions']) ? 0 : 1, 'track_clicks' => empty($_POST['track_clicks']) ? 0 : 1, 'bot_filter' => empty($_POST['bot_filter']) ? 0 : 1, 'retention_days' => min(3650, max(1, absint($_POST['retention_days'] ?? 365))), 'ad_label' => sanitize_text_field(wp_unslash($_POST['ad_label'] ?? 'Advertisement')), 'video_upload_enabled' => empty($_POST['video_upload_enabled']) ? 0 : 1, 'video_max_mb' => min(20, max(1, absint($_POST['video_max_mb'] ?? 20))), 'adblock_enabled' => empty($_POST['adblock_enabled']) ? 0 : 1, 'adblock_dismissible' => empty($_POST['adblock_dismissible']) ? 0 : 1, 'adblock_title' => sanitize_text_field(wp_unslash($_POST['adblock_title'] ?? 'Please disable your ad blocker')), 'adblock_message' => sanitize_textarea_field(wp_unslash($_POST['adblock_message'] ?? '')), 'ads_txt' => sanitize_textarea_field(wp_unslash($_POST['ads_txt'] ?? '')), 'header_code' => current_user_can('unfiltered_html') ? wp_unslash($_POST['header_code'] ?? '') : wp_kses_post(wp_unslash($_POST['header_code'] ?? '')), 'injections' => array());
		$settings['video_max_mb'] = min(50, max(1, absint($_POST['video_max_mb'] ?? 20)));
		if (!empty($_POST['injection_zone'])) $settings['injections'][] = array('zone' => sanitize_title(wp_unslash($_POST['injection_zone'])), 'position' => in_array($_POST['injection_position'] ?? '', array('before', 'after', 'paragraph'), true) ? $_POST['injection_position'] : 'after', 'paragraph' => absint($_POST['injection_paragraph'] ?? 2), 'post_types' => $post_types ?: array('post'));
		update_option('cotlas_ads_settings', $settings, false); $this->redirect('settings');
	}

	public function allow_scoped_video_mime(array $mimes): array {
		if ($this->is_scoped_video_upload()) $mimes['mp4'] = 'video/mp4';
		return $mimes;
	}

	public function validate_scoped_video_upload(array $file): array {
		if (!$this->is_scoped_video_upload()) return $file;
		$settings = wp_parse_args(get_option('cotlas_ads_settings', array()), array('video_upload_enabled' => 0, 'video_max_mb' => 20));
		if (empty($settings['video_upload_enabled'])) $file['error'] = 'Video uploads are disabled in Cotlas Ads settings.';
		$limit = min(50, max(1, absint($settings['video_max_mb']))) * MB_IN_BYTES;
		if (empty($file['error']) && (int) ($file['size'] ?? 0) > $limit) $file['error'] = sprintf('Video exceeds the Cotlas Ads limit of %d MB.', (int) ($limit / MB_IN_BYTES));
		if (empty($file['error']) && strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'mp4') $file['error'] = 'Cotlas Ads accepts MP4 video files only.';
		return $file;
	}

	private function is_scoped_video_upload(): bool {
		$nonce = sanitize_text_field(wp_unslash($_REQUEST['cotlas_ads_video_upload'] ?? ''));
		return current_user_can('cotlas_ads_manage') && $nonce !== '' && wp_verify_nonce($nonce, 'cotlas_ads_video_upload');
	}

	private function validate_video_attachment(int $attachment_id): void {
		if (!$attachment_id || get_post_mime_type($attachment_id) !== 'video/mp4') wp_die('Select a valid MP4 video for this campaign.');
		$path = get_attached_file($attachment_id);
		$settings = wp_parse_args(get_option('cotlas_ads_settings', array()), array('video_max_mb' => 20));
		$limit = min(50, max(1, absint($settings['video_max_mb']))) * MB_IN_BYTES;
		if (!$path || !is_file($path) || filesize($path) > $limit) wp_die(sprintf('The campaign video must be no larger than %d MB.', (int) ($limit / MB_IN_BYTES)));
	}

	public function ajax_upload_video(): void {
		check_ajax_referer('cotlas_ads_video_upload', 'nonce');
		if (!current_user_can('cotlas_ads_manage')) wp_send_json_error(array('message' => 'Permission denied.'), 403);
		$settings = wp_parse_args(get_option('cotlas_ads_settings', array()), array('video_upload_enabled' => 0, 'video_max_mb' => 20));
		if (empty($settings['video_upload_enabled'])) wp_send_json_error(array('message' => 'Video uploads are disabled in Cotlas Ads settings.'), 403);
		if (empty($_FILES['video']) || !is_uploaded_file($_FILES['video']['tmp_name'])) wp_send_json_error(array('message' => 'Select an MP4 file.'), 400);
		$file = $_FILES['video'];
		$limit = min(50, max(1, absint($settings['video_max_mb']))) * MB_IN_BYTES;
		if ((int) $file['size'] > $limit) wp_send_json_error(array('message' => sprintf('Video exceeds the %d MB campaign limit.', (int) ($limit / MB_IN_BYTES))), 400);
		if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'mp4') wp_send_json_error(array('message' => 'Cotlas Ads accepts MP4 files only.'), 400);
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload = wp_handle_sideload($file, array('test_form' => false, 'mimes' => array('mp4' => 'video/mp4')));
		if (!empty($upload['error'])) wp_send_json_error(array('message' => $upload['error']), 400);
		$attachment_id = wp_insert_attachment(array('post_mime_type' => 'video/mp4', 'post_title' => sanitize_text_field(pathinfo($file['name'], PATHINFO_FILENAME)), 'post_status' => 'inherit'), $upload['file']);
		if (is_wp_error($attachment_id)) { wp_delete_file($upload['file']); wp_send_json_error(array('message' => $attachment_id->get_error_message()), 500); }
		update_attached_file($attachment_id, $upload['file']);
		wp_send_json_success(array('id' => $attachment_id, 'url' => $upload['url']));
	}

	public function export(): void {
		$this->guard('cotlas_ads_manage', 'cotlas_export', '_wpnonce', 'get');
		$package = array('format' => 'cotlas-ads', 'version' => COTLAS_ADS_VERSION, 'exported_at' => gmdate(DATE_ATOM), 'ads' => $this->repository->ads(), 'zones' => $this->repository->zones(), 'settings' => get_option('cotlas_ads_settings', array()));
		nocache_headers(); header('Content-Type: application/json'); header('Content-Disposition: attachment; filename="cotlas-ads-' . gmdate('Y-m-d') . '.json"'); echo wp_json_encode($package, JSON_PRETTY_PRINT); exit;
	}

	public function import(): void {
		$this->guard('cotlas_ads_manage', 'cotlas_import');
		if (!isset($_FILES['package']['tmp_name']) || !is_uploaded_file($_FILES['package']['tmp_name'])) wp_die('Missing import file.');
		$data = json_decode((string) file_get_contents($_FILES['package']['tmp_name']), true);
		if (!is_array($data) || ($data['format'] ?? '') !== 'cotlas-ads') wp_die('Invalid Cotlas Ads package.');
		$map = array(); foreach ((array) ($data['ads'] ?? array()) as $ad) { $old = absint($ad['id'] ?? 0); unset($ad['id']); $map[$old] = $this->repository->save_ad($ad); }
		foreach ((array) ($data['zones'] ?? array()) as $zone) { unset($zone['id']); $zone['ad_ids'] = array_map(fn($id) => $map[absint($id)] ?? 0, explode(',', (string) ($zone['ad_ids'] ?? ''))); $this->repository->save_zone($zone); }
		$this->redirect('tools');
	}

	private function guard(string $cap, string $action, string $field = '_wpnonce', string $source = 'post'): void {
		if (!current_user_can($cap)) wp_die('Permission denied.');
		$input = $source === 'get' ? $_GET : $_POST;
		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($input[$field] ?? '')), $action)) wp_die('Invalid request.');
	}
	private function redirect(string $tab): void { wp_safe_redirect($this->url($tab, array('saved' => 1))); exit; }
	private function url(string $tab, array $args = array()): string { return add_query_arg(array_merge(array('page' => 'cotlas-ads', 'tab' => $tab), $args), admin_url('admin.php')); }
	private function local_date(?string $value): string { return $value ? wp_date('Y-m-d\TH:i', strtotime($value), wp_timezone()) : ''; }
	private function multi_select(string $name, array $items, array $selected, string $placeholder): void {
		$selected = array_map('absint', $selected);
		$selected_names = array();
		foreach ($items as $item) if (in_array((int) $item['id'], $selected, true)) $selected_names[] = $item['name'];
		?>
		<div class="cotlas-multiselect" data-multiselect>
			<button type="button" class="cotlas-multiselect-toggle" data-multiselect-toggle aria-expanded="false"><span data-multiselect-summary><?php echo esc_html($selected_names ? implode(', ', $selected_names) : 'Select…'); ?></span><span aria-hidden="true">▾</span></button>
			<div class="cotlas-multiselect-panel" data-multiselect-panel hidden><input type="search" class="cotlas-multiselect-search" data-multiselect-search placeholder="<?php echo esc_attr($placeholder); ?>"><div class="cotlas-multiselect-options"><?php foreach ($items as $item): ?><label data-multiselect-option data-label="<?php echo esc_attr(strtolower($item['name'])); ?>"><input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo absint($item['id']); ?>" <?php checked(in_array((int) $item['id'], $selected, true)); ?>><span><?php echo esc_html($item['name']); ?></span></label><?php endforeach; ?></div></div>
		</div>
		<?php
	}
	private function effective_status(array $ad): string {
		if ($ad['status'] !== 'active') return $ad['status'];
		$now = time();
		if ($ad['start_at'] && $now < strtotime($ad['start_at'])) return 'scheduled';
		if ($ad['end_at'] && $now > strtotime($ad['end_at'])) return 'expired';
		$totals = $this->repository->totals((int) $ad['id'], 3650);
		if (($ad['max_impressions'] && $totals['impression'] >= (int) $ad['max_impressions']) || ($ad['max_clicks'] && $totals['click'] >= (int) $ad['max_clicks'])) return 'expired';
		return 'active';
	}
	private function delivery_diagnostics(array $ad): array {
		$messages = array();
		$status = $this->effective_status($ad);
		if ($status === 'scheduled') $messages[] = 'It will not serve until ' . wp_date('M j, Y g:i a', strtotime($ad['start_at']), wp_timezone()) . '.';
		elseif ($status === 'expired') $messages[] = 'It is expired because its end date or a delivery cap has passed.';
		elseif ($status !== 'active') $messages[] = 'Its saved status is ' . $status . '.';
		if ($ad['days'] !== '' && !in_array((string) current_time('w'), array_map('trim', explode(',', $ad['days'])), true)) $messages[] = 'Today is excluded by the weekday rule.';
		if ($ad['hours'] !== '' && !in_array((string) current_time('G'), array_map('trim', explode(',', $ad['hours'])), true)) $messages[] = 'The current site-local hour (' . current_time('G') . ':00) is excluded by the hour rule.';
		if (!$ad['include_logged_in'] && is_user_logged_in()) $messages[] = 'It is hidden while you are logged in.';
		return $messages;
	}
}
