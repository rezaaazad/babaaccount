/* Runs on every wp-admin page; does something only when the page is framed by the store panel (html.aim-embed). */
(function () {
	'use strict';
	const html = document.documentElement;
	if (!html.classList.contains('aim-embed')) return;

	const adminRoot = location.pathname.replace(/[^/]*$/, ''); // ".../wp-admin/"
	const pathOf = (url) => url.pathname.slice(adminRoot.length) + url.search;

	// Tell the panel where we are, so its address bar and title follow along.
	// "Page title ‹ Site — WordPress" → "Page title"; fall back to the page heading.
	const heading = document.querySelector('.wrap h1, .woocommerce-layout__header-heading, h1');
	const title = document.title.split(/\s[‹|]\s/)[0].trim() || (heading ? heading.textContent.trim() : '');
	window.parent.postMessage({ aim: 'embed', path: pathOf(location), title }, location.origin);

	// Links: wp-admin pages stay in the frame; the store front and other sites open in a new tab;
	// full-screen editors (Elementor, Customizer) and the panel itself take over the whole window.
	const TOP = /[?&]action=elementor\b|customize\.php|[?&]page=aimarket\b|wp-login\.php/;
	document.addEventListener('click', (e) => {
		const a = e.target.closest('a[href]');
		if (!a || a.target === '_blank' || a.hasAttribute('download') || e.defaultPrevented) return;
		const url = new URL(a.href, location.href);
		if (!/^https?:$/.test(url.protocol)) return;
		if (url.origin === location.origin && url.pathname.startsWith(adminRoot)) {
			if (TOP.test(url.pathname + url.search)) a.target = '_top';
			return;
		}
		a.target = '_blank';
		a.rel = 'noopener';
	}, true);
})();
