document.addEventListener('click', (event) => {
  const target = event.target.closest('[data-confirm]');
  if (target && !window.confirm(target.dataset.confirm)) event.preventDefault();
});

const creativeType = document.querySelector('[data-creative-type]');
const refreshCreativePanels = () => {
  if (!creativeType) return;
  document.querySelectorAll('[data-creative-panel]').forEach((panel) => {
    panel.hidden = panel.dataset.creativePanel !== creativeType.value;
  });
};
if (creativeType) {
  creativeType.addEventListener('change', refreshCreativePanels);
  refreshCreativePanels();
}

document.querySelector('[data-media-single]')?.addEventListener('click', () => {
  const frame = wp.media({ title: 'Select campaign image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
  frame.on('select', () => {
    const image = frame.state().get('selection').first().toJSON();
    document.querySelector('[data-media-id]').value = image.id;
    document.querySelector('[data-media-preview]').innerHTML = `<img src="${image.sizes?.medium?.url || image.url}" alt="">`;
  });
  frame.open();
});

document.querySelector('[data-media-slider]')?.addEventListener('click', () => {
  const frame = wp.media({ title: 'Select slider images of the same size', button: { text: 'Use these images' }, multiple: true, library: { type: 'image' } });
  frame.on('select', () => {
    const images = frame.state().get('selection').toJSON();
	const dimensions = [...new Set(images.map((image) => `${image.width}x${image.height}`))];
	if (dimensions.length > 1) {
	  window.alert(`All slider images must have the same dimensions. Selected sizes: ${dimensions.join(', ')}`);
	  return;
	}
    document.querySelector('[data-slider-ids]').value = images.map((image) => image.id).join(',');
    document.querySelector('[data-slider-preview]').innerHTML = images.map((image) => `<img src="${image.sizes?.thumbnail?.url || image.url}" alt="">`).join('');
  });
  frame.open();
});
