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
});
