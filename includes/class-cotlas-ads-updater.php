<?php
defined('ABSPATH') || exit;

final class Cotlas_Ads_Updater {
	private string $file;
	private string $basename;
	private string $repository;

	public function __construct(string $file, string $repository) {
		$this->file = $file;
		$this->basename = plugin_basename($file);
		$this->repository = $repository;
		add_filter('pre_set_site_transient_update_plugins', array($this, 'check'));
		add_filter('plugins_api', array($this, 'information'), 10, 3);
		add_filter('upgrader_post_install', array($this, 'normalize_folder'), 10, 3);
	}

	public function check($transient) {
		if (!is_object($transient) || empty($transient->checked[$this->basename])) return $transient;
		$release = $this->release();
		if (!$release || empty($release['tag_name'])) return $transient;
		$remote = ltrim($release['tag_name'], 'vV');
		if (!version_compare(COTLAS_ADS_VERSION, $remote, '<')) return $transient;
		$transient->response[$this->basename] = (object) array('slug' => 'cotlas-ads', 'plugin' => $this->basename, 'new_version' => $remote, 'url' => 'https://github.com/' . $this->repository, 'package' => $this->package($release), 'tested' => '7.1', 'requires_php' => '8.0');
		return $transient;
	}

	public function information($result, string $action, $args) {
		if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'cotlas-ads') return $result;
		$release = $this->release();
		if (!$release) return $result;
		return (object) array('name' => 'Cotlas Ads', 'slug' => 'cotlas-ads', 'version' => ltrim($release['tag_name'] ?? COTLAS_ADS_VERSION, 'vV'), 'author' => 'Cotlas', 'homepage' => 'https://github.com/' . $this->repository, 'sections' => array('description' => 'Lightweight, self-hosted ad operations for newsrooms.', 'changelog' => nl2br(esc_html($release['body'] ?? ''))), 'download_link' => $this->package($release));
	}

	public function normalize_folder($response, array $hook_extra, array $result): array {
		if (($hook_extra['plugin'] ?? '') !== $this->basename || empty($result['destination'])) return $result;
		global $wp_filesystem;
		$destination = WP_PLUGIN_DIR . '/cotlas-ads';
		if (untrailingslashit($result['destination']) === untrailingslashit($destination)) return $result;
		if ($wp_filesystem->exists($destination)) $wp_filesystem->delete($destination, true);
		$wp_filesystem->move($result['destination'], $destination, true);
		$result['destination'] = $destination;
		return $result;
	}

	private function release() {
		$cached = get_site_transient('cotlas_ads_github_release');
		if ($cached !== false) return $cached === 'none' ? false : $cached;
		$headers = array('Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28');
		if (defined('COTLAS_GITHUB_TOKEN') && COTLAS_GITHUB_TOKEN) $headers['Authorization'] = 'Bearer ' . COTLAS_GITHUB_TOKEN;
		$response = wp_remote_get('https://api.github.com/repos/' . $this->repository . '/releases/latest', array('headers' => $headers, 'timeout' => 10));
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { set_site_transient('cotlas_ads_github_release', 'none', HOUR_IN_SECONDS); return false; }
		$release = json_decode(wp_remote_retrieve_body($response), true);
		set_site_transient('cotlas_ads_github_release', $release, 6 * HOUR_IN_SECONDS);
		return $release;
	}

	private function package(array $release): string {
		foreach ((array) ($release['assets'] ?? array()) as $asset) {
			if (str_ends_with(strtolower($asset['name'] ?? ''), '.zip')) return esc_url_raw($asset['browser_download_url'] ?? '');
		}
		return esc_url_raw($release['zipball_url'] ?? '');
	}
}
