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

document.querySelectorAll('[data-multiselect]').forEach((multi) => {
  const toggle = multi.querySelector('[data-multiselect-toggle]');
  const panel = multi.querySelector('[data-multiselect-panel]');
  const search = multi.querySelector('[data-multiselect-search]');
  const options = [...multi.querySelectorAll('[data-multiselect-option]')];
  const summary = multi.querySelector('[data-multiselect-summary]');
  const updateSummary = () => {
    const names = options.filter((option) => option.querySelector('input').checked).map((option) => option.querySelector('span').textContent.trim());
    summary.textContent = names.length ? (names.length <= 2 ? names.join(', ') : `${names.length} selected`) : 'Select…';
  };
  toggle.addEventListener('click', () => {
    const opening = panel.hidden;
    panel.hidden = !opening;
    toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    if (opening) search.focus();
  });
  search.addEventListener('input', () => {
    const query = search.value.trim().toLowerCase();
	const container = multi.querySelector('.cotlas-multiselect-options');
	options
	  .sort((a, b) => {
		const aLabel = a.dataset.label || '';
		const bLabel = b.dataset.label || '';
		const aPosition = query ? aLabel.indexOf(query) : 0;
		const bPosition = query ? bLabel.indexOf(query) : 0;
		if (aPosition !== bPosition) return aPosition - bPosition;
		return aLabel.localeCompare(bLabel);
	  })
	  .forEach((option) => {
		const matches = !query || (option.dataset.label || '').includes(query);
		option.hidden = !matches;
		option.classList.toggle('is-filtered-out', !matches);
		container.appendChild(option);
	  });
  });
  options.forEach((option) => option.querySelector('input').addEventListener('change', updateSummary));
  document.addEventListener('click', (event) => {
    if (!multi.contains(event.target)) {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
  updateSummary();
});
