/* Storefront behaviour: colour mode, header search, drawer, rails, finder, steps, copy, newsletter. */
(function () {
	'use strict';
	const $ = (s, r) => (r || document).querySelector(s);
	const $$ = (s, r) => [...(r || document).querySelectorAll(s)];
	const FA = '۰۱۲۳۴۵۶۷۸۹';
	const toEn = (s) => String(s).replace(/[۰-۹]/g, (d) => FA.indexOf(d));

	function toast(msg) {
		const stack = $('[data-toasts]');
		if (!stack) return;
		const el = document.createElement('div');
		el.className = 'toast';
		el.innerHTML = '<i class="ic-checkbox-circle-fill"></i><span></span>';
		el.querySelector('span').textContent = msg;
		stack.appendChild(el);
		requestAnimationFrame(() => el.classList.add('is-in'));
		setTimeout(() => { el.classList.remove('is-in'); setTimeout(() => el.remove(), 250); }, 2800);
	}

	// ---- Colour mode (shared key with the panels) ----
	function setTheme(mode) {
		document.documentElement.dataset.theme = mode;
		try { localStorage.setItem('aim-theme', mode); } catch (e) { /* private mode */ }
		$$('[data-theme-toggle] i').forEach((i) => { i.className = mode === 'dark' ? 'ic-sun-line' : 'ic-moon-line'; });
	}
	document.addEventListener('click', (e) => {
		if (!e.target.closest('[data-theme-toggle]')) return;
		const cur = document.documentElement.dataset.theme;
		const dark = cur === 'dark' || (!cur && matchMedia('(prefers-color-scheme: dark)').matches);
		setTheme(dark ? 'light' : 'dark');
	});

	// ---- Announcement bar (remembered per browser) ----
	const ann = $('[data-announce]');
	if (ann) {
		try { if (localStorage.getItem('aim-announce') === ann.textContent.trim().length + '') ann.remove(); } catch (e) { /* ignore */ }
		ann.addEventListener('click', (e) => {
			if (!e.target.closest('[data-announce-close]')) return;
			try { localStorage.setItem('aim-announce', ann.textContent.trim().length + ''); } catch (err) { /* ignore */ }
			ann.remove();
		});
	}

	// ---- Header search: "/" focuses it; mobile button opens it ----
	const headSearch = $('[data-search] input[type=search]');
	document.addEventListener('keydown', (e) => {
		if (e.key === '/' && headSearch && !/input|textarea|select/i.test(document.activeElement.tagName)) {
			e.preventDefault();
			document.body.classList.add('search-open');
			headSearch.focus();
		}
		if (e.key === 'Escape') { document.body.classList.remove('search-open'); closeDrawer(); }
	});
	document.addEventListener('click', (e) => {
		if (!e.target.closest('[data-search-open]')) return;
		const hero = $('#hero-s');
		if (hero && hero.getBoundingClientRect().bottom > 0 && innerWidth < 992) { hero.focus(); return; }
		document.body.classList.toggle('search-open');
		if (document.body.classList.contains('search-open') && headSearch) {
			scrollTo({ top: 0, behavior: 'smooth' });
			setTimeout(() => headSearch.focus(), 50);
		}
	});

	// ---- Drawer ----
	const drawer = $('[data-drawer]');
	function closeDrawer() { if (drawer) { drawer.hidden = true; document.body.style.overflow = ''; } }
	document.addEventListener('click', (e) => {
		if (e.target.closest('[data-drawer-open]') && drawer) { drawer.hidden = false; document.body.style.overflow = 'hidden'; return; }
		if (e.target.closest('[data-drawer-close]') || e.target === drawer) closeDrawer();
		if (drawer && !drawer.hidden && e.target.closest('.dm-body a')) closeDrawer();
	});
	// Footer: back to top; link columns fold into an accordion on phones.
	document.addEventListener('click', (e) => {
		if (e.target.closest('[data-backtop]')) scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
	});
	const phone = matchMedia('(max-width: 767px)');
	const foldCols = () => $$('[data-ft-col]').forEach((d) => { d.open = !phone.matches; });
	foldCols();
	phone.addEventListener('change', foldCols);

	// One open group at a time in the drawer.
	$$('.dm-acc').forEach((d) => d.addEventListener('toggle', () => {
		if (d.open) $$('.dm-acc').forEach((o) => { if (o !== d) o.open = false; });
	}));

	// ---- Mega menu: opens on hover (with a short delay), click/tap or keyboard; tabs switch on hover/focus ----
	$$('[data-mega]').forEach((li) => {
		const trigger = $('.mega-trigger', li);
		const tabs = $$('[data-mega-tab]', li);
		let timer = 0;
		const set = (open) => {
			clearTimeout(timer);
			if (open) $$('[data-mega].is-open').forEach((o) => { if (o !== li) o.classList.remove('is-open'); });
			li.classList.toggle('is-open', open);
			trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
		};
		const select = (tab, focus) => {
			tabs.forEach((t) => {
				const on = t === tab;
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
				document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
			});
			if (focus) tab.focus();
		};
		li.addEventListener('pointerenter', (e) => {
			if (e.pointerType === 'touch') return;
			clearTimeout(timer); // cancels a pending close when the pointer comes back
			if (li.classList.contains('is-open')) return;
			timer = setTimeout(() => set(true), 90);
		});
		li.addEventListener('pointerleave', (e) => { if (e.pointerType === 'touch') return; clearTimeout(timer); timer = setTimeout(() => set(false), 160); });
		// Click on the label opens the panel (touch, keyboard); a second click follows the link.
		trigger.addEventListener('click', (e) => { if (!li.classList.contains('is-open')) { e.preventDefault(); set(true); } });
		trigger.addEventListener('keydown', (e) => {
			if (e.key === 'ArrowDown') { e.preventDefault(); set(true); (tabs.find((t) => t.tabIndex === 0) || tabs[0]).focus(); }
		});
		tabs.forEach((t, i) => {
			t.addEventListener('mouseenter', () => select(t));
			t.addEventListener('focus', () => select(t));
			t.addEventListener('click', () => select(t));
			t.addEventListener('keydown', (e) => {
				const step = { ArrowDown: 1, ArrowUp: -1 }[e.key];
				if (step) { e.preventDefault(); select(tabs[(i + step + tabs.length) % tabs.length], true); }
			});
		});
		li.addEventListener('keydown', (e) => { if (e.key === 'Escape' && li.classList.contains('is-open')) { set(false); trigger.focus(); } });
		li.addEventListener('focusout', (e) => { if (!li.contains(e.relatedTarget)) set(false); });
	});
	document.addEventListener('click', (e) => {
		if (!e.target.closest('[data-mega]')) $$('[data-mega].is-open').forEach((o) => { o.classList.remove('is-open'); $('.mega-trigger', o).setAttribute('aria-expanded', 'false'); });
	});

	// ---- Homepage sections: rails, use-case finder, purchase steps ----
	// Each runs once per element, so it works for the static page and for widgets
	// that Elementor's editor re-renders one by one (see the hook at the end).
	const once = (el, key) => { if (el.dataset[key]) return false; el.dataset[key] = '1'; return true; };

	function initRail(rail) {
		if (!once(rail, 'railReady')) return;
		const btns = $$('[data-rail="' + rail.id + '"]');
		if (!btns.length) return;
		const step = () => (rail.firstElementChild ? rail.firstElementChild.getBoundingClientRect().width + 18 : 300);
		const update = () => {
			// In RTL, scrollLeft runs from 0 (start, right edge) to negative values.
			const max = rail.scrollWidth - rail.clientWidth;
			const pos = Math.abs(rail.scrollLeft);
			btns.forEach((b) => { b.disabled = b.dataset.dir === 'prev' ? pos <= 2 : pos >= max - 2; });
		};
		btns.forEach((b) => b.addEventListener('click', () => {
			const dir = b.dataset.dir === 'next' ? -1 : 1; // RTL: "next" moves content to the left.
			rail.scrollBy({ left: dir * step(), behavior: 'smooth' });
		}));
		rail.addEventListener('scroll', update, { passive: true });
		addEventListener('resize', update);
		update();
	}

	function initFinder(finder) {
		if (!once(finder, 'finderReady')) return;
		finder.addEventListener('click', (e) => {
			const tab = e.target.closest('[data-use]');
			if (!tab) return;
			$$('[data-use]', finder).forEach((t) => { t.classList.toggle('is-active', t === tab); t.setAttribute('aria-selected', t === tab); });
			$$('[data-use-panel]', finder).forEach((p) => { p.hidden = p.dataset.usePanel !== tab.dataset.use; });
		});
	}

	function initSteps(steps) {
		if (!once(steps, 'stepsReady')) return;
		const btns = $$('[data-step]', steps);
		const panels = $$('[data-step-panel]', steps);
		const prev = $('[data-step-go="-1"]', steps);
		const next = $('[data-step-go="1"]', steps);
		const label = next.querySelector('span');
		let cur = 0;
		const go = (i) => {
			cur = Math.max(0, Math.min(btns.length - 1, i));
			btns.forEach((b, k) => { b.classList.toggle('is-active', k === cur); b.classList.toggle('is-done', k < cur); });
			panels.forEach((p, k) => { p.hidden = k !== cur; });
			prev.disabled = cur === 0;
			label.textContent = cur === btns.length - 1 ? steps.dataset.lastText : steps.dataset.nextText;
		};
		btns.forEach((b, k) => b.addEventListener('click', () => go(k)));
		prev.addEventListener('click', () => go(cur - 1));
		next.addEventListener('click', () => {
			if (cur === btns.length - 1) { if (steps.dataset.lastUrl) location.href = steps.dataset.lastUrl; return; }
			go(cur + 1);
		});
		go(0);
	}

	function initSections(root) {
		$$('.rail', root).forEach(initRail);
		$$('[data-finder]', root).forEach(initFinder);
		$$('[data-steps]', root).forEach(initSteps);
	}
	initSections(document);

	// Elementor (editor preview and live pages): set up each widget as it appears.
	const hookElementor = () => window.elementorFrontend.hooks.addAction('frontend/element_ready/global', ($el) => initSections($el[0]));
	if (window.elementorFrontend && window.elementorFrontend.hooks) hookElementor();
	else window.addEventListener('elementor/frontend/init', hookElementor);

	// ---- Copy discount code ----
	document.addEventListener('click', async (e) => {
		const b = e.target.closest('[data-copy-text]');
		if (!b) return;
		try {
			await navigator.clipboard.writeText(b.dataset.copyText);
			const label = b.querySelector('span');
			if (label) { const old = label.textContent; label.textContent = 'کپی شد'; setTimeout(() => { label.textContent = old; }, 1600); }
			toast('کد تخفیف کپی شد.');
		} catch (err) { /* clipboard blocked: the code is visible next to the button */ }
	});

	// ---- Newsletter ----
	const nl = $('[data-newsletter]');
	if (nl && window.AIMT) {
		const msg = $('[data-newsletter-msg]');
		nl.phone.addEventListener('input', () => { nl.phone.value = toEn(nl.phone.value).replace(/[^\d]/g, ''); });
		nl.addEventListener('submit', async (e) => {
			e.preventDefault();
			const btn = nl.querySelector('button');
			btn.disabled = true;
			try {
				const res = await fetch(AIMT.rest + 'newsletter', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ phone: nl.phone.value }) });
				const json = await res.json();
				msg.textContent = json.message || '';
				msg.classList.toggle('is-err', !res.ok);
				if (res.ok) nl.reset();
			} catch (err) {
				msg.textContent = 'ارتباط برقرار نشد. دوباره تلاش کنید.';
				msg.classList.add('is-err');
			} finally {
				btn.disabled = false;
			}
		});
	}

	if (document.documentElement.dataset.theme) setTheme(document.documentElement.dataset.theme);
})();

