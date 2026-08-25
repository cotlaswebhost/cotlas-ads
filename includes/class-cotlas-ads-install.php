<?php
defined('ABSPATH') || exit;

final class Cotlas_Ads_Install {
	public static function activate(): void {
		self::install_schema();
		self::add_roles();
		if (!wp_next_scheduled('cotlas_ads_daily_cleanup')) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'cotlas_ads_daily_cleanup');
		}
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		if (get_option('cotlas_ads_version') !== COTLAS_ADS_VERSION) {
			self::install_schema();
		}
	}

	private static function install_schema(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$ads = $wpdb->prefix . 'cotlas_ads';
		$zones = $wpdb->prefix . 'cotlas_ad_zones';
		$events = $wpdb->prefix . 'cotlas_ad_events';

		dbDelta("CREATE TABLE {$ads} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			creative_type varchar(20) NOT NULL DEFAULT 'html',
			content longtext NOT NULL,
			image_id bigint(20) unsigned NOT NULL DEFAULT 0,
			slider_image_ids text NULL,
			target_url text NULL,
			canvas_width smallint unsigned NOT NULL DEFAULT 0,
			canvas_height smallint unsigned NOT NULL DEFAULT 0,
			slider_interval smallint unsigned NOT NULL DEFAULT 5,
			weight smallint unsigned NOT NULL DEFAULT 10,
			start_at datetime NULL,
			end_at datetime NULL,
			days varchar(20) NOT NULL DEFAULT '',
			hours varchar(64) NOT NULL DEFAULT '',
			device varchar(20) NOT NULL DEFAULT 'all',
			countries text NULL,
			include_logged_in tinyint(1) NOT NULL DEFAULT 1,
			max_impressions bigint unsigned NOT NULL DEFAULT 0,
			max_clicks bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_dates (status,start_at,end_at)
		) {$charset};");

		dbDelta("CREATE TABLE {$zones} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			mode varchar(20) NOT NULL DEFAULT 'weighted',
			ad_ids text NULL,
			css_class varchar(190) NOT NULL DEFAULT '',
			fallback longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset};");

		dbDelta("CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ad_id bigint(20) unsigned NOT NULL,
			zone_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(12) NOT NULL,
			event_date date NOT NULL,
			event_hour tinyint unsigned NOT NULL,
			count bigint unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY event_rollup (ad_id,zone_id,event_type,event_date,event_hour),
			KEY event_date (event_date)
		) {$charset};");

		update_option('cotlas_ads_version', COTLAS_ADS_VERSION, false);
		add_option('cotlas_ads_settings', array(
			'track_impressions' => 1,
			'track_clicks' => 1,
			'retention_days' => 365,
			'bot_filter' => 1,
			'header_code' => '',
			'ads_txt' => '',
			'injections' => array(),
			'ad_label' => 'Advertisement',
			'adblock_enabled' => 0,
			'adblock_dismissible' => 1,
			'adblock_title' => 'Please disable your ad blocker',
			'adblock_message' => 'Advertising supports our newsroom. Please disable your ad blocker and reload this page to continue.',
		));
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook('cotlas_ads_daily_cleanup');
		flush_rewrite_rules();
	}

	private static function add_roles(): void {
		$admin = get_role('administrator');
		if ($admin) {
			foreach (array('cotlas_ads_manage', 'cotlas_ads_report', 'cotlas_ads_settings') as $cap) {
				$admin->add_cap($cap);
			}
		}
		add_role('cotlas_advertiser', __('Ad Advertiser', 'cotlas-ads'), array(
			'read' => true,
			'cotlas_ads_report' => true,
		));
	}
}
