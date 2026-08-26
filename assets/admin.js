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

const videoSource = document.querySelector('[data-video-source]');
const refreshVideoPanels = () => {
	if (!videoSource) return;
	document.querySelectorAll('[data-video-source-panel]').forEach((panel) => {
		panel.hidden = panel.dataset.videoSourcePanel !== videoSource.value;
	});
};
if (videoSource) {
	videoSource.addEventListener('change', refreshVideoPanels);
	refreshVideoPanels();
}

const videoLimitInput = document.querySelector('[name="video_max_mb"]');
if (videoLimitInput && window.cotlasAdsAdmin) {
	videoLimitInput.max = '50';
	videoLimitInput.value = String(Math.round(window.cotlasAdsAdmin.videoMaxBytes / 1048576));
	const note = videoLimitInput.parentElement?.querySelector('small');
	if (note) note.textContent = 'Default: 20 MB. Hard maximum: 50 MB. Your server may enforce a smaller request limit.';
}

const imagePreviewItem = (url, removeType, id = '') => `<span class="media-preview-item"${removeType === 'slider' ? ` data-slider-image-id="${id}"` : ''}><img src="${url}" alt=""><button type="button" class="media-remove" ${removeType === 'slider' ? `data-remove-slider-image="${id}"` : `data-remove-image="${removeType}"`} aria-label="Remove image">×</button></span>`;

