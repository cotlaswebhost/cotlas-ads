<?php
defined('ABSPATH') || exit;

final class Cotlas_Ads_Engine {
	private Cotlas_Ads_Repository $repository;
	private array $settings;

	public function __construct(Cotlas_Ads_Repository $repository) {
		$this->repository = $repository;
		$this->settings = wp_parse_args(get_option('cotlas_ads_settings', array()), array('header_code' => '', 'injections' => array(), 'ad_label' => 'Advertisement', 'adblock_enabled' => 0, 'adblock_dismissible' => 1, 'adblock_title' => 'Please disable your ad blocker', 'adblock_message' => 'Advertising supports our newsroom. Please disable your ad blocker and reload this page to continue.', 'video_upload_enabled' => 0, 'video_max_mb' => 20, 'ga4_adapter_enabled' => 0, 'matomo_adapter_enabled' => 0, 'ga4_impression_event' => 'ad_impression', 'ga4_click_event' => 'ad_click', 'matomo_category' => 'Advertising', 'asset_alias_enabled' => 1, 'asset_probe_alias' => 'site-runtime-check.js', 'asset_control_alias' => 'site-support-check.js'));
		add_shortcode('cotlas_ad', array($this, 'shortcode'));
		add_action('wp_head', array($this, 'header_code'), 99);
		add_filter('the_content', array($this, 'inject_content'), 20);
		add_action('init', array($this, 'register_block'));
		add_action('init', array($this, 'ads_txt_route'));
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		add_action('wp_footer', array($this, 'adblock_markup'), 100);
		add_action('wp_body_open', array($this, 'inject_header'));
		add_action('wp_footer', array($this, 'inject_footer'), 20);
		add_action('wp_footer', array($this, 'overlay_placements'), 30);
		add_action('loop_start', array($this, 'reset_feed_counter'));
		add_action('the_post', array($this, 'inject_feed_item'));
		add_filter('dynamic_sidebar_params', array($this, 'inject_sidebar_before'));
		add_action('dynamic_sidebar_after', array($this, 'inject_sidebar_after'));
		add_action('template_redirect', array($this, 'serve_asset_alias'), 0);
	}

	public function register_assets(): void {
		wp_register_style('cotlas-ads-front', COTLAS_ADS_URL . 'assets/frontend.css', array(), COTLAS_ADS_VERSION);
		wp_register_script('cotlas-ads-front', COTLAS_ADS_URL . 'assets/frontend.js', array(), COTLAS_ADS_VERSION, true);
		$has_overlay = (bool) array_filter($this->repository->zones(), fn($zone) => in_array($zone['placement_type'] ?? 'standard', array('interstitial', 'sticky'), true));
		if ($has_overlay) { wp_enqueue_style('cotlas-ads-front'); wp_enqueue_script('cotlas-ads-front'); }
		if (!empty($this->settings['ga4_adapter_enabled']) || !empty($this->settings['matomo_adapter_enabled'])) {
			wp_enqueue_script('cotlas-ads-front');
			wp_localize_script('cotlas-ads-front', 'cotlasAdsEvents', array('ga4' => !empty($this->settings['ga4_adapter_enabled']), 'matomo' => !empty($this->settings['matomo_adapter_enabled']), 'ga4Impression' => $this->settings['ga4_impression_event'], 'ga4Click' => $this->settings['ga4_click_event'], 'matomoCategory' => $this->settings['matomo_category']));
		}
		if (!empty($this->settings['adblock_enabled'])) {
			wp_enqueue_style('cotlas-ads-front');
			wp_enqueue_script('cotlas-ads-front');
		}
	}

	public function register_block(): void {
		register_block_type('cotlas/ads', array(
			'attributes' => array('zone' => array('type' => 'string', 'default' => '')),
			'render_callback' => function (array $attributes): string {
				return $this->render_zone($attributes['zone'] ?? '');
			},
		));
	}