/* Archive + product page. */
(function () {
	'use strict';
	const $ = (s, r) => (r || document).querySelector(s);
	const $$ = (s, r) => [...(r || document).querySelectorAll(s)];
	const FA = '۰۱۲۳۴۵۶۷۸۹';
	const toEn = (s) => String(s).replace(/[۰-۹]/g, (d) => FA.indexOf(d));

	// ---- Shop sidebar: a column on desktop, a drawer on phones and tablets ----
	const flt = $('[data-filter]');
	if (flt) {
		const scrim = $('.arc-scrim');
		const open = () => { flt.classList.add('is-open'); scrim.hidden = false; document.body.style.overflow = 'hidden'; };
		const close = () => { flt.classList.remove('is-open'); scrim.hidden = true; document.body.style.overflow = ''; };
		document.addEventListener('click', (e) => {
			if (e.target.closest('[data-filter-open]')) { e.preventDefault(); open(); }
			else if (e.target.closest('[data-filter-close]')) close();
			const all = e.target.closest('[data-side-all]');
			if (all) {
				const show = all.getAttribute('aria-expanded') !== 'true';
				$$('.side-more', flt).forEach((m) => { m.hidden = !show; });
				all.setAttribute('aria-expanded', show ? 'true' : 'false');
			}
		});
		document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && flt.classList.contains('is-open')) close(); });
		// Money inputs: Persian digits in, thousands separators shown, plain digits submitted.
		$$('[data-money]', flt).forEach((inp) => {
			const fmt = () => { const d = toEn(inp.value).replace(/\D/g, ''); inp.value = d ? Number(d).toLocaleString('en-US') : ''; };
			inp.addEventListener('input', fmt);
			fmt();
		});
		flt.querySelector('form.side-filter').addEventListener('submit', () => {
			$$('[data-money]', flt).forEach((inp) => { inp.value = toEn(inp.value).replace(/\D/g, ''); if (!inp.value) inp.disabled = true; });
			$$('input[type=checkbox]', flt).forEach((c) => { if (!c.checked) c.disabled = true; });
		});
		// On mobile the centre bottom-bar button opens the filters on shop pages.
		const fab = $('.mbar-fab');
		if (fab) {
			fab.addEventListener('click', (e) => { e.preventDefault(); open(); });
			fab.querySelector('b').textContent = 'فیلتر';
			fab.querySelector('span').innerHTML = '<i class="ic-equalizer-line"></i>';
		}
	}

	// ---- Plan picker + sticky buy bar ----
	const form = $('[data-plans]');
	if (form) {
		const bb = { title: $('[data-bb-title]', form), price: $('[data-bb-price]', form), regular: $('[data-bb-regular]', form) };
		const variation = $('[data-variation]', form);
		const attrBox = $('[data-attr-fields]', form);
		const apply = () => {
			const r = $('input[name=aim_plan]:checked', form);
			if (!r) return;
			bb.title.textContent = r.dataset.title;
			bb.price.textContent = r.dataset.price;
			bb.regular.textContent = r.dataset.regular || '';
			if (variation) {
				variation.value = r.value;
				attrBox.innerHTML = '';
				const attrs = JSON.parse(r.dataset.attrs || '{}');
				Object.keys(attrs).forEach((k) => {
					const i = document.createElement('input');
					i.type = 'hidden'; i.name = k; i.value = attrs[k];
					attrBox.appendChild(i);
				});
			}
		};
		form.addEventListener('change', (e) => { if (e.target.name === 'aim_plan') apply(); });
		$('[data-buy]', form)?.addEventListener('click', () => { $('[data-buy-now]', form).value = '1'; });
		$('.buybar-cart', form)?.addEventListener('click', () => { $('[data-buy-now]', form).value = '0'; });
		form.addEventListener('submit', (e) => {
			if (!$('input[name=aim_plan]:checked', form)) { e.preventDefault(); form.scrollIntoView({ behavior: 'smooth' }); }
			$$('button[type=submit]', form).forEach((b) => { b.classList.add('is-busy'); });
		});
		apply();
	}

	// ---- Section tabs: highlight the section in view ----
	const tabs = $('[data-pd-tabs]');
	if (tabs && 'IntersectionObserver' in window) {
		const links = $$('a', tabs);
		const map = new Map(links.map((a) => [a.getAttribute('href').slice(1), a]));
		const io = new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				if (!en.isIntersecting) return;
				links.forEach((a) => a.classList.remove('is-active'));
				const a = map.get(en.target.id);
				if (a) { a.classList.add('is-active'); a.scrollIntoView({ block: 'nearest', inline: 'center' }); }
			});
		}, { rootMargin: '-45% 0px -50% 0px' });
		map.forEach((a, id) => { const s = document.getElementById(id); if (s) io.observe(s); });
	}

	// ---- Read more ----
	$$('[data-readmore]').forEach((box) => {
		const inner = box.firstElementChild;
		const btn = $('[data-readmore-btn]', box);
		if (inner.scrollHeight > 420) {
			box.classList.add('is-clamped');
			btn.hidden = false;
			btn.addEventListener('click', () => {
				const clamped = box.classList.toggle('is-clamped');
				btn.firstChild.textContent = clamped ? 'ادامهٔ مطلب' : 'بستن';
				if (clamped) box.scrollIntoView({ behavior: 'smooth' });
			});
		}
	});
})();

