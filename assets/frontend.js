document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-cotlas-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('.cotlas-slide')];
    if (slides.length < 2) {
      slider.querySelectorAll('button').forEach((button) => button.hidden = true);
      return;
    }
    let current = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
    const show = (next) => {
      slides[current].classList.remove('is-active');
      current = (next + slides.length) % slides.length;
      slides[current].classList.add('is-active');
    };
    const interval = Math.max(2, Number(slider.dataset.interval) || 5) * 1000;
    let timer = window.setInterval(() => show(current + 1), interval);
    const move = (amount) => {
      window.clearInterval(timer);
      show(current + amount);
      timer = window.setInterval(() => show(current + 1), interval);
    };
    slider.querySelector('.cotlas-slider-prev').addEventListener('click', (event) => { event.preventDefault(); move(-1); });
    slider.querySelector('.cotlas-slider-next').addEventListener('click', (event) => { event.preventDefault(); move(1); });
  });

  const overlay = document.querySelector('[data-cotlas-adblock-overlay]');
  if (overlay) {
    const dismissed = overlay.dataset.dismissible === '1' && window.sessionStorage.getItem('cotlas_adblock_dismissed') === '1';
    window.setTimeout(() => {
      const bait = document.querySelector('.cotlas-adblock-bait');
      const blocked = !bait || bait.offsetParent === null || bait.offsetHeight === 0 || bait.offsetWidth === 0 || window.getComputedStyle(bait).display === 'none';
      if (blocked && !dismissed) {
        overlay.hidden = false;
        document.documentElement.classList.add('cotlas-adblock-locked');
        overlay.querySelector('button')?.focus();
      }
    }, 900);
    overlay.querySelector('[data-adblock-reload]')?.addEventListener('click', () => window.location.reload());
    overlay.querySelector('[data-adblock-dismiss]')?.addEventListener('click', () => {
      window.sessionStorage.setItem('cotlas_adblock_dismissed', '1');
      overlay.hidden = true;
      document.documentElement.classList.remove('cotlas-adblock-locked');
    });
  }
});
