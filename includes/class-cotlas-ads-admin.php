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
	}

	public function menu(): void {
		add_menu_page(__('Cotlas Ads', 'cotlas-ads'), __('Cotlas Ads', 'cotlas-ads'), 'cotlas_ads_manage', 'cotlas-ads', array($this, 'page'), 'dashicons-megaphone', 25);
	}

	public function assets(string $hook): void {
		if ($hook !== 'toplevel_page_cotlas-ads') return;
		wp_enqueue_style('cotlas-ads-admin', COTLAS_ADS_URL . 'assets/admin.css', array(), COTLAS_ADS_VERSION);
		wp_enqueue_script('cotlas-ads-admin', COTLAS_ADS_URL . 'assets/admin.js', array(), COTLAS_ADS_VERSION, true);
		wp_enqueue_media();
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
			<aside class="cotlas-card"><h2>Placement health</h2><p class="cotlas-big"><?php echo number_format_i18n(count($zones)); ?></p><p>Configured zones</p><hr><p><b>Tip:</b> Use a stable zone shortcode in templates, then swap campaigns without editing the story layout.</p></aside>
		</section>
		<?php
	}

	private function ads_page(): void {
		$id = absint($_GET['id'] ?? 0);
		if (($_GET['action'] ?? '') === 'new' || $id) {
			$this->ad_form($id ? $this->repository->ad($id) : null);
			return;
		}
		echo '<section class="cotlas-card"><div class="card-title"><h2>Campaigns</h2><span class="muted">Unlimited campaigns · no external account</span></div>';
		$this->campaign_table($this->repository->ads(), true);
		echo '</section>';
	}

	private function campaign_table(array $ads, bool $actions = false): void {
		if (!$ads) { echo '<div class="cotlas-empty"><p>No campaigns yet.</p></div>'; return; }
		echo '<div class="table-scroll"><table class="cotlas-table"><thead><tr><th>Campaign</th><th>Status</th><th>Delivery</th><th>Weight</th>' . ($actions ? '<th></th>' : '') . '</tr></thead><tbody>';
		foreach ($ads as $ad) {
			$window = ($ad['start_at'] ? wp_date('M j', strtotime($ad['start_at'])) : 'Now') . ' → ' . ($ad['end_at'] ? wp_date('M j', strtotime($ad['end_at'])) : 'Open');
			echo '<tr><td><a class="campaign-name" href="' . esc_url($this->url('ads', array('id' => $ad['id']))) . '">' . esc_html($ad['name']) . '</a><small>' . esc_html(ucfirst($ad['creative_type'])) . '</small></td><td><span class="status ' . esc_attr($ad['status']) . '">' . esc_html($ad['status']) . '</span></td><td>' . esc_html($window) . '</td><td>' . absint($ad['weight']) . '</td>';
			if ($actions) echo '<td class="row-actions"><a href="' . esc_url($this->url('ads', array('id' => $ad['id']))) . '">Edit</a><a class="danger" data-confirm="Delete this campaign and its analytics?" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=cotlas_delete_ad&id=' . absint($ad['id'])), 'cotlas_delete_ad_' . absint($ad['id']))) . '">Delete</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private function ad_form(?array $ad): void {
		$ad = wp_parse_args($ad ?: array(), array('id' => 0, 'name' => '', 'status' => 'draft', 'creative_type' => 'html', 'content' => '', 'image_id' => 0, 'target_url' => '', 'weight' => 10, 'start_at' => '', 'end_at' => '', 'days' => '', 'hours' => '', 'device' => 'all', 'countries' => '', 'include_logged_in' => 1, 'max_impressions' => 0, 'max_clicks' => 0));
		?>
		<form class="cotlas-editor" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('cotlas_save_ad'); ?><input type="hidden" name="action" value="cotlas_save_ad"><input type="hidden" name="id" value="<?php echo absint($ad['id']); ?>">
			<div class="cotlas-card editor-main"><div class="card-title"><h2><?php echo $ad['id'] ? 'Edit campaign' : 'New campaign'; ?></h2><a href="<?php echo esc_url($this->url('ads')); ?>">Cancel</a></div>
				<label>Campaign name<input required name="name" value="<?php echo esc_attr($ad['name']); ?>" placeholder="Homepage sponsor — September"></label>
				<div class="field-row"><label>Creative type<select name="creative_type"><option value="html" <?php selected($ad['creative_type'], 'html'); ?>>HTML / ad tag</option><option value="image" <?php selected($ad['creative_type'], 'image'); ?>>Image</option></select></label><label>Destination URL<input type="url" name="target_url" value="<?php echo esc_attr($ad['target_url']); ?>" placeholder="https://advertiser.example/"></label></div>
				<label>Creative HTML<textarea name="content" rows="10" placeholder="Paste an ad-network tag or accessible HTML creative"><?php echo esc_textarea($ad['content']); ?></textarea></label>
				<label>Media attachment ID<input name="image_id" value="<?php echo absint($ad['image_id']); ?>" inputmode="numeric"><small>Choose an image in Media Library and use its attachment ID. HTML is used as fallback.</small></label>
			</div>
			<aside class="cotlas-card editor-side"><h2>Delivery</h2><label>Status<select name="status"><option value="active" <?php selected($ad['status'], 'active'); ?>>Active</option><option value="paused" <?php selected($ad['status'], 'paused'); ?>>Paused</option><option value="draft" <?php selected($ad['status'], 'draft'); ?>>Draft</option></select></label>
				<label>Weight<input type="number" min="1" max="100" name="weight" value="<?php echo absint($ad['weight']); ?>"></label><label>Starts<input type="datetime-local" name="start_at" value="<?php echo esc_attr($this->local_date($ad['start_at'])); ?>"></label><label>Ends<input type="datetime-local" name="end_at" value="<?php echo esc_attr($this->local_date($ad['end_at'])); ?>"></label>
				<label>Device<select name="device"><?php foreach (array('all' => 'All devices', 'desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet') as $value => $label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($ad['device'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
				<label>Country allowlist<input name="countries" value="<?php echo esc_attr($ad['countries']); ?>" placeholder="IN, US, GB"><small>ISO codes; blank allows everywhere. Cloudflare country header supported.</small></label>
				<label>Weekdays<input name="days" value="<?php echo esc_attr($ad['days']); ?>" placeholder="1,2,3,4,5"><small>0 Sunday through 6 Saturday; blank allows every day.</small></label><label>Hours<input name="hours" value="<?php echo esc_attr($ad['hours']); ?>" placeholder="8,9,10,17,18"><small>Site-local hours; blank allows all.</small></label>
				<label>Impression cap<input type="number" min="0" name="max_impressions" value="<?php echo absint($ad['max_impressions']); ?>"></label><label>Click cap<input type="number" min="0" name="max_clicks" value="<?php echo absint($ad['max_clicks']); ?>"></label>
				<label class="check"><input type="checkbox" name="include_logged_in" value="1" <?php checked($ad['include_logged_in']); ?>> Show to logged-in readers</label><button class="cotlas-primary" type="submit">Save campaign</button>
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
		<label>Name<input required name="name" value="<?php echo esc_attr($zone['name']); ?>"></label><label>Slug<input name="slug" value="<?php echo esc_attr($zone['slug']); ?>" placeholder="article-inline"></label><label>Delivery<select name="mode"><option value="weighted" <?php selected($zone['mode'], 'weighted'); ?>>Weighted rotation</option><option value="random" <?php selected($zone['mode'], 'random'); ?>>Equal random</option><option value="all" <?php selected($zone['mode'], 'all'); ?>>Show all</option></select></label>
		<fieldset><legend>Campaigns</legend><?php foreach ($this->repository->ads() as $ad): ?><label class="check"><input type="checkbox" name="ad_ids[]" value="<?php echo absint($ad['id']); ?>" <?php checked(in_array((int) $ad['id'], $selected, true)); ?>><?php echo esc_html($ad['name']); ?></label><?php endforeach; ?></fieldset><label>CSS class<input name="css_class" value="<?php echo esc_attr($zone['css_class']); ?>"></label><label>Fallback HTML<textarea name="fallback" rows="4"><?php echo esc_textarea($zone['fallback']); ?></textarea></label><button class="cotlas-primary">Save placement</button></form></div>
		<?php
	}

	private function reports_page(): void {
		$totals = $this->repository->totals(0, 30); $max = max(1, ...array_map(fn($row) => (int) $row['count'], $totals['series']));
		?><section class="cotlas-card"><div class="card-title"><h2>30-day analytics</h2><span>Aggregated hourly · privacy-friendly</span></div><div class="report-summary"><b><?php echo number_format_i18n($totals['impression']); ?></b> impressions <b><?php echo number_format_i18n($totals['click']); ?></b> clicks</div><div class="chart" aria-label="Daily ad events"><?php foreach ($totals['series'] as $row): ?><div class="bar <?php echo esc_attr($row['event_type']); ?>" style="--h:<?php echo esc_attr(round((int) $row['count'] / $max * 100)); ?>%" title="<?php echo esc_attr($row['event_date'] . ': ' . $row['count'] . ' ' . $row['event_type']); ?>"></div><?php endforeach; ?></div></section><?php
	}

	private function settings_page(): void {
		$s = wp_parse_args(get_option('cotlas_ads_settings', array()), array('track_impressions' => 1, 'track_clicks' => 1, 'retention_days' => 365, 'bot_filter' => 1, 'header_code' => '', 'ads_txt' => '', 'injections' => array()));
		$rule = wp_parse_args($s['injections'][0] ?? array(), array('zone' => '', 'position' => 'after', 'paragraph' => 2, 'post_types' => array('post')));
		?><form class="cotlas-grid" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('cotlas_save_settings'); ?><input type="hidden" name="action" value="cotlas_save_settings"><section class="cotlas-card"><h2>Tracking & privacy</h2><label class="check"><input type="checkbox" name="track_impressions" value="1" <?php checked($s['track_impressions']); ?>> Track impressions</label><label class="check"><input type="checkbox" name="track_clicks" value="1" <?php checked($s['track_clicks']); ?>> Track clicks</label><label class="check"><input type="checkbox" name="bot_filter" value="1" <?php checked($s['bot_filter']); ?>> Ignore known crawlers</label><label>Retention days<input type="number" min="1" max="3650" name="retention_days" value="<?php echo absint($s['retention_days']); ?>"></label><h2>Post injection</h2><label>Placement<select name="injection_zone"><option value="">Disabled</option><?php foreach ($this->repository->zones() as $zone): ?><option value="<?php echo esc_attr($zone['slug']); ?>" <?php selected($rule['zone'], $zone['slug']); ?>><?php echo esc_html($zone['name']); ?></option><?php endforeach; ?></select></label><label>Position<select name="injection_position"><option value="before" <?php selected($rule['position'], 'before'); ?>>Before content</option><option value="after" <?php selected($rule['position'], 'after'); ?>>After content</option><option value="paragraph" <?php selected($rule['position'], 'paragraph'); ?>>After paragraph</option></select></label><label>Paragraph<input type="number" min="1" name="injection_paragraph" value="<?php echo absint($rule['paragraph']); ?>"></label><label>Post types<input name="injection_post_types" value="<?php echo esc_attr(implode(',', $rule['post_types'])); ?>" placeholder="post,page,product"></label></section>
		<section class="cotlas-card"><h2>Publisher integrations</h2><label>ads.txt<textarea name="ads_txt" rows="10" placeholder="google.com, pub-..., DIRECT, ..."><?php echo esc_textarea($s['ads_txt']); ?></textarea></label><label>Header snippets<textarea name="header_code" rows="10" placeholder="Verification meta tags or network scripts"><?php echo esc_textarea($s['header_code']); ?></textarea><small>Only trusted administrators should edit this field.</small></label><button class="cotlas-primary">Save settings</button></section></form><?php
	}

	private function tools_page(): void { ?>
		<div class="cotlas-grid"><section class="cotlas-card"><h2>Export</h2><p>Download campaigns, placements, and settings as a portable JSON package.</p><a class="cotlas-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cotlas_export'), 'cotlas_export')); ?>">Download export</a></section><form class="cotlas-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('cotlas_import'); ?><input type="hidden" name="action" value="cotlas_import"><h2>Import</h2><p>Add records from a Cotlas Ads JSON export. Existing records are not overwritten.</p><input required type="file" name="package" accept="application/json"><button class="cotlas-primary">Import package</button></form></div>
	<?php }

	public function save_ad(): void { $this->guard('cotlas_ads_manage', 'cotlas_save_ad'); $this->repository->save_ad(wp_unslash($_POST)); $this->redirect('ads'); }
	public function delete_ad(): void { $id = absint($_GET['id'] ?? 0); $this->guard('cotlas_ads_manage', 'cotlas_delete_ad_' . $id, '_wpnonce', 'get'); $this->repository->delete_ad($id); $this->redirect('ads'); }
	public function save_zone(): void { $this->guard('cotlas_ads_manage', 'cotlas_save_zone'); $this->repository->save_zone(wp_unslash($_POST)); $this->redirect('zones'); }
	public function delete_zone(): void { $id = absint($_GET['id'] ?? 0); $this->guard('cotlas_ads_manage', 'cotlas_delete_zone_' . $id, '_wpnonce', 'get'); $this->repository->delete_zone($id); $this->redirect('zones'); }
	public function save_settings(): void {
		$this->guard('cotlas_ads_settings', 'cotlas_save_settings');
		$post_types = array_filter(array_map('sanitize_key', explode(',', wp_unslash($_POST['injection_post_types'] ?? 'post'))));
		$settings = array('track_impressions' => empty($_POST['track_impressions']) ? 0 : 1, 'track_clicks' => empty($_POST['track_clicks']) ? 0 : 1, 'bot_filter' => empty($_POST['bot_filter']) ? 0 : 1, 'retention_days' => min(3650, max(1, absint($_POST['retention_days'] ?? 365))), 'ads_txt' => sanitize_textarea_field(wp_unslash($_POST['ads_txt'] ?? '')), 'header_code' => current_user_can('unfiltered_html') ? wp_unslash($_POST['header_code'] ?? '') : wp_kses_post(wp_unslash($_POST['header_code'] ?? '')), 'injections' => array());
		if (!empty($_POST['injection_zone'])) $settings['injections'][] = array('zone' => sanitize_title(wp_unslash($_POST['injection_zone'])), 'position' => in_array($_POST['injection_position'] ?? '', array('before', 'after', 'paragraph'), true) ? $_POST['injection_position'] : 'after', 'paragraph' => absint($_POST['injection_paragraph'] ?? 2), 'post_types' => $post_types ?: array('post'));
		update_option('cotlas_ads_settings', $settings, false); $this->redirect('settings');
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
	private function local_date(?string $value): string { return $value ? wp_date('Y-m-d\TH:i', strtotime($value)) : ''; }
}