/* Cart + checkout. */
(function () {
	'use strict';
	const $ = (s, r) => (r || document).querySelector(s);
	const $$ = (s, r) => [...(r || document).querySelectorAll(s)];

	// Quantity stepper. Listeners live on document because WooCommerce swaps the
	// cart form via AJAX after each update.
	if ($('[data-cart-form]')) {
		let timer;
		const submit = (row) => {
			clearTimeout(timer);
			timer = setTimeout(() => {
				const form = $('[data-cart-form]');
				const btn = form && $('[data-update]', form);
				if (!btn) return;
				if (row) row.classList.add('is-busy');
				// WooCommerce keeps this disabled until it sees an input event.
				btn.disabled = false;
				btn.removeAttribute('aria-disabled');
				btn.click();
			}, 450);
		};
		document.addEventListener('click', (e) => {
			const b = e.target.closest('[data-cart-form] [data-qty] button');
			if (b) {
				const inp = b.parentElement.querySelector('input');
				const max = Number(inp.max) || Infinity;
				inp.value = Math.max(0, Math.min(max, (Number(inp.value) || 0) + Number(b.dataset.step)));
				submit(b.closest('.ci'));
				return;
			}
			const rm = e.target.closest('[data-cart-form] .ci-remove');
			if (rm) rm.closest('.ci').classList.add('is-busy');
		});
		document.addEventListener('change', (e) => { if (e.target.matches('[data-cart-form] [data-qty] input')) submit(e.target.closest('.ci')); });
		document.addEventListener('keydown', (e) => {
			// Enter in the coupon box applies the coupon instead of "update cart".
			if (e.key === 'Enter' && e.target.id === 'coupon_code') { e.preventDefault(); $('[name=apply_coupon]').click(); }
		});
		// After an AJAX update WooCommerce replaces the form and .cart_totals; refresh the mobile bar from it.
		if (window.jQuery) {
			window.jQuery(document.body).on('updated_wc_div updated_cart_totals', () => {
				const total = $('.cart_totals .is-total dd');
				const bar = $('.co-mbar b');
				if (total && bar) bar.innerHTML = total.innerHTML;
				if (!$('[data-cart-form]')) location.reload(); // Cart became empty: show the empty state + suggestions.
			});
		}
	}

	// Checkout: «روش انتخاب‌شده» line and the coupon box inside the order summary.
	if ($('form.woocommerce-checkout') && window.jQuery) {
		const jq = window.jQuery;
		const chosen = () => {
			const note = $('[data-cx-chosen]');
			const input = $('input[name=payment_method]:checked');
			if (!note) return;
			note.hidden = !input;
			if (input) note.querySelector('b').textContent = input.closest('li').querySelector('.cx-method-text b').textContent.trim();
		};
		document.addEventListener('change', (e) => { if (e.target.name === 'payment_method') chosen(); });
		jq(document.body).on('updated_checkout', chosen);
		chosen();

		const applyCoupon = async (row) => {
			const input = $('input', row);
			const code = input.value.trim();
			if (!code || !window.wc_checkout_params) { input.focus(); return; }
			const btn = $('[data-cx-coupon-apply]', row);
			btn.disabled = true;
			const body = new URLSearchParams({ security: wc_checkout_params.apply_coupon_nonce, coupon_code: code });
			try {
				const res = await fetch(wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon'), { method: 'POST', credentials: 'same-origin', body });
				const html = await res.text();
				const holder = $('.woocommerce-notices-wrapper') || $('form.woocommerce-checkout');
				const box = document.createElement('div');
				box.innerHTML = html;
				holder.prepend(box);
				jq(document.body).trigger('update_checkout', { update_shipping_method: false });
			} finally {
				btn.disabled = false;
			}
		};
		document.addEventListener('click', (e) => {
			const apply = e.target.closest('[data-cx-coupon-apply]');
			if (apply) applyCoupon(apply.closest('[data-cx-coupon]'));
		});
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Enter' && e.target.closest('[data-cx-coupon]')) { e.preventDefault(); applyCoupon(e.target.closest('[data-cx-coupon]')); }
		});
	}
})();

