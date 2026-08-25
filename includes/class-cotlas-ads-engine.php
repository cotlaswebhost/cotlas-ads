<?php
defined('ABSPATH') || exit;

final class Cotlas_Ads_Engine {
	private Cotlas_Ads_Repository $repository;
	private array $settings;

	public function __construct(Cotlas_Ads_Repository $repository) {
		$this->repository = $repository;
		$this->settings = wp_parse_args(get_option('cotlas_ads_settings', array()), array('header_code' => '', 'injections' => array(), 'ad_label' => 'Advertisement', 'adblock_enabled' => 0, 'adblock_dismissible' => 1, 'adblock_title' => 'Please disable your ad blocker', 'adblock_message' => 'Advertising supports our newsroom. Please disable your ad blocker and reload this page to continue.'));
		add_shortcode('cotlas_ad', array($this, 'shortcode'));
		add_action('wp_head', array($this, 'header_code'), 99);
		add_filter('the_content', array($this, 'inject_content'), 20);
		add_action('init', array($this, 'register_block'));
		add_action('init', array($this, 'ads_txt_route'));
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		add_action('wp_footer', array($this, 'adblock_markup'), 100);
	}

	public function register_assets(): void {
		wp_register_style('cotlas-ads-front', COTLAS_ADS_URL . 'assets/frontend.css', array(), COTLAS_ADS_VERSION);
		wp_register_script('cotlas-ads-front', COTLAS_ADS_URL . 'assets/frontend.js', array(), COTLAS_ADS_VERSION, true);
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
			if ($slides !== '') $body = '<div class="cotlas-slider" data-cotlas-slider data-interval="' . absint($ad['slider_interval']) . '">' . $slides . '<button type="button" class="cotlas-slider-prev" aria-label="Previous advertisement">‹</button><button type="button" class="cotlas-slider-next" aria-label="Next advertisement">›</button></div>';
		}
		if ($click_url && $ad['creative_type'] !== 'slider') {
			$body = '<a href="' . esc_url($click_url) . '" rel="sponsored noopener" target="_blank">' . $body . '</a>';
		}
		$pixel = add_query_arg(array('cotlas-ad-view' => (int) $ad['id'], 'zone' => $zone_id), home_url('/'));
		$outer_style = (int) $ad['canvas_width'] > 0 ? 'width:min(100%,' . absint($ad['canvas_width']) . 'px);' : '';
		$frame_style = (int) $ad['canvas_height'] > 0 ? 'height:' . absint($ad['canvas_height']) . 'px;' : '';
		$label = trim((string) $this->settings['ad_label']);
		$label_html = $label !== '' ? '<div class="cotlas-ad-label">' . esc_html($label) . '</div>' : '';
		return '<div class="cotlas-ad cotlas-type-' . esc_attr($ad['creative_type']) . '" style="' . esc_attr($outer_style) . '" data-cotlas-ad="' . absint($ad['id']) . '"><div class="cotlas-ad-frame" style="' . esc_attr($frame_style) . '">' . $body . '<img src="' . esc_url($pixel) . '" width="1" height="1" alt="" loading="eager" class="cotlas-pixel" /></div>' . $label_html . '</div>';
	}

	public function adblock_markup(): void {
		if (empty($this->settings['adblock_enabled'])) return;
		$dismissible = !empty($this->settings['adblock_dismissible']);
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
			window.setTimeout(function(){
				var bait=document.querySelector('.cotlas-block-test');
				var creatives=Array.prototype.slice.call(document.querySelectorAll('.cotlas-ad-image'));
				var realAdBlocked=creatives.length>0&&creatives.every(function(image){return isHidden(image)||(image.complete&&image.naturalWidth===0);});
				var dismissed=overlay.dataset.dismissible==='1'&&window.sessionStorage.getItem('cotlas_support_dismissed')==='1';
				if((isHidden(bait)||realAdBlocked)&&!dismissed){overlay.hidden=false;document.documentElement.classList.add('cotlas-support-locked');var button=overlay.querySelector('button');if(button)button.focus();}
			},1400);
			overlay.querySelector('[data-support-reload]').addEventListener('click',function(){window.location.reload();});
			var dismiss=overlay.querySelector('[data-support-dismiss]');if(dismiss)dismiss.addEventListener('click',function(){window.sessionStorage.setItem('cotlas_support_dismissed','1');overlay.hidden=true;document.documentElement.classList.remove('cotlas-support-locked');});
		})();
		</script>
		<?php
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
		foreach ((array) ($this->settings['injections'] ?? array()) as $rule) {
			if (empty($rule['zone']) || !in_array(get_post_type(), (array) ($rule['post_types'] ?? array('post')), true)) continue;
			$ad = $this->render_zone($rule['zone']);
			if (($rule['position'] ?? '') === 'before') $content = $ad . $content;
			elseif (($rule['position'] ?? '') === 'after') $content .= $ad;
			elseif (($rule['position'] ?? '') === 'paragraph') {
				$parts = explode('</p>', $content);
				$at = min(max(1, absint($rule['paragraph'] ?? 2)), count($parts));
				array_splice($parts, $at, 0, $ad);
				$content = implode('</p>', $parts);
			}
		}
		return $content;
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