document.querySelector('[data-media-single]')?.addEventListener('click', () => {
  const frame = wp.media({ title: 'Select campaign image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
  frame.on('select', () => {
    const image = frame.state().get('selection').first().toJSON();
    document.querySelector('[data-media-id]').value = image.id;
    document.querySelector('[data-media-preview]').innerHTML = imagePreviewItem(image.url, 'image_id');
  });
  frame.open();
});

document.querySelectorAll('[data-pick-image]').forEach((button) => {
  button.addEventListener('click', () => {
	const fieldName = button.dataset.pickImage;
	const frame = wp.media({ title: 'Select image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
	frame.on('select', () => {
	  const image = frame.state().get('selection').first().toJSON();
	  document.querySelector(`[data-image-field="${fieldName}"]`).value = image.id;
	  document.querySelector(`[data-image-preview="${fieldName}"]`).innerHTML = imagePreviewItem(image.url, fieldName);
	});
	frame.open();
  });
});

const injectionRules = document.querySelector('[data-injection-rules]');
const renumberInjectionRules = () => {
  if (!injectionRules) return;
  [...injectionRules.querySelectorAll('[data-injection-rule]')].forEach((row, index) => {
	row.querySelectorAll('[name]').forEach((field) => { field.name = field.name.replace(/injection_rules\[\d+\]/, `injection_rules[${index}]`); });
  });
};
const injectionLocationLabels = {
  before: 'Before post content', after: 'After post content', paragraph: 'After paragraph number', feed: 'Before archive/feed item number',
  header: 'Visible header / after body opens', footer: 'Footer', sidebar_before: 'Before first sidebar widget', sidebar_after: 'After all sidebar widgets',
};
const refreshInjectionSummary = (row) => {
  const zone = row.querySelector('[name$="[zone]"]');
  const location = row.querySelector('[name$="[location]"]');
  const number = row.querySelector('[name$="[number]"]');
  row.querySelector('[data-rule-summary-zone]').textContent = zone?.selectedOptions?.[0]?.textContent || 'Disabled';
  row.querySelector('[data-rule-summary-location]').textContent = injectionLocationLabels[location?.value] || location?.value || '';
  row.querySelector('[data-rule-summary-target]').textContent = ['paragraph', 'feed'].includes(location?.value) ? `#${number?.value || 1}` : '—';
};
document.querySelector('[data-add-injection]')?.addEventListener('click', () => {
  const source = injectionRules?.querySelector('[data-injection-rule]:last-child');
  if (!source) return;
  const row = source.cloneNode(true);
  row.querySelectorAll('input').forEach((input) => { input.value = input.type === 'number' ? '2' : ''; });
  row.querySelectorAll('select').forEach((select) => { select.selectedIndex = 0; });
  row.open = true;
  injectionRules.appendChild(row); renumberInjectionRules(); refreshInjectionSummary(row);
});
injectionRules?.addEventListener('change', (event) => refreshInjectionSummary(event.target.closest('[data-injection-rule]')));
document.addEventListener('click', (event) => {
  const remove = event.target.closest('[data-remove-injection]');
  if (!remove) return;
  if (!window.confirm('Delete this injection? Save the rules to confirm the change.')) return;
  const rows = injectionRules?.querySelectorAll('[data-injection-rule]') || [];
  if (rows.length > 1) remove.closest('[data-injection-rule]').remove();
  else rows[0].querySelectorAll('input,select').forEach((field) => { if (field.tagName === 'SELECT') field.selectedIndex = 0; else field.value = ''; });
  renumberInjectionRules();
});

document.querySelector('[data-media-slider]')?.addEventListener('click', () => {
	const frame = wp.media({ title: 'Select carousel images of the same size', button: { text: 'Use selected images' }, multiple: 'add', library: { type: 'image' } });
	const storedIds = (document.querySelector('[data-slider-ids]')?.value || '').split(',').map(Number).filter(Boolean);
	frame.on('open', () => {
		const selection = frame.state().get('selection');
		storedIds.forEach((id) => {
			const attachment = wp.media.attachment(id);
			attachment.fetch();
			selection.add(attachment);
		});
	});
	frame.on('select', () => {
    const images = frame.state().get('selection').toJSON();
	const dimensions = [...new Set(images.map((image) => `${image.width}x${image.height}`))];
	if (dimensions.length > 1) {
	  window.alert(`All slider images must have the same dimensions. Selected sizes: ${dimensions.join(', ')}`);
	  return;
	}
		document.querySelector('[data-slider-ids]').value = images.map((image) => image.id).join(',');
		document.querySelector('[data-slider-preview]').innerHTML = images.map((image) => imagePreviewItem(image.sizes?.thumbnail?.url || image.url, 'slider', image.id)).join('');
		updateCarouselCount(images.length);
	});
	frame.open();
});

const carouselButton = document.querySelector('[data-media-slider]');
const carouselCount = carouselButton ? document.createElement('small') : null;
const updateCarouselCount = (count) => {
	if (carouselCount) carouselCount.textContent = `${count} image${count === 1 ? '' : 's'} currently selected`;
};
if (carouselButton && carouselCount) {
	carouselCount.dataset.sliderCount = '';
	carouselButton.closest('.media-button-row')?.after(carouselCount);
	updateCarouselCount((document.querySelector('[data-slider-ids]')?.value || '').split(',').filter(Boolean).length);
}

document.addEventListener('click', (event) => {
	const imageRemove = event.target.closest('[data-remove-image]');
	if (imageRemove) {
		const fieldName = imageRemove.dataset.removeImage;
		const field = fieldName === 'image_id' ? document.querySelector('[data-media-id]') : document.querySelector(`[data-image-field="${fieldName}"]`);
		if (field) field.value = '';
		imageRemove.closest('.media-preview-item')?.remove();
		return;
	}
	const sliderRemove = event.target.closest('[data-remove-slider-image]');
	if (!sliderRemove) return;
	const removedId = Number(sliderRemove.dataset.removeSliderImage);
	const field = document.querySelector('[data-slider-ids]');
	const ids = (field?.value || '').split(',').map(Number).filter((id) => id && id !== removedId);
	if (field) field.value = ids.join(',');
	sliderRemove.closest('.media-preview-item')?.remove();
	updateCarouselCount(ids.length);
});

document.querySelector('[data-media-video]')?.addEventListener('click', () => {
	if (!window.cotlasAdsAdmin?.videoEnabled) return;
	if (window.wp?.Uploader?.defaults?.multipart_params) {
		window.wp.Uploader.defaults.multipart_params.cotlas_ads_video_upload = window.cotlasAdsAdmin.videoUploadNonce;
	}
	const frame = wp.media({
		title: 'Select or upload an MP4 video ad',
		button: { text: 'Use this video' },
		multiple: false,
		library: { type: 'video/mp4' },
		uploader: { params: { cotlas_ads_video_upload: window.cotlasAdsAdmin.videoUploadNonce } },
	});
	frame.on('select', () => {
		const video = frame.state().get('selection').first().toJSON();
		if (video.mime !== 'video/mp4') {
			window.alert('Cotlas Ads accepts MP4 video files only.');
			return;
		}
		if (Number(video.filesizeInBytes || 0) > Number(window.cotlasAdsAdmin.videoMaxBytes)) {
			window.alert(`This video exceeds the configured ${Math.round(window.cotlasAdsAdmin.videoMaxBytes / 1048576)} MB limit.`);
			return;
		}
		document.querySelector('[data-video-id]').value = video.id;
		document.querySelector('[data-video-preview]').innerHTML = `<video style="display:block;max-width:100%;height:auto" src="${video.url}" controls muted playsinline></video>`;
	});
	frame.on('close', () => {
		if (window.wp?.Uploader?.defaults?.multipart_params) delete window.wp.Uploader.defaults.multipart_params.cotlas_ads_video_upload;
	});
	frame.open();
});

document.querySelector('[data-upload-video]')?.addEventListener('click', async (event) => {
	const button = event.currentTarget;
	const file = document.querySelector('[data-video-file]')?.files?.[0];
	if (!file) return window.alert('Select an MP4 file first.');
	if (!/\.mp4$/i.test(file.name)) return window.alert('Cotlas Ads accepts MP4 files only.');
	if (file.size > Number(window.cotlasAdsAdmin.videoMaxBytes)) return window.alert(`This video exceeds the configured ${Math.round(window.cotlasAdsAdmin.videoMaxBytes / 1048576)} MB limit.`);
	const body = new FormData();
	body.append('action', 'cotlas_ads_upload_video');
	body.append('nonce', window.cotlasAdsAdmin.videoUploadNonce);
	body.append('video', file);
	button.disabled = true;
	button.textContent = 'Uploading…';
	try {
		const response = await fetch(window.cotlasAdsAdmin.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' });
		const result = await response.json();
		if (!result.success) throw new Error(result.data?.message || 'Upload failed.');
		document.querySelector('[data-video-id]').value = result.data.id;
		document.querySelector('[data-video-preview]').innerHTML = `<video style="display:block;max-width:100%;height:auto" src="${result.data.url}" controls muted playsinline></video>`;
	} catch (error) {
		window.alert(error.message || 'Video upload failed.');
	} finally {
		button.disabled = false;
		button.textContent = 'Upload selected MP4';
	}
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
