/* Accounts module UI in the admin panel: «افزودن اکانت» counter and the encryption key card. Uses window.AIMUI from the core panel. */
(function () {
	'use strict';
	if (!window.AIMUI) return;

	// ---- «افزودن اکانت» modal: live count of pasted accounts (templates/admin/accounts.php) ----
	const countAccounts = (e) => {
		const form = e.target.closest('#add-accounts form');
		if (!form) return;
		const t = form.secrets.value.replace(/\r/g, '').trim();
		const mode = form.querySelector('[name=mode]:checked').value;
		const n = !t ? 0 : (mode === 'block' ? t.replace(/^[ \t]*-{3,}[ \t]*$/gm, '').split(/\n\s*\n/) : t.split('\n')).filter((x) => x.trim()).length;
		form.querySelector('[data-acc-count]').textContent = '(' + AIMUI.toFa(n) + ' اکانت)';
	};
	document.addEventListener('input', countAccounts);
	document.addEventListener('change', countAccounts);

	// ---- «کلید رمزنگاری اکانت‌ها» (settings → ورود و امنیت, AIM_Secret_Key) ----
	document.addEventListener('click', async (e) => {
		const copy = e.target.closest('[data-secret-copy]');
		if (copy) {
			try { await navigator.clipboard.writeText(document.querySelector('[data-secret-code]').textContent); AIMUI.toast('کپی شد.', 'success'); } catch (err) { AIMUI.toast('کپی نشد؛ متن را دستی انتخاب کنید.', 'warning'); }
			return;
		}
		const b = e.target.closest('[data-secret-key]');
		if (!b) return;
		b.disabled = true;
		const box = document.querySelector('[data-secret-result]');
		const show = (msg, code, note) => {
			box.querySelector('[data-secret-msg]').textContent = msg;
			box.querySelector('[data-secret-code]').textContent = code;
			box.querySelector('[data-secret-note]').textContent = note;
			box.hidden = false;
		};
		try {
			const res = await AIMUI.api('security/secret-key', {});
			AIMUI.toast(res.message, 'success');
			b.hidden = true;
			show(res.message, res.key, 'این کلید را جای امنی (جدا از نسخهٔ پشتیبان سایت) یادداشت کنید. دوباره نمایش داده نمی‌شود.');
		} catch (err) {
			if (err.data && err.data.line) {
				b.hidden = true;
				show(err.message, err.data.line, 'این خط را بلافاصله بعد از اولین خط (<?php) فایل wp-config.php بگذارید و ذخیره کنید. اکانت‌های فعلی پس از آن خودکار با کلید جدید رمز می‌شوند.');
			} else {
				AIMUI.toast(err.message, 'error');
				b.disabled = false;
			}
		}
	});
})();
