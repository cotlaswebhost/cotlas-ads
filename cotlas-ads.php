<?php
/**
 * Plugin Name: Cotlas Ads
 * Description: Lightweight, self-hosted advertising management for newsrooms.
 * Version: 0.3.3
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: Cotlas
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/cotlaswebhost/cotlas-ads
 * Text Domain: cotlas-ads
 */

defined('ABSPATH') || exit;

define('COTLAS_ADS_VERSION', '0.3.1');
define('COTLAS_ADS_FILE', __FILE__);
define('COTLAS_ADS_DIR', plugin_dir_path(__FILE__));
define('COTLAS_ADS_URL', plugin_dir_url(__FILE__));

require_once COTLAS_ADS_DIR . 'includes/class-cotlas-ads-install.php';
require_once COTLAS_ADS_DIR . 'includes/class-cotlas-ads-repository.php';
require_once COTLAS_ADS_DIR . 'includes/class-cotlas-ads-engine.php';
require_once COTLAS_ADS_DIR . 'includes/class-cotlas-ads-tracker.php';
require_once COTLAS_ADS_DIR . 'includes/class-cotlas-ads-admin.php';
require_once COTLAS_ADS_DIR . 'includes/class-cotlas-ads-updater.php';

register_activation_hook(__FILE__, array('Cotlas_Ads_Install', 'activate'));
register_deactivation_hook(__FILE__, array('Cotlas_Ads_Install', 'deactivate'));

function cotlas_ads(): Cotlas_Ads_Engine {
	static $instance;
	if (!$instance) {
		$repository = new Cotlas_Ads_Repository();
		$instance = new Cotlas_Ads_Engine($repository);
		new Cotlas_Ads_Tracker($repository);
		if (is_admin()) {
			new Cotlas_Ads_Admin($repository);
		}
	}
	return $instance;
}
add_action('plugins_loaded', 'cotlas_ads');
add_action('plugins_loaded', array('Cotlas_Ads_Install', 'maybe_upgrade'), 5);

if (is_admin()) {
	new Cotlas_Ads_Updater(COTLAS_ADS_FILE, 'cotlaswebhost/cotlas-ads');
}

function cotlas_ad(int $id, array $args = array()): string {
	return cotlas_ads()->render_ad($id, $args);
}

function cotlas_ad_zone($zone, array $args = array()): string {
	return cotlas_ads()->render_zone($zone, $args);
}
