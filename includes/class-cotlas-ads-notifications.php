<?php
defined('ABSPATH') || exit;

final class Cotlas_Ads_Notifications {
	private Cotlas_Ads_Repository $repository;

	public function __construct(Cotlas_Ads_Repository $repository) {
		$this->repository = $repository;
		add_action('cotlas_ads_daily_cleanup', array($this, 'send'));
	}

	public function send(): void {
		$settings = wp_parse_args(get_option('cotlas_ads_settings', array()), array('advertiser_emails_enabled' => 0, 'advertiser_email_frequency' => 'weekly'));
		if (empty($settings['advertiser_emails_enabled'])) return;
		$frequency = in_array($settings['advertiser_email_frequency'], array('daily', 'weekly', 'monthly'), true) ? $settings['advertiser_email_frequency'] : 'weekly';
		$last = absint(get_option('cotlas_ads_last_advertiser_report', 0));
		$seconds = array('daily' => DAY_IN_SECONDS, 'weekly' => WEEK_IN_SECONDS, 'monthly' => 30 * DAY_IN_SECONDS)[$frequency];
		$report_due = !$last || time() - $last >= $seconds;
		$alerts = (array) get_option('cotlas_ads_delivery_alerts', array());
		foreach (get_users(array('role' => 'cotlas_advertiser')) as $user) {
			$ads = $this->repository->ads_for_advertiser((int) $user->ID);
			if (!$ads) continue;
			$lines = array();
			foreach ($ads as $ad) {
				$totals = $this->repository->totals((int) $ad['id'], 30);
				$ctr = $totals['impression'] ? round($totals['click'] / $totals['impression'] * 100, 2) : 0;
				$lines[] = sprintf('%s: %s impressions, %s clicks, %s%% CTR', $ad['name'], number_format_i18n($totals['impression']), number_format_i18n($totals['click']), $ctr);
				$reason = $this->expired_reason($ad);
				$key = (int) $ad['id'] . ':' . md5($reason . '|' . $ad['end_at'] . '|' . $ad['max_impressions'] . '|' . $ad['max_clicks']);
				if ($reason && empty($alerts[$key])) {
					wp_mail($user->user_email, sprintf('[%s] Campaign delivery alert', wp_specialchars_decode(get_bloginfo('name'))), sprintf("Hello %s,\n\nThe campaign “%s” is no longer delivering because %s.\n\nView your report: %s", $user->display_name, $ad['name'], $reason, admin_url('admin.php?page=cotlas-ads')));
					$alerts[$key] = time();
				}
			}
			if ($report_due) wp_mail($user->user_email, sprintf('[%s] Advertising report', wp_specialchars_decode(get_bloginfo('name'))), "Hello {$user->display_name},\n\nHere is your 30-day campaign summary:\n\n" . implode("\n", $lines) . "\n\nView details or download CSV: " . admin_url('admin.php?page=cotlas-ads'));
		}
		update_option('cotlas_ads_delivery_alerts', $alerts, false);
		if ($report_due) update_option('cotlas_ads_last_advertiser_report', time(), false);
	}

	private function expired_reason(array $ad): string {
		if ($ad['end_at'] && time() > strtotime($ad['end_at'])) return 'its end date has passed';
		if (!$ad['max_impressions'] && !$ad['max_clicks']) return '';
		$totals = $this->repository->totals((int) $ad['id'], 3650);
		if ($ad['max_impressions'] && $totals['impression'] >= (int) $ad['max_impressions']) return 'its impression cap was reached';
		if ($ad['max_clicks'] && $totals['click'] >= (int) $ad['max_clicks']) return 'its click cap was reached';
		return '';
	}
}
