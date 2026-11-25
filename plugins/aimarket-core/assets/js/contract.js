/* Order contract on checkout: draft window, consent, print (AIM_Contract). */
(function () {
	'use strict';
	const modal = document.querySelector('[data-contract-modal]');
	if (!modal || !window.AIMC) return;
	const body = modal.querySelector('[data-contract-body]');
	let opener = null;
	let draft = '';

	const field = (name) => { const el = document.querySelector('[name="billing_' + name + '"]'); return el ? el.value.trim() : ''; };
	const accept = () => document.querySelector('[data-contract-accept]');

	async function open(trigger) {
		opener = trigger;
		draft = '';
		modal.hidden = false;
		document.documentElement.style.overflow = 'hidden';
		body.innerHTML = '<div class="aimc-loading">در حال آماده‌سازی قرارداد همین سفارش…</div>';
		body.focus();
		try {
			const res = await fetch(AIMC.rest, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': AIMC.nonce },
				body: JSON.stringify({ buyer: { first_name: field('first_name'), last_name: field('last_name'), phone: field('phone'), email: field('email') } }),
			});
			const json = await res.json();
			if (!res.ok || !json.ok) throw new Error(json.message || 'قرارداد آماده نشد.');
			draft = json.html;
			body.innerHTML = json.summary + json.html;
			body.scrollTop = 0;
		} catch (err) {
			body.innerHTML = '<p class="aimc-error"></p>';
			body.firstChild.textContent = err.message + ' دوباره تلاش کنید.';
		}
	}
	function close() {
		modal.hidden = true;
		document.documentElement.style.overflow = '';
		if (opener) opener.focus();
	}
	// Print the document alone, on white paper, from a hidden frame.
	function print() {
		if (!draft) return;
		const frame = document.createElement('iframe');
		frame.style.cssText = 'position:fixed;width:0;height:0;border:0;opacity:0';
		document.body.appendChild(frame);
		const doc = frame.contentDocument;
		doc.open();
		doc.write('<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>قرارداد سفارش</title><link rel="stylesheet" href="' + AIMC.css + '"></head><body class="aimc-page">' + draft + '</body></html>');
		doc.close();
		frame.onload = () => { frame.contentWindow.focus(); frame.contentWindow.print(); setTimeout(() => frame.remove(), 1000); };
	}

	document.addEventListener('click', (e) => {
		const trigger = e.target.closest('[data-contract-open]');
		if (trigger) { e.preventDefault(); open(trigger); return; }
		if (!modal.hidden && (e.target.closest('[data-contract-close]') || e.target === modal)) close();
		if (e.target.closest('[data-contract-agree]') && draft) { // only after the draft was shown
			const box = accept();
			if (box) { box.checked = true; box.dispatchEvent(new Event('change', { bubbles: true })); }
			close();
		}
		if (e.target.closest('[data-contract-print]')) print();
	});
	document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) close(); });
	document.addEventListener('change', (e) => {
		if (e.target.matches('[data-contract-accept]')) e.target.closest('[data-contract-box]').classList.remove('is-error');
	});
	// Highlight the box when WooCommerce refuses the order without consent.
	if (window.jQuery) {
		// Without theme support the box sits inside #payment, which WooCommerce redraws on every
		// order-review refresh: keep the tick across the redraw.
		let ticked = false;
		window.jQuery(document.body).on('update_checkout', () => { const box = accept(); ticked = !!(box && box.checked); });
		window.jQuery(document.body).on('updated_checkout', () => { const box = accept(); if (box && ticked) box.checked = true; });
		window.jQuery(document.body).on('checkout_error', () => {
			const box = accept();
			if (box && !box.checked) {
				const wrap = box.closest('[data-contract-box]');
				wrap.classList.add('is-error');
				wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		});
	}
})();
