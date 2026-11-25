/* Setup wizard (templates/admin/setup.php): steps, suggested features per business type, task runner. */
(function () {
	'use strict';
	const root = document.querySelector('[data-setup]');
	if (!root || !window.AIMUI) return;
	const { api } = window.AIMUI;
	const $ = (s) => root.querySelector(s);
	const $$ = (s) => [...root.querySelectorAll(s)];

	const FEATURES = JSON.parse(root.dataset.features);
	const steps = $$('[data-step]');
	const next = $('[data-next]');
	const back = $('[data-back]');
	const dots = $$('.su-dots i');
	const startLabel = next.textContent;
	let cur = 0;
	let featsFor = '';

	const type = () => (root.querySelector('input[name=su-type]:checked') || {}).value || '';

	function show(i) {
		cur = i;
		steps.forEach((s) => { s.hidden = Number(s.dataset.step) !== i; });
		steps[i].classList.add('is-active');
		dots.forEach((d, k) => d.classList.toggle('is-on', k === Math.min(i, 4) - 1 || (i === 0 && k === 0)));
		back.hidden = i === 0 || i >= 4;
		$('[data-cancel]').hidden = i >= 4;
		next.hidden = i === 4;
		next.textContent = { 0: startLabel, 3: 'شروع راه‌اندازی', 5: 'رفتن به پنل' }[i] || 'ادامه';
		if (i === 3) renderFeatures();
		const first = steps[i].querySelector('input:not([type=hidden])');
		if (first && i > 0 && i < 4) setTimeout(() => first.focus(), 50);
	}

	// Step 3 lists the features suggested for the chosen type; a change of type resets them.
	function renderFeatures() {
		const t = type();
		$('[data-type-label]').textContent = root.querySelector('input[name=su-type]:checked').closest('label').querySelector('b').textContent;
		if (featsFor === t) return;
		featsFor = t;
		const box = $('[data-feats]');
		box.innerHTML = '';
		Object.entries(FEATURES[t]).forEach(([key, f]) => {
			const missing = f.needs === 'accounts' && !root.dataset.accounts;
			const row = document.createElement('label');
			row.className = 'su-feat' + (missing ? ' is-off' : '');
			row.innerHTML = '<div><b></b><small></small></div><input type="checkbox" class="su-switch">';
			row.querySelector('b').textContent = f.label;
			row.querySelector('small').textContent = missing ? 'ماژول فروش اکانت روی این سایت نصب نیست.' : f.text;
			const input = row.querySelector('input');
			input.dataset.feature = key;
			input.checked = f.on && !missing;
			input.disabled = missing || !!f.locked;
			box.appendChild(row);
		});
		syncFeatures();
	}
	// Accounts need the shop; the WooCommerce note shows while the shop is on.
	function syncFeatures() {
		const shop = $('[data-feature=shop]');
		const acc = $('[data-feature=accounts]');
		if (acc && !acc.closest('.is-off')) { acc.disabled = !shop.checked; if (!shop.checked) acc.checked = false; }
		$('[data-woo-note]').hidden = !shop.checked;
	}
	root.addEventListener('change', (e) => { if (e.target.matches('[data-feature]')) syncFeatures(); });

	function validate() {
		if (cur === 1 && !type()) return 'نوع کسب‌وکار را انتخاب کنید.';
		if (cur === 2 && !$('[data-info=name]').value.trim()) { $('[data-info=name]').focus(); return 'نام کسب‌وکار را وارد کنید.'; }
		if (cur === 2 && !$('[data-info=email]').checkValidity()) { $('[data-info=email]').focus(); return 'ایمیل معتبر نیست.'; }
		return '';
	}

	async function run() {
		show(4);
		const info = {};
		$$('[data-info]').forEach((el) => { if (el.type !== 'radio' || el.checked) info[el.dataset.info] = el.value; });
		const features = {};
		$$('[data-feature]').forEach((el) => { features[el.dataset.feature] = el.checked; });
		const list = $('[data-tasks]');
		const bar = $('[data-progress]');
		try {
			const { tasks } = await api('setup/start', { type: type(), info, features });
			list.innerHTML = '';
			const rows = tasks.map((t) => {
				const li = document.createElement('li');
				li.innerHTML = '<i class="ic-circle-line"></i><div><span></span><small></small></div>';
				li.querySelector('span').textContent = t.label;
				list.appendChild(li);
				return li;
			});
			let failed = 0;
			for (let k = 0; k < tasks.length; k++) {
				const li = rows[k];
				li.className = 'is-run';
				li.querySelector('i').className = '';
				try {
					const res = await api('setup/task', { id: tasks[k].id });
					li.className = 'is-ok';
					li.querySelector('i').className = 'ic-checkbox-circle-fill';
					li.querySelector('small').textContent = res.message || '';
				} catch (err) {
					failed++;
					li.className = 'is-err';
					li.querySelector('i').className = 'ic-error-warning-fill';
					li.querySelector('small').textContent = err.message;
					// Without WooCommerce its settings cannot be applied; keep going with the rest.
				}
				bar.style.width = Math.round(((k + 1) / tasks.length) * 100) + '%';
			}
			if (failed) {
				$('[data-run-title]').textContent = 'راه‌اندازی با چند خطا تمام شد';
				$('[data-run-lead]').textContent = 'موارد قرمز را از پنل دستی انجام دهید؛ بقیه تنظیم شد.';
				next.hidden = false;
				next.textContent = 'رفتن به پنل';
				cur = 5;
			} else {
				setTimeout(() => show(5), 500);
			}
		} catch (err) {
			window.AIMUI.toast(err.message, 'error');
			show(3);
		}
	}

	next.addEventListener('click', () => {
		if (cur === 5) { location.href = root.dataset.panel; return; }
		const error = validate();
		if (error) { window.AIMUI.toast(error, 'warning'); return; }
		if (cur === 3) { run(); return; }
		show(cur + 1);
	});
	back.addEventListener('click', () => show(Math.max(0, cur - 1)));
	// Picking a type moves on by itself.
	root.addEventListener('change', (e) => { if (e.target.name === 'su-type') setTimeout(() => show(2), 180); });
	document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && cur < 4) location.href = root.dataset.panel; });
})();
