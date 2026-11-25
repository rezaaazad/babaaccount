/* Login flow: phone → one-time code, with password as an alternative. */
(function () {
	'use strict';
	const card = document.querySelector('[data-auth]');
	if (!card) return;

	const { api, toEn, toFa, toast } = window.AIMUI;
	const steps = card.querySelectorAll('[data-step]');
	const state = { phone: '', length: 5, timer: null };

	function show(name) {
		steps.forEach((s) => s.classList.toggle('is-active', s.dataset.step === name));
		const active = card.querySelector('[data-step="' + name + '"]');
		setError(active, '');
		const focus = active.querySelector(name === 'code' ? '.otp-boxes input' : 'input:not([type=hidden])');
		if (focus) setTimeout(() => focus.focus(), 50);
	}

	function setError(form, msg) {
		const el = form.querySelector('[data-error]');
		if (el) el.textContent = msg || '';
		form.querySelectorAll('.field').forEach((f) => f.classList.toggle('is-invalid', !!msg));
	}

	function busy(form, on) {
		const btn = form.querySelector('[type=submit]');
		btn.disabled = on;
		btn.classList.toggle('is-loading', on);
	}

	function normalize(v) {
		v = toEn(v).replace(/\D/g, '');
		if (v.startsWith('0098')) v = '0' + v.slice(4);
		else if (v.startsWith('98') && v.length === 12) v = '0' + v.slice(2);
		else if (v.startsWith('9') && v.length === 10) v = '0' + v;
		return /^09\d{9}$/.test(v) ? v : '';
	}

	// ---- OTP boxes: auto-advance, backspace, paste, SMS autofill ----
	function buildBoxes(len) {
		const wrap = card.querySelector('[data-otp]');
		wrap.innerHTML = '';
		for (let i = 0; i < len; i++) {
			const inp = document.createElement('input');
			inp.className = 'otp-box';
			inp.inputMode = 'numeric';
			// No maxLength: SMS autofill and paste insert the whole code into one box; the input handler spreads it.
			inp.setAttribute('aria-label', 'رقم ' + (i + 1));
			if (i === 0) inp.autocomplete = 'one-time-code';
			wrap.appendChild(inp);
		}
		const boxes = [...wrap.children];
		const sync = () => {
			const code = boxes.map((b) => b.value).join('');
			card.querySelector('[data-step=code] [name=code]').value = code;
			boxes.forEach((b) => b.classList.toggle('is-filled', !!b.value));
			if (code.length === len) card.querySelector('[data-step=code]').requestSubmit();
		};
		const fill = (from, digits) => {
			digits.split('').forEach((d, k) => { if (boxes[from + k]) boxes[from + k].value = d; });
			const next = boxes[Math.min(from + digits.length, len - 1)];
			next.focus();
			sync();
		};
		boxes.forEach((b, i) => {
			b.addEventListener('input', () => {
				const d = toEn(b.value).replace(/\D/g, '');
				b.value = '';
				if (d) fill(i, d.slice(0, len - i)); else sync();
			});
			b.addEventListener('keydown', (e) => {
				if (e.key === 'Backspace' && !b.value && i > 0) { boxes[i - 1].value = ''; boxes[i - 1].focus(); sync(); e.preventDefault(); }
				if (e.key === 'ArrowLeft' && i < len - 1) boxes[i + 1].focus();
				if (e.key === 'ArrowRight' && i > 0) boxes[i - 1].focus();
			});
			b.addEventListener('paste', (e) => {
				e.preventDefault();
				const d = toEn((e.clipboardData || window.clipboardData).getData('text')).replace(/\D/g, '');
				if (d) fill(0, d.slice(0, len));
			});
			b.addEventListener('focus', () => b.select());
		});
	}

	function clearBoxes() {
		card.querySelectorAll('.otp-box').forEach((b) => { b.value = ''; b.classList.remove('is-filled'); });
		card.querySelector('[data-step=code] [name=code]').value = '';
	}

	function startTimer(sec) {
		const wrap = card.querySelector('[data-timer-wrap]');
		const label = card.querySelector('[data-timer]');
		const resend = card.querySelector('[data-resend]');
		clearInterval(state.timer);
		wrap.hidden = false;
		resend.hidden = true;
		let left = sec;
		const tick = () => {
			const m = String(Math.floor(left / 60)).padStart(2, '0');
			const s = String(left % 60).padStart(2, '0');
			label.textContent = toFa(m + ':' + s);
			if (left-- <= 0) { clearInterval(state.timer); wrap.hidden = true; resend.hidden = false; }
		};
		tick();
		state.timer = setInterval(tick, 1000);
	}

	async function sendCode(form) {
		busy(form, true);
		try {
			const res = await api('auth/send', { phone: state.phone });
			state.length = res.length || 5;
			card.querySelector('[data-phone-label]').textContent = toFa(state.phone);
			buildBoxes(state.length);
			show('code');
			startTimer(res.wait || 60);
			toast(res.message, 'success');
		} catch (err) {
			setError(form, err.message);
			if (err.data && err.data.wait && form.dataset.step === 'code') startTimer(err.data.wait);
		} finally {
			busy(form, false);
		}
	}

	// ---- Step handlers ----
	card.querySelector('[data-step=phone]').addEventListener('submit', (e) => {
		e.preventDefault();
		const form = e.currentTarget;
		const phone = normalize(form.phone.value);
		if (!phone) return setError(form, 'شمارهٔ موبایل معتبر نیست. نمونهٔ درست: ' + toFa('09123456789'));
		state.phone = phone;
		sendCode(form);
	});

	card.querySelector('[data-step=code]').addEventListener('submit', async (e) => {
		e.preventDefault();
		const form = e.currentTarget;
		const code = form.code.value;
		if (code.length !== state.length) return setError(form, 'کد ' + toFa(state.length) + ' رقمی را کامل وارد کنید.');
		if (form.classList.contains('is-busy')) return;
		form.classList.add('is-busy');
		busy(form, true);
		try {
			const res = await api('auth/verify', { phone: state.phone, code, redirect: card.dataset.redirect });
			toast(res.message, 'success');
			window.location.href = res.redirect;
		} catch (err) {
			setError(form, err.message);
			clearBoxes();
			form.querySelector('.otp-box').focus();
			busy(form, false);
		} finally {
			form.classList.remove('is-busy');
		}
	});

	card.querySelector('[data-resend]').addEventListener('click', () => {
		clearBoxes();
		sendCode(card.querySelector('[data-step=code]'));
	});

	const pass = card.querySelector('[data-step=password]');
	if (pass) {
		pass.addEventListener('submit', async (e) => {
			e.preventDefault();
			const phone = normalize(pass.phone.value);
			if (!phone) return setError(pass, 'شمارهٔ موبایل معتبر نیست.');
			if (!pass.password.value) return setError(pass, 'گذرواژه را وارد کنید.');
			busy(pass, true);
			try {
				const res = await api('auth/password', { phone, password: pass.password.value, redirect: card.dataset.redirect });
				window.location.href = res.redirect;
			} catch (err) {
				setError(pass, err.message);
				busy(pass, false);
			}
		});
		pass.querySelector('[data-toggle-pass]').addEventListener('click', (e) => {
			const inp = pass.password;
			inp.type = inp.type === 'password' ? 'text' : 'password';
			e.currentTarget.innerHTML = '<i class="ic-' + (inp.type === 'password' ? 'eye-line' : 'eye-off-line') + '"></i>';
		});
	}

	card.addEventListener('click', (e) => {
		const go = e.target.closest('[data-go]');
		if (!go) return;
		if (go.dataset.go === 'password' && state.phone) pass.phone.value = state.phone;
		if (go.dataset.go === 'phone') clearInterval(state.timer);
		show(go.dataset.go);
	});

	// Live-normalize Persian digits in phone inputs.
	card.querySelectorAll('input[type=tel]').forEach((inp) => {
		inp.addEventListener('input', () => { inp.value = toEn(inp.value).replace(/[^\d+]/g, ''); });
	});
})();
