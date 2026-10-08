(function () {
	const script = document.currentScript;
	const version = script ? new URL(script.src, window.location.href).searchParams.get('ver') : '';
	window.cotlasAdvertisementProbe = version || true;
}());