/* Mini-cart drawer + AJAX add to cart with a fly-to-cart animation. */
(function () {
	'use strict';
	const $ = (s, r) => (r || document).querySelector(s);
	const $$ = (s, r) => [...(r || document).querySelectorAll(s)];
	const drawer = $('[data-minicart]');
	if (!drawer || !window.AIMT) return;
	const body = $('[data-mc-body]', drawer);
	const flash = $('[data-mc-flash]', drawer);
	const FA = '۰۱۲۳۴۵۶۷۸۹';
	const toFa = (n) => String(n).replace(/\d/g, (d) => FA[d]);
	const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
	let loaded = false;

	async function call(path, data) {
		const res = await fetch(AIMT.rest + path, {
			method: data ? 'POST' : 'GET',
			credentials: 'same-origin',
			headers: Object.assign({ 'Content-Type': 'application/json' }, AIMT.nonce ? { 'X-WP-Nonce': AIMT.nonce } : {}),
			body: data ? JSON.stringify(data) : undefined,
		});
		let json = {};
		try { json = await res.json(); } catch (e) { /* non-JSON */ }
		return json;
	}

	function render(json) {
		if (typeof json.html === 'string') body.innerHTML = json.html;
		loaded = true;
		const n = Number(json.count || 0);
		$$('.aim-cart-count').forEach((el) => { el.textContent = toFa(n); });
		$$('.aim-cart-count-m').forEach((el) => { el.textContent = toFa(n); el.hidden = !n; });
		const c = $('[data-mc-count]', drawer);
		if (c) c.textContent = n ? '(' + toFa(n) + ')' : '';
	}

	function say(msg, tone) {
		if (!msg) { flash.hidden = true; return; }
		flash.className = 'mc-flash is-' + (tone || 'ok');
		flash.innerHTML = '<i class="ic-' + (tone === 'err' ? 'error-warning-fill' : 'checkbox-circle-fill') + '"></i><span></span>';
		flash.querySelector('span').textContent = msg;
		flash.hidden = false;
		clearTimeout(say.t);
		say.t = setTimeout(() => { flash.hidden = true; }, 3500);
	}

	function open() {
		drawer.hidden = false;
		document.body.style.overflow = 'hidden';
		void drawer.offsetWidth; // apply the closed state first so the slide-in transition runs
		drawer.classList.add('is-open');
		if (!loaded) call('minicart').then(render);
		setTimeout(() => { const b = $('[data-minicart-close]', drawer); if (b) b.focus(); }, 60);
	}
	function close() {
		drawer.classList.remove('is-open');
		document.body.style.overflow = '';
		setTimeout(() => { drawer.hidden = true; }, 220);
	}

	document.addEventListener('click', (e) => {
		const o = e.target.closest('[data-minicart-open]');
		// wp_localize_script sends values as strings, so compare with '1' rather than relying on truthiness.
		if (o && String(AIMT.noDrawer) !== '1') { e.preventDefault(); open(); return; }
		if (e.target.closest('[data-minicart-close]') || e.target === drawer) close();
	});
	document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !drawer.hidden) close(); });

	// Quantity / remove inside the drawer.
	drawer.addEventListener('click', async (e) => {
		const b = e.target.closest('[data-mc-qty]');
		if (!b) return;
		const item = b.closest('[data-key]');
		item.classList.add('is-busy');
		const json = await call('minicart/update', { key: item.dataset.key, quantity: Number(b.dataset.mcQty) });
		render(json);
		if (json.message) say(json.message, json.ok === false ? 'err' : 'ok');
	});

	// Fly a copy of the product tile from the button to the header cart icon.
	function fly(fromEl) {
		const target = [...$$('.sh-cart, .mbar-cart')].find((el) => el.offsetParent !== null);
		if (!fromEl || !target || reduce) return Promise.resolve();
		const src = $('.pd-logo') || fromEl;
		const a = src.getBoundingClientRect();
		const b = target.getBoundingClientRect();
		const ghost = src.cloneNode(true);
		ghost.classList.add('fly-ghost');
		Object.assign(ghost.style, { left: a.left + 'px', top: a.top + 'px', width: a.width + 'px', height: a.height + 'px' });
		document.body.appendChild(ghost);
		const dx = b.left + b.width / 2 - (a.left + a.width / 2);
		const dy = b.top + b.height / 2 - (a.top + a.height / 2);
		const anim = ghost.animate([
			{ transform: 'translate(0,0) scale(1)', opacity: 1 },
			{ transform: `translate(${dx * 0.5}px, ${dy * 0.5 - 80}px) scale(.6)`, opacity: 1, offset: 0.55 },
			{ transform: `translate(${dx}px, ${dy}px) scale(.15)`, opacity: 0.4 },
		], { duration: 750, easing: 'cubic-bezier(.5,.05,.4,1)' });
		// Never wait on the animation itself: hidden tabs and some browsers pause it, so cap it at 900ms.
		const done = Promise.race([anim.finished.catch(() => {}), new Promise((r) => setTimeout(r, 900))]);
		return done.then(() => {
			ghost.remove();
			target.classList.remove('is-bump');
			void target.offsetWidth; // restart the bump animation
			target.classList.add('is-bump');
		});
	}

	// Product page: the cart button adds without leaving the page.
	const form = $('[data-plans]');
	const cartBtn = form && $('.buybar-cart', form);
	if (cartBtn) {
		cartBtn.addEventListener('click', async (e) => {
			e.preventDefault();
			const variation = form.querySelector('[name=variation_id]');
			const payload = {
				product_id: Number(form.querySelector('[name="add-to-cart"]').value),
				variation_id: variation ? Number(variation.value) : 0,
				quantity: 1,
			};
			cartBtn.disabled = true;
			cartBtn.classList.add('is-loading');
			const [json] = await Promise.all([call('minicart/add', payload), fly(cartBtn)]);
			cartBtn.disabled = false;
			cartBtn.classList.remove('is-loading');
			render(json);
			if (json.ok === false) {
				open();
				say(json.message || 'افزودن به سبد ناموفق بود.', 'err');
				return;
			}
			cartBtn.classList.add('is-added');
			setTimeout(() => cartBtn.classList.remove('is-added'), 1400);
			open();
			say(json.message, 'ok');
		});
	}
})();