	public function shortcode(array $attributes = array()): string {
		$attributes = shortcode_atts(array('id' => 0, 'zone' => '', 'class' => ''), $attributes, 'cotlas_ad');
		return $attributes['zone'] !== ''
			? $this->render_zone($attributes['zone'], array('class' => $attributes['class']))
			: $this->render_ad(absint($attributes['id']), array('class' => $attributes['class']));
	}

	public function render_zone($id_or_slug, array $args = array()): string {
		$zone = $this->repository->zone($id_or_slug);
		if (!$zone) return '';
		$ads = array_filter(array_map(array($this->repository, 'ad'), array_map('absint', explode(',', (string) $zone['ad_ids']))));
		$eligible = array_values(array_filter($ads, array($this, 'is_eligible')));
		if (!$eligible) return (string) $zone['fallback'];
		if ($zone['mode'] === 'all') {
			$html = '';
			foreach ($eligible as $ad) $html .= $this->creative($ad, (int) $zone['id']);
		} else {
			$ad = $zone['mode'] === 'random' ? $eligible[array_rand($eligible)] : $this->weighted_pick($eligible);
			$html = $this->creative($ad, (int) $zone['id']);
		}
		$class = trim('cotlas-zone ' . sanitize_html_class($zone['css_class']) . ' ' . sanitize_html_class($args['class'] ?? ''));
		return '<div class="' . esc_attr($class) . '" data-cotlas-zone="' . absint($zone['id']) . '">' . $html . '</div>';
	}

	public function render_ad(int $id, array $args = array()): string {
		$ad = $this->repository->ad($id);
		if (!$ad || !$this->is_eligible($ad)) return '';
		$class = trim('cotlas-placement ' . sanitize_html_class($args['class'] ?? ''));
		return '<div class="' . esc_attr($class) . '">' . $this->creative($ad, 0) . '</div>';
	}

	public function is_eligible(array $ad): bool {
		if ($ad['status'] !== 'active') return false;
		$now = time();
		if ($ad['start_at'] && $now < strtotime($ad['start_at'])) return false;
		if ($ad['end_at'] && $now > strtotime($ad['end_at'])) return false;
		if (!$ad['include_logged_in'] && is_user_logged_in()) return false;
		if ($ad['days'] !== '' && !in_array((string) current_time('w'), array_map('trim', explode(',', $ad['days'])), true)) return false;
		if ($ad['hours'] !== '' && !in_array((string) current_time('G'), array_map('trim', explode(',', $ad['hours'])), true)) return false;
		if (!$this->device_matches($ad['device'])) return false;
		if (!$this->country_matches($ad['countries'])) return false;
		if ($ad['max_impressions'] || $ad['max_clicks']) {
			$totals = $this->repository->totals((int) $ad['id'], 3650);
			if ($ad['max_impressions'] && $totals['impression'] >= (int) $ad['max_impressions']) return false;
			if ($ad['max_clicks'] && $totals['click'] >= (int) $ad['max_clicks']) return false;
		}
		return true;
	}

