document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-cotlas-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('.cotlas-slide')];
	if (slides.length < 2) return;
    let current = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
    const show = (next) => {
	  const previous = current;
	  current = (next + slides.length) % slides.length;
	  slides[previous].classList.remove('is-active');
	  slides[previous].classList.add('is-leaving');
	  slides[current].classList.remove('is-leaving');
	  slides[current].classList.add('is-active');
	  window.setTimeout(() => slides[previous].classList.remove('is-leaving'), 500);
    };
    const interval = Math.max(2, Number(slider.dataset.interval) || 5) * 1000;
	window.setInterval(() => show(current + 1), interval);
  });

  const config = window.cotlasAdsEvents || {};
  const emit = (type, unit) => {
	const id = Number(unit.dataset.cotlasAd || 0);
	const zone = Number(unit.dataset.cotlasZone || 0);
	const name = unit.dataset.cotlasName || '';
	const eventName = type === 'impression' ? config.ga4Impression : config.ga4Click;
	if (config.ga4 && eventName && typeof window.gtag === 'function') {
	  window.gtag('event', eventName, { campaign_id: id, campaign_name: name, placement_id: zone });
	}
	if (config.matomo) {
	  window._paq = window._paq || [];
	  window._paq.push(['trackEvent', config.matomoCategory || 'Advertising', type, name || String(id), zone]);
	}
  };
  document.querySelectorAll('[data-cotlas-ad]').forEach((unit) => {
	if (unit.dataset.cotlasAdapterImpression !== '1') {
	  unit.dataset.cotlasAdapterImpression = '1';
	  emit('impression', unit);
	}
	unit.addEventListener('click', (event) => {
	  if (!event.target.closest('a')) return;
	  emit('click', unit);
	}, { capture: true });
  });

  const now = Date.now();
  document.querySelectorAll('[data-cotlas-sticky]').forEach((sticky) => {
	const cooldownMinutes = Math.max(0, Number(sticky.dataset.cooldown || 0));
	const key = `cotlas_sticky_${sticky.dataset.zone}_${cooldownMinutes}`;
	if (Number(localStorage.getItem(key) || 0) <= now) sticky.hidden = false;
	sticky.querySelector('.cotlas-overlay-close')?.addEventListener('click', () => {
	  if (cooldownMinutes > 0) localStorage.setItem(key, String(Date.now() + cooldownMinutes * 60000));
	  else localStorage.removeItem(key);
	  sticky.hidden = true;
	});
  });
  document.querySelectorAll('[data-cotlas-interstitial]').forEach((overlay) => {
	const zone = overlay.dataset.zone;
	const cooldownMinutes = Math.max(0, Number(overlay.dataset.cooldown || 0));
	const cooldownKey = `cotlas_interstitial_until_${zone}_${cooldownMinutes}`;
	const countKey = `cotlas_interstitial_clicks_${zone}`;
	let pendingUrl = '';
	document.addEventListener('click', (event) => {
	  const link = event.target.closest('a[href]');
	  if (!link || link.closest('[data-cotlas-interstitial],[data-cotlas-sticky],[data-cotlas-support-overlay],#wpadminbar') || link.hasAttribute('download') || link.target === '_blank' || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
	  const destination = new URL(link.href, window.location.href);
	  if (!['http:', 'https:'].includes(destination.protocol) || (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash)) return;
	  if (Number(localStorage.getItem(cooldownKey) || 0) > Date.now()) return;
	  const count = Number(localStorage.getItem(countKey) || 0) + 1;
	  localStorage.setItem(countKey, String(count));
	  if (count < Math.max(1, Number(overlay.dataset.clicks || 3))) return;
	  event.preventDefault(); pendingUrl = destination.href; localStorage.setItem(countKey, '0'); overlay.hidden = false; document.documentElement.classList.add('cotlas-support-locked');
	}, true);
	overlay.querySelector('.cotlas-overlay-close')?.addEventListener('click', () => {
	  if (cooldownMinutes > 0) localStorage.setItem(cooldownKey, String(Date.now() + cooldownMinutes * 60000));
	  else localStorage.removeItem(cooldownKey);
	  overlay.hidden = true; document.documentElement.classList.remove('cotlas-support-locked'); if (pendingUrl) window.location.href = pendingUrl;
	});
  });
});