/* «خبرم کن» (stock / sale alerts) and the thank-you coupon copy button. */
(function () {
	'use strict';
	document.addEventListener('click', async (e) => {
		const b = e.target.closest('[data-alert]');
		if (!b || b.disabled) return;
		b.disabled = true;
		try {
			const res = await fetch(AIMT.rest + 'alerts/subscribe', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': AIMT.nonce },
				body: JSON.stringify({ product_id: Number(b.dataset.product), type: b.dataset.alert }),
			});
			const json = await res.json().catch(() => ({}));
			if (res.status === 401 && json.login) { location.href = json.login; return; }
			if (!res.ok || json.ok === false) throw new Error(json.message || 'ثبت نشد؛ دوباره تلاش کنید.');
			b.classList.add('is-done');
			b.querySelector('i').className = 'ic-check-line';
			b.querySelector('span').textContent = 'در فهرست اطلاع‌رسانی هستید';
			b.title = json.message;
		} catch (err) {
			b.disabled = false;
			b.querySelector('span').textContent = err.message;
		}
	});

	document.addEventListener('click', async (e) => {
		const c = e.target.closest('[data-copy-code]');
		if (!c) return;
		try { await navigator.clipboard.writeText(c.dataset.copyCode); c.classList.add('is-copied'); setTimeout(() => c.classList.remove('is-copied'), 1600); } catch (err) { /* user can select the code by hand */ }
	});
})();
