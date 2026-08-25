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
});