	private function creative(array $ad, int $zone_id): string {
		wp_enqueue_style('cotlas-ads-front');
		$click_url = '';
		if ($ad['target_url']) {
			$click_url = add_query_arg(array('cotlas-ad-click' => (int) $ad['id'], 'zone' => $zone_id, 'token' => substr(wp_hash('cotlas_click_' . (int) $ad['id']), 0, 20)), home_url('/'));
		}
		$body = (string) $ad['content'];
		if ($ad['creative_type'] === 'image' && $ad['image_id']) {
			$body = wp_get_attachment_image((int) $ad['image_id'], 'full', false, array('loading' => 'lazy', 'decoding' => 'async', 'alt' => $ad['name'], 'class' => 'cotlas-ad-image'));
		} elseif ($ad['creative_type'] === 'slider') {
			wp_enqueue_script('cotlas-ads-front');
			$slides = '';
			foreach (array_values(array_filter(array_map('absint', explode(',', (string) $ad['slider_image_ids'])))) as $index => $image_id) {
				$image = wp_get_attachment_image($image_id, 'full', false, array('loading' => $index === 0 ? 'eager' : 'lazy', 'decoding' => 'async', 'alt' => $ad['name'], 'class' => 'cotlas-ad-image'));
				if ($click_url) $image = '<a href="' . esc_url($click_url) . '" rel="sponsored noopener" target="_blank">' . $image . '</a>';
				$slides .= '<div class="cotlas-slide' . ($index === 0 ? ' is-active' : '') . '">' . $image . '</div>';
			}
			if ($slides !== '') $body = '<div class="cotlas-slider" data-cotlas-slider data-interval="' . absint($ad['slider_interval']) . '">' . $slides . '</div>';
		} elseif ($ad['creative_type'] === 'video') {
			$source = $ad['video_source'] ?? 'upload';
			if ($source === 'embed' && !empty($ad['video_embed'])) {
				$body = (string) $ad['video_embed'];
			} else {
				$video_url = $source === 'url' ? esc_url_raw($ad['video_url'] ?? '') : wp_get_attachment_url((int) ($ad['video_id'] ?? 0));
				if ($source === 'url' && $video_url && !preg_match('/\.mp4(?:$|\?)/i', $video_url)) {
					$embed = wp_oembed_get($video_url, array('width' => max(300, absint($ad['canvas_width']))));
					if ($embed) $body = $embed;
				} elseif ($video_url) {
					$body = '<video class="cotlas-ad-video" autoplay muted loop playsinline preload="metadata" aria-label="' . esc_attr($ad['name']) . '"><source src="' . esc_url($video_url) . '" type="video/mp4"></video>';
				}
			}
		} elseif ($ad['creative_type'] === 'branded') {
			$logo = $ad['brand_logo_id'] ? wp_get_attachment_image((int) $ad['brand_logo_id'], 'medium', false, array('class' => 'cotlas-brand-logo', 'alt' => '')) : '<span class="cotlas-brand-placeholder" aria-hidden="true">AD</span>';
			$background = $ad['background_image_id'] ? wp_get_attachment_image_url((int) $ad['background_image_id'], 'full') : '';
			$background_style = $background ? 'background-image:linear-gradient(90deg,rgba(255,255,255,.9),rgba(255,255,255,.82)),url(' . esc_url($background) . ');background-position:center center;background-size:cover;background-repeat:no-repeat;' : '';
			$button = $click_url && $ad['promo_button_text'] !== '' ? '<a class="cotlas-brand-button" href="' . esc_url($click_url) . '" rel="sponsored noopener" target="_blank">' . esc_html($ad['promo_button_text']) . '</a>' : '';
			$body = '<div class="cotlas-brand-card" style="' . esc_attr($background_style) . '"><div class="cotlas-brand-identity">' . $logo . '</div><div class="cotlas-brand-copy"><strong>' . esc_html($ad['promo_title']) . '</strong><span>' . esc_html($ad['promo_description']) . '</span></div><div class="cotlas-brand-actions">' . $button . '</div></div>';
		}
		if ($click_url && !in_array($ad['creative_type'], array('slider', 'video', 'branded'), true)) {
			$body = '<a href="' . esc_url($click_url) . '" rel="sponsored noopener" target="_blank">' . $body . '</a>';
		}
		$pixel = add_query_arg(array('cotlas-ad-view' => (int) $ad['id'], 'zone' => $zone_id), home_url('/'));
		$outer_style = (int) $ad['canvas_width'] > 0 ? 'width:min(100%,' . absint($ad['canvas_width']) . 'px);' : '';
		$frame_style = (int) $ad['canvas_height'] > 0 ? 'height:' . absint($ad['canvas_height']) . 'px;' : '';
		$label = trim((string) $this->settings['ad_label']);
		$label_html = $label !== '' ? '<div class="cotlas-ad-label">' . esc_html($label) . '</div>' : '';
		$creative_class = sanitize_html_class($ad['creative_css_class'] ?? '');
		return '<div class="cotlas-ad cotlas-type-' . esc_attr($ad['creative_type']) . ' ' . esc_attr($creative_class) . '" style="' . esc_attr($outer_style) . '" data-cotlas-ad="' . absint($ad['id']) . '" data-cotlas-zone="' . absint($zone_id) . '" data-cotlas-name="' . esc_attr($ad['name']) . '"><div class="cotlas-ad-frame" style="' . esc_attr($frame_style) . '">' . $body . '<img src="' . esc_url($pixel) . '" width="1" height="1" alt="" loading="eager" class="cotlas-pixel" /></div>' . $label_html . '</div>';
	}

