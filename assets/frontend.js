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
});
