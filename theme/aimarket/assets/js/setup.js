/* Appearance → «راه‌اندازی هوش‌مارکت» (template-parts/setup-page.php): runs each step through admin-ajax. */
(function () {
        'use strict';
        const root = document.querySelector('[data-theme-setup]');
        if (!root || !window.AIMSETUP) return;
        const run = root.querySelector('[data-run]');
        const bar = root.querySelector('[data-progress]');
        const steps = ['core', 'woocommerce', 'accounts', 'reseller', 'elementor', 'configure', 'content'];

        async function step(id) {
                const body = new URLSearchParams({ action: 'aim_theme_setup', step: id, _ajax_nonce: AIMSETUP.nonce });
                const res = await fetch(AIMSETUP.ajax, { method: 'POST', credentials: 'same-origin', body }).catch(() => null);
                if (!res) throw new Error('اتصال به سایت برقرار نشد؛ اینترنت را بررسی کنید و دوباره تلاش کنید.');
                const text = await res.text();
                // admin-ajax answers -1 to an expired nonce (page left open for a long time).
                if (text.trim() === '-1') throw new Error('نشست منقضی شده است؛ صفحه را تازه کنید و دوباره تلاش کنید.');
                let json = {};
                try { json = JSON.parse(text); } catch (e) { /* PHP error or timeout */ }
                if (!json.success) throw new Error((json.data && json.data.message) || 'خطای ناشناخته؛ دوباره تلاش کنید.');
                return json.data.message;
        }

        // «افزونه‌های همراه قالب»: one-click sync of the plugins bundled in inc/bundled/ to the theme's versions.
        const sync = root.querySelector('[data-sync]');
        if (sync) {
                sync.addEventListener('click', async () => {
                        sync.disabled = true;
                        const wrap = root.querySelector('[data-bundled]');
                        const pill = root.querySelector('[data-bundled-pill]');
                        const msg = root.querySelector('[data-bundled-msg]');
                        pill.textContent = 'در حال همگام‌سازی…';
                        wrap.classList.remove('is-ok', 'is-stale', 'is-missing');
                        msg.hidden = true;
                        try {
                                msg.textContent = await step('bundled-sync');
                                msg.hidden = false;
                                pill.textContent = 'هم‌نسخهٔ قالب';
                                wrap.classList.add('is-ok');
                                setTimeout(() => window.location.reload(), 1800);
                        } catch (err) {
                                msg.textContent = err.message;
                                msg.hidden = false;
                                msg.setAttribute('data-err', '');
                                pill.textContent = 'خطا';
                                wrap.classList.add('is-stale');
                                sync.disabled = false;
                                sync.textContent = 'تلاش دوباره';
                        }
                });
        }

        run.addEventListener('click', async () => {
                run.disabled = true;
                let failed = 0;
                for (let k = 0; k < steps.length; k++) {
                        const li = root.querySelector('[data-req="' + steps[k] + '"]');
                        const msg = li.querySelector('[data-msg]');
                        const pill = li.querySelector('[data-pill]');
                        li.className = 'is-run';
                        pill.textContent = 'در حال انجام…';
                        try {
                                msg.textContent = await step(steps[k]);
                                li.className = 'is-active';
                                li.querySelector('i').className = 'ic-checkbox-circle-fill';
                                pill.textContent = 'انجام شد';
                                msg.removeAttribute('data-err');
                        } catch (err) {
                                failed++;
                                li.className = 'is-missing';
                                li.querySelector('i').className = 'ic-error-warning-fill';
                                pill.textContent = 'خطا';
                                msg.textContent = err.message;
                                msg.setAttribute('data-err', '');
                        }
                        bar.style.width = Math.round(((k + 1) / steps.length) * 100) + '%';
                }
                run.disabled = false;
                run.textContent = failed ? 'تلاش دوباره' : 'راه‌اندازی دوباره';
                root.querySelector('[data-go]').hidden = false;
                const alert = root.querySelector('.aimt-alert');
                if (alert && !failed) alert.remove();
        });
})();