	public function adblock_markup(): void {
		if (empty($this->settings['adblock_enabled'])) return;
		$dismissible = !empty($this->settings['adblock_dismissible']);
		$aliased = !empty($this->settings['asset_alias_enabled']);
		$probe_url = add_query_arg('ver', COTLAS_ADS_VERSION, $aliased ? home_url('/' . $this->settings['asset_probe_alias']) : COTLAS_ADS_URL . 'assets/advertisement.js');
		$control_url = add_query_arg('ver', COTLAS_ADS_VERSION, $aliased ? home_url('/' . $this->settings['asset_control_alias']) : COTLAS_ADS_URL . 'assets/support-check.js');
		?>
		<div class="adsbox ad-banner advertisement pub_300x250 cotlas-block-test" aria-hidden="true">&nbsp;</div>
		<div class="cotlas-support-overlay" data-cotlas-support-overlay data-dismissible="<?php echo $dismissible ? '1' : '0'; ?>" hidden>
			<div class="cotlas-support-dialog" role="dialog" aria-modal="true" aria-labelledby="cotlas-support-title" aria-describedby="cotlas-support-message">
				<div class="cotlas-support-icon" aria-hidden="true">!</div>
				<h2 id="cotlas-support-title"><?php echo esc_html($this->settings['adblock_title']); ?></h2>
				<p id="cotlas-support-message"><?php echo esc_html($this->settings['adblock_message']); ?></p>
				<button type="button" class="cotlas-support-reload" data-support-reload>Reload after disabling</button>
				<?php if ($dismissible): ?><button type="button" class="cotlas-support-dismiss" data-support-dismiss>Continue without disabling</button><?php endif; ?>
			</div>
		</div>
		<script>
		(function(){
			var overlay=document.querySelector('[data-cotlas-support-overlay]');
			if(!overlay)return;
			function isHidden(el){if(!el)return true;var style=window.getComputedStyle(el);return style.display==='none'||style.visibility==='hidden'||el.offsetWidth===0||el.offsetHeight===0;}
			var probeLoaded=false,probeFailed=false,controlLoaded=false;
			var probe=document.createElement('script');
			probe.src=<?php echo wp_json_encode($probe_url); ?>;
			probe.async=true;
			probe.onload=function(){probeLoaded=true;};
			probe.onerror=function(){probeFailed=true;};
			document.head.appendChild(probe);
			var control=document.createElement('script');
			control.src=<?php echo wp_json_encode($control_url); ?>;
			control.async=true;
			control.onload=function(){controlLoaded=true;};
			document.head.appendChild(control);
			window.setTimeout(function(){
				var bait=document.querySelector('.cotlas-block-test');
				var units=Array.prototype.slice.call(document.querySelectorAll('.cotlas-ad'));
				var realAdBlocked=units.length>0&&units.every(function(unit){
					if(isHidden(unit))return true;
					if(!unit.classList.contains('cotlas-type-image')&&!unit.classList.contains('cotlas-type-slider'))return false;
					var images=Array.prototype.slice.call(unit.querySelectorAll('.cotlas-ad-image'));
					return images.length===0||images.every(function(image){return isHidden(image)||(image.complete&&image.naturalWidth===0);});
				});
				var networkProbeBlocked=controlLoaded&&(probeFailed||!probeLoaded||!window.cotlasAdvertisementProbe);
				var dismissed=overlay.dataset.dismissible==='1'&&window.sessionStorage.getItem('cotlas_support_dismissed')==='1';
				if(realAdBlocked)units.forEach(function(unit){var label=unit.querySelector('.cotlas-ad-label');if(label)label.hidden=true;});
				if((isHidden(bait)||realAdBlocked||networkProbeBlocked)&&!dismissed){overlay.hidden=false;document.documentElement.classList.add('cotlas-support-locked');var button=overlay.querySelector('button');if(button)button.focus();}
			},2500);
			overlay.querySelector('[data-support-reload]').addEventListener('click',function(){window.location.reload();});
			var dismiss=overlay.querySelector('[data-support-dismiss]');if(dismiss)dismiss.addEventListener('click',function(){window.sessionStorage.setItem('cotlas_support_dismissed','1');overlay.hidden=true;document.documentElement.classList.remove('cotlas-support-locked');});
		})();
		</script>
		<?php
	}

