<?php
defined('ABSPATH') || exit;

final class Cotlas_Ads_Tracker {
	private Cotlas_Ads_Repository $repository;

	public function __construct(Cotlas_Ads_Repository $repository) {
		$this->repository = $repository;
		add_action('template_redirect', array($this, 'handle'));
		add_action('cotlas_ads_daily_cleanup', array($this, 'cleanup'));
	}

	public function handle(): void {
		$settings = wp_parse_args(get_option('cotlas_ads_settings', array()), array('track_impressions' => 1, 'track_clicks' => 1, 'bot_filter' => 1));
		$is_bot = !empty($settings['bot_filter']) && $this->is_bot();

		if (isset($_GET['cotlas-ad-view'])) {
			if (!$is_bot && !empty($settings['track_impressions'])) $this->repository->increment(absint($_GET['cotlas-ad-view']), absint($_GET['zone'] ?? 0), 'impression');
			nocache_headers();
			header('Content-Type: image/gif');
			echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
			exit;
		}

		if (isset($_GET['cotlas-ad-click'])) {
			$ad_id = absint($_GET['cotlas-ad-click']);
			$ad = $this->repository->ad($ad_id);
			$target = $ad ? esc_url_raw($ad['target_url']) : '';
			$expected = substr(wp_hash('cotlas_click_' . $ad_id), 0, 20);
			$provided = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
			if (!$target || !hash_equals($expected, $provided)) return;
			if (!$is_bot && !empty($settings['track_clicks'])) $this->repository->increment($ad_id, absint($_GET['zone'] ?? 0), 'click');
			wp_redirect($target, 302, 'Cotlas Ads');
			exit;
		}
	}

	public function cleanup(): void {
		$settings = get_option('cotlas_ads_settings', array());
		$this->repository->cleanup(absint($settings['retention_days'] ?? 365));
	}

	private function is_bot(): bool {
		$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
		return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|headless/i', $ua);
	}
}
