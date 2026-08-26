<?php
defined('ABSPATH') || exit;

final class Cotlas_Ads_Repository {
	private wpdb $db;
	private string $ads;
	private string $zones;
	private string $events;

	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
		$this->ads = $wpdb->prefix . 'cotlas_ads';
		$this->zones = $wpdb->prefix . 'cotlas_ad_zones';
		$this->events = $wpdb->prefix . 'cotlas_ad_events';
	}

	public function ads(): array {
		return $this->db->get_results("SELECT * FROM {$this->ads} ORDER BY updated_at DESC", ARRAY_A) ?: array();
	}

	public function ads_for_advertiser(int $user_id): array {
		return $this->db->get_results($this->db->prepare("SELECT * FROM {$this->ads} WHERE advertiser_user_id=%d ORDER BY updated_at DESC", $user_id), ARRAY_A) ?: array();
	}

	public function ad(int $id): ?array {
		$row = $this->db->get_row($this->db->prepare("SELECT * FROM {$this->ads} WHERE id=%d", $id), ARRAY_A);
		return $row ?: null;
	}

	public function save_ad(array $data): int {
		$now = current_time('mysql');
		$row = array(
			'name' => sanitize_text_field($data['name'] ?? ''),
			'status' => in_array($data['status'] ?? '', array('active', 'paused', 'draft'), true) ? $data['status'] : 'draft',
			'creative_type' => in_array($data['creative_type'] ?? '', array('html', 'image', 'slider', 'video'), true) ? $data['creative_type'] : 'html',
			'content' => current_user_can('unfiltered_html') ? (string) ($data['content'] ?? '') : wp_kses_post($data['content'] ?? ''),
			'image_id' => absint($data['image_id'] ?? 0),
			'video_id' => absint($data['video_id'] ?? 0),
			'video_source' => in_array($data['video_source'] ?? '', array('upload', 'url', 'embed'), true) ? $data['video_source'] : 'upload',
			'video_url' => esc_url_raw($data['video_url'] ?? ''),
			'video_embed' => current_user_can('unfiltered_html') ? (string) ($data['video_embed'] ?? '') : wp_kses_post($data['video_embed'] ?? ''),
			'slider_image_ids' => implode(',', array_filter(array_map('absint', explode(',', (string) ($data['slider_image_ids'] ?? ''))))),
			'target_url' => esc_url_raw($data['target_url'] ?? ''),
			'canvas_width' => min(4096, absint($data['canvas_width'] ?? 0)),
			'canvas_height' => min(4096, absint($data['canvas_height'] ?? 0)),
			'slider_interval' => min(60, max(2, absint($data['slider_interval'] ?? 5))),
			'weight' => min(100, max(1, absint($data['weight'] ?? 10))),
			'start_at' => self::date_or_null($data['start_at'] ?? ''),
			'end_at' => self::date_or_null($data['end_at'] ?? ''),
			'days' => sanitize_text_field($data['days'] ?? ''),
			'hours' => sanitize_text_field($data['hours'] ?? ''),
			'device' => in_array($data['device'] ?? '', array('all', 'desktop', 'mobile', 'tablet'), true) ? $data['device'] : 'all',
			'countries' => strtoupper(sanitize_text_field($data['countries'] ?? '')),
			'include_logged_in' => empty($data['include_logged_in']) ? 0 : 1,
			'max_impressions' => absint($data['max_impressions'] ?? 0),
			'max_clicks' => absint($data['max_clicks'] ?? 0),
			'advertiser_user_id' => absint($data['advertiser_user_id'] ?? 0),
			'advertiser_can_edit' => empty($data['advertiser_can_edit']) ? 0 : 1,
			'updated_at' => $now,
		);
		$id = absint($data['id'] ?? 0);
		if ($id) {
			$this->db->update($this->ads, $row, array('id' => $id));
			return $id;
		}
		$row['created_at'] = $now;
		$this->db->insert($this->ads, $row);
		return (int) $this->db->insert_id;
	}

	public function update_advertiser_fields(int $id, array $data): void {
		$this->db->update($this->ads, array(
			'name' => sanitize_text_field($data['name'] ?? ''),
			'target_url' => esc_url_raw($data['target_url'] ?? ''),
			'status' => in_array($data['status'] ?? '', array('active', 'paused', 'draft'), true) ? $data['status'] : 'draft',
			'start_at' => self::date_or_null($data['start_at'] ?? ''),
			'end_at' => self::date_or_null($data['end_at'] ?? ''),
			'updated_at' => current_time('mysql'),
		), array('id' => $id));
	}

	public function zone_ids_for_ad(int $ad_id): array {
		$ids = array();
		foreach ($this->zones() as $zone) {
			if (in_array($ad_id, array_map('absint', explode(',', (string) $zone['ad_ids'])), true)) $ids[] = (int) $zone['id'];
		}
		return $ids;
	}

	public function assign_ad_to_zones(int $ad_id, array $zone_ids): void {
		$selected = array_map('absint', $zone_ids);
		foreach ($this->zones() as $zone) {
			$ad_ids = array_values(array_filter(array_map('absint', explode(',', (string) $zone['ad_ids']))));
			$has = in_array($ad_id, $ad_ids, true);
			$should = in_array((int) $zone['id'], $selected, true);
			if ($should && !$has) $ad_ids[] = $ad_id;
			if (!$should && $has) $ad_ids = array_values(array_diff($ad_ids, array($ad_id)));
			if ($has !== $should) $this->db->update($this->zones, array('ad_ids' => implode(',', $ad_ids), 'updated_at' => current_time('mysql')), array('id' => (int) $zone['id']));
		}
	}

	public function delete_ad(int $id): void {
		$this->db->delete($this->ads, array('id' => $id));
		$this->db->query($this->db->prepare("DELETE FROM {$this->events} WHERE ad_id=%d", $id));
	}

	public function zones(): array {
		return $this->db->get_results("SELECT * FROM {$this->zones} ORDER BY name", ARRAY_A) ?: array();
	}

	public function zone($id_or_slug): ?array {
		if (is_numeric($id_or_slug)) {
			$row = $this->db->get_row($this->db->prepare("SELECT * FROM {$this->zones} WHERE id=%d", absint($id_or_slug)), ARRAY_A);
		} else {
			$row = $this->db->get_row($this->db->prepare("SELECT * FROM {$this->zones} WHERE slug=%s", sanitize_title($id_or_slug)), ARRAY_A);
		}
		return $row ?: null;
	}

	public function save_zone(array $data): int {
		$now = current_time('mysql');
		$name = sanitize_text_field($data['name'] ?? '');
		$row = array(
			'name' => $name,
			'slug' => sanitize_title(!empty($data['slug']) ? $data['slug'] : $name),
			'mode' => in_array($data['mode'] ?? '', array('weighted', 'random', 'all'), true) ? $data['mode'] : 'weighted',
			'ad_ids' => implode(',', array_filter(array_map('absint', (array) ($data['ad_ids'] ?? array())))),
			'css_class' => sanitize_html_class($data['css_class'] ?? ''),
			// Placement managers are trusted to store complete ad markup. Fallback
			// creatives commonly include inline styles and third-party script tags.
			'fallback' => current_user_can('cotlas_ads_manage') ? (string) ($data['fallback'] ?? '') : wp_kses_post($data['fallback'] ?? ''),
			'updated_at' => $now,
		);
		$id = absint($data['id'] ?? 0);
		if ($id) {
			$this->db->update($this->zones, $row, array('id' => $id));
			return $id;
		}
		$row['created_at'] = $now;
		$this->db->insert($this->zones, $row);
		return (int) $this->db->insert_id;
	}

	public function delete_zone(int $id): void {
		$this->db->delete($this->zones, array('id' => $id));
	}

	public function totals(int $ad_id = 0, int $days = 30): array {
		$where = $this->db->prepare('event_date >= %s', gmdate('Y-m-d', time() - DAY_IN_SECONDS * $days));
		if ($ad_id) $where .= $this->db->prepare(' AND ad_id=%d', $ad_id);
		$rows = $this->db->get_results("SELECT event_date,event_type,SUM(count) count FROM {$this->events} WHERE {$where} GROUP BY event_date,event_type ORDER BY event_date", ARRAY_A) ?: array();
		$result = array('impression' => 0, 'click' => 0, 'series' => $rows);
		foreach ($rows as $row) $result[$row['event_type']] += (int) $row['count'];
		return $result;
	}

	public function totals_by_ad(int $days = 30): array {
		$since = gmdate('Y-m-d', time() - DAY_IN_SECONDS * $days);
		$rows = $this->db->get_results($this->db->prepare("SELECT ad_id,event_type,SUM(count) count FROM {$this->events} WHERE event_date >= %s GROUP BY ad_id,event_type", $since), ARRAY_A) ?: array();
		$result = array();
		foreach ($rows as $row) {
			$id = (int) $row['ad_id'];
			if (!isset($result[$id])) $result[$id] = array('impression' => 0, 'click' => 0);
			$result[$id][$row['event_type']] = (int) $row['count'];
		}
		return $result;
	}

	public function increment(int $ad_id, int $zone_id, string $type): void {
		if (!in_array($type, array('impression', 'click'), true)) return;
		$date = current_time('Y-m-d');
		$hour = (int) current_time('G');
		$sql = $this->db->prepare(
			"INSERT INTO {$this->events} (ad_id,zone_id,event_type,event_date,event_hour,count) VALUES (%d,%d,%s,%s,%d,1) ON DUPLICATE KEY UPDATE count=count+1",
			$ad_id, $zone_id, $type, $date, $hour
		);
		$this->db->query($sql);
	}

	public function cleanup(int $days): void {
		$before = gmdate('Y-m-d', time() - DAY_IN_SECONDS * max(1, $days));
		$this->db->query($this->db->prepare("DELETE FROM {$this->events} WHERE event_date < %s", $before));
	}

	private static function date_or_null(string $value): ?string {
		if (!$value) return null;
		try {
			$date = new DateTimeImmutable($value, wp_timezone());
			return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
		} catch (Exception $exception) {
			return null;
		}
	}
}