	public function serve_asset_alias(): void {
		if (empty($this->settings['asset_alias_enabled'])) return;
		$path = basename((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
		$probe = trim((string) $this->settings['asset_probe_alias'], '/');
		$control = trim((string) $this->settings['asset_control_alias'], '/');
		if ($path !== $probe && $path !== $control) return;
		$file = $path === $probe ? 'advertisement.js' : 'support-check.js';
		$source = COTLAS_ADS_DIR . 'assets/' . $file;
		if (!is_readable($source)) return;
		nocache_headers(); header('Content-Type: application/javascript; charset=utf-8'); header('X-Content-Type-Options: nosniff');
		readfile($source); exit; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	}

	private function weighted_pick(array $ads): array {
		$total = array_sum(array_map(fn($ad) => max(1, (int) $ad['weight']), $ads));
		$pick = random_int(1, max(1, $total));
		foreach ($ads as $ad) {
			$pick -= max(1, (int) $ad['weight']);
			if ($pick <= 0) return $ad;
		}
		return $ads[0];
	}

	private function device_matches(string $device): bool {
		if ($device === 'all') return true;
		$mobile = wp_is_mobile();
		$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
		$tablet = $mobile && (str_contains($ua, 'ipad') || str_contains($ua, 'tablet') || (str_contains($ua, 'android') && !str_contains($ua, 'mobile')));
		return ($device === 'tablet' && $tablet) || ($device === 'mobile' && $mobile && !$tablet) || ($device === 'desktop' && !$mobile);
	}

	private function country_matches(string $countries): bool {
		if (trim($countries) === '') return true;
		$country = strtoupper(sanitize_text_field($_SERVER['HTTP_CF_IPCOUNTRY'] ?? $_SERVER['HTTP_X_COUNTRY_CODE'] ?? ''));
		return $country === '' || in_array($country, array_map('trim', explode(',', strtoupper($countries))), true);
	}

	public function header_code(): void {
		// Stored only by administrators with unfiltered_html; ad networks require script tags.
		if (!empty($this->settings['header_code'])) echo "\n" . $this->settings['header_code'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function inject_content(string $content): string {
		if (!is_singular() || !is_main_query() || !in_the_loop()) return $content;
		foreach ($this->injection_rules() as $rule) {
			if (!in_array($rule['location'], array('before', 'after', 'paragraph'), true) || !in_array(get_post_type(), $rule['post_types'], true)) continue;
			$ad = $this->render_zone($rule['zone']);
			if ($rule['location'] === 'before') $content = $ad . $content;
			elseif ($rule['location'] === 'after') $content .= $ad;
			else $content = $this->inject_after_nonempty_paragraph($content, $ad, absint($rule['number']));
		}
		foreach ((array) ($this->settings['injections'] ?? array()) as $rule) {
			if (empty($rule['zone']) || !in_array(get_post_type(), (array) ($rule['post_types'] ?? array('post')), true)) continue;
			$ad = $this->render_zone($rule['zone']);
			if (($rule['position'] ?? '') === 'before') $content = $ad . $content;
			elseif (($rule['position'] ?? '') === 'after') $content .= $ad;
			elseif (($rule['position'] ?? '') === 'paragraph') $content = $this->inject_after_nonempty_paragraph($content, $ad, absint($rule['paragraph'] ?? 2));
		}
		return $content;
	}

	private function inject_after_nonempty_paragraph(string $content, string $ad, int $target): string {
		$count = 0;
		$target = max(1, $target);
		$result = preg_replace_callback('/<p\b[^>]*>.*?<\/p>/is', function (array $match) use (&$count, $target, $ad): string {
			$text = html_entity_decode(wp_strip_all_tags($match[0]), ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
			$text = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $text);
			$insert = false;
			if ($text !== '') { $count++; $insert = $count === $target; }
			return $match[0] . ($insert ? $ad : '');
		}, $content);
		return is_string($result) ? $result : $content;
	}

	private int $feed_counter = 0;
	private array $sidebar_started = array();

	private function injection_rules(): array { return array_values((array) ($this->settings['injection_rules'] ?? array())); }

	public function inject_header(): void { foreach ($this->injection_rules() as $rule) if (($rule['location'] ?? '') === 'header') echo $this->render_zone($rule['zone']); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ }
	public function inject_footer(): void { foreach ($this->injection_rules() as $rule) if (($rule['location'] ?? '') === 'footer') echo $this->render_zone($rule['zone']); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ }
	public function reset_feed_counter($query): void { if ($query instanceof WP_Query && $query->is_main_query() && !$query->is_singular()) $this->feed_counter = 0; }
	public function inject_feed_item($post): void {
		global $wp_query; if (!$wp_query || !$wp_query->is_main_query() || $wp_query->is_singular()) return; $this->feed_counter++;
		foreach ($this->injection_rules() as $rule) if (($rule['location'] ?? '') === 'feed' && $this->feed_counter === absint($rule['number'] ?? 2)) echo $this->render_zone($rule['zone']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	public function inject_sidebar_before(array $params): array {
		$sidebar = sanitize_key($params[0]['id'] ?? ''); if (isset($this->sidebar_started[$sidebar])) return $params; $this->sidebar_started[$sidebar] = true;
		foreach ($this->injection_rules() as $rule) if (($rule['location'] ?? '') === 'sidebar_before' && (empty($rule['sidebar_id']) || $rule['sidebar_id'] === $sidebar)) echo $this->render_zone($rule['zone']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $params;
	}
	public function inject_sidebar_after(string $sidebar): void { $sidebar = sanitize_key($sidebar); foreach ($this->injection_rules() as $rule) if (($rule['location'] ?? '') === 'sidebar_after' && (empty($rule['sidebar_id']) || $rule['sidebar_id'] === $sidebar)) echo $this->render_zone($rule['zone']); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ }

	public function overlay_placements(): void {
		foreach ($this->repository->zones() as $zone) {
			$type = $zone['placement_type'] ?? 'standard'; if (!in_array($type, array('interstitial', 'sticky'), true)) continue;
			$html = $this->render_zone((int) $zone['id']); if ($html === '') continue;
			if ($type === 'interstitial') echo '<div class="cotlas-interstitial" data-cotlas-interstitial data-zone="' . absint($zone['id']) . '" data-clicks="' . absint($zone['trigger_clicks']) . '" data-cooldown="' . absint($zone['cooldown_minutes']) . '" hidden><div class="cotlas-overlay-ad"><button type="button" class="cotlas-overlay-close" aria-label="Close advertisement">×</button>' . $html . '</div></div>';
			else echo '<div class="cotlas-sticky-ad" data-cotlas-sticky data-zone="' . absint($zone['id']) . '" data-cooldown="' . absint($zone['cooldown_minutes']) . '" style="--cotlas-sticky-height:' . absint($zone['max_height']) . 'px" hidden><button type="button" class="cotlas-overlay-close" aria-label="Close advertisement">×</button>' . $html . '</div>';
		}
	}

	public function ads_txt_route(): void {
		if (($_SERVER['REQUEST_URI'] ?? '') !== '/ads.txt') return;
		$text = trim((string) ($this->settings['ads_txt'] ?? ''));
		if ($text === '') return;
		status_header(200);
		header('Content-Type: text/plain; charset=utf-8');
		echo esc_html($text);
		exit;
	}
}
