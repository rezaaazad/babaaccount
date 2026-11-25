/* Shared UI for plugin pages: REST helper, toasts, theme, sidebar, modals, tabs, AJAX forms. */
(function () {
        'use strict';
        const cfg = window.AIM || {};

        const FA = '۰۱۲۳۴۵۶۷۸۹';
        const toEn = (s) => String(s).replace(/[۰-۹]/g, (d) => FA.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
        const toFa = (s) => String(s).replace(/\d/g, (d) => FA[d]);

        async function api(path, body, method) {
                const res = await fetch(cfg.rest + path, {
                        method: method || (body ? 'POST' : 'GET'),
                        credentials: 'same-origin',
                        headers: Object.assign({ 'Content-Type': 'application/json' }, cfg.nonce ? { 'X-WP-Nonce': cfg.nonce } : {}),
                        body: body ? JSON.stringify(body) : undefined,
                });
                let json = {};
                try { json = await res.json(); } catch (e) { /* non-JSON */ }
                if (!res.ok || json.ok === false) {
                        const err = new Error(json.message || 'خطایی رخ داد. دوباره تلاش کنید.');
                        err.data = json;
                        throw err;
                }
                return json;
        }

        function toast(message, tone) {
                const stack = document.querySelector('[data-toasts]');
                if (!stack || !message) return;
                const el = document.createElement('div');
                el.className = 'toast toast-' + (tone || 'info');
                const icon = { success: 'checkbox-circle-fill', error: 'error-warning-fill', warning: 'alert-fill', info: 'information-fill' }[tone || 'info'];
                el.innerHTML = '<i class="ic-' + icon + '"></i><span></span>';
                el.querySelector('span').textContent = message;
                stack.appendChild(el);
                requestAnimationFrame(() => el.classList.add('is-in'));
                setTimeout(() => { el.classList.remove('is-in'); setTimeout(() => el.remove(), 250); }, 4200);
        }

        // ---- Theme (light/dark), persisted per browser ----
        // The admin panel remembers its own mode, separate from the shop and customer area (see aim_color_mode_script()).
        const THEME_KEY = cfg.bundle === 'wp-admin' ? 'aim-theme-admin' : 'aim-theme';
        // Only the toggle saves the choice; applying the default (or the current mode after navigation) must not pin it.
        function setTheme(mode, save = true) {
                document.documentElement.dataset.theme = mode;
                if (save) { try { localStorage.setItem(THEME_KEY, mode); } catch (e) { /* private mode */ } }
                document.querySelectorAll('[data-theme-toggle] i').forEach((i) => { i.className = mode === 'dark' ? 'ic-sun-line' : 'ic-moon-line'; });
        }
        document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-theme-toggle]')) return;
                const dark = document.documentElement.dataset.theme === 'dark' ||
                        (!document.documentElement.dataset.theme && matchMedia('(prefers-color-scheme: dark)').matches);
                setTheme(dark ? 'light' : 'dark');
        });
        if (document.documentElement.dataset.theme) setTheme(document.documentElement.dataset.theme, false);

        // ---- Sidebar: collapse on desktop, drawer on mobile, sub-menus ----
        document.addEventListener('click', (e) => {
                if (e.target.closest('[data-sidebar-toggle]')) {
                        if (matchMedia('(max-width: 991px)').matches) document.body.classList.toggle('sidebar-open');
                        else {
                                document.body.classList.toggle('sidebar-mini');
                                try { localStorage.setItem('aim-sidebar-mini', document.body.classList.contains('sidebar-mini') ? '1' : ''); } catch (err) { /* ignore */ }
                        }
                }
                if (e.target.closest('[data-sidebar-close]')) document.body.classList.remove('sidebar-open');
                const parent = e.target.closest('[data-submenu]');
                if (parent) {
                        e.preventDefault();
                        parent.parentElement.classList.toggle('is-open');
                        parent.setAttribute('aria-expanded', parent.parentElement.classList.contains('is-open'));
                }
        });
        try { if (localStorage.getItem('aim-sidebar-mini')) document.body.classList.add('sidebar-mini'); } catch (e) { /* ignore */ }

        // ---- Dropdowns ----
        document.addEventListener('click', (e) => {
                const t = e.target.closest('[data-dropdown]');
                document.querySelectorAll('.dropdown.is-open').forEach((d) => { if (!t || d !== t.parentElement) d.classList.remove('is-open'); });
                if (t) t.parentElement.classList.toggle('is-open');
        });

        // ---- Fullscreen ----
        document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-fullscreen]')) return;
                if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen?.();
        });

        // ---- Modals ----
        function openModal(id) {
                const m = document.getElementById(id);
                if (!m) return;
                m.hidden = false;
                requestAnimationFrame(() => m.classList.add('is-open'));
                document.body.classList.add('modal-open');
                const f = m.querySelector('input:not([type=hidden]),textarea,select');
                if (f) setTimeout(() => f.focus(), 120);
        }
        function closeModal(m) {
                m.classList.remove('is-open');
                document.body.classList.remove('modal-open');
                setTimeout(() => { m.hidden = true; }, 180);
        }
        document.addEventListener('click', (e) => {
                const o = e.target.closest('[data-modal-open]');
                if (o) { e.preventDefault(); openModal(o.dataset.modalOpen); return; }
                const c = e.target.closest('[data-modal-close]');
                if (c) closeModal(c.closest('.modal'));
                else if (e.target.classList.contains('modal')) closeModal(e.target);
        });
        document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                const m = document.querySelector('.modal.is-open');
                if (m) closeModal(m);
                document.body.classList.remove('sidebar-open');
        });

        // ---- Tabs (in-page) ----
        document.addEventListener('click', (e) => {
                const tab = e.target.closest('[data-tab]');
                if (!tab) return;
                const group = tab.closest('[data-tabs]');
                group.querySelectorAll('[data-tab]').forEach((t) => { t.classList.toggle('is-active', t === tab); t.setAttribute('aria-selected', t === tab); });
                const root = tab.closest('[data-tabs-root]') || document;
                root.querySelectorAll('[data-tab-panel]').forEach((p) => { p.hidden = p.dataset.tabPanel !== tab.dataset.tab; });
                try { history.replaceState(null, '', '#' + tab.dataset.tab); } catch (err) { /* ignore */ }
        });
        function openHashTab() {
                if (!location.hash) return;
                const t = document.querySelector('[data-tab="' + CSS.escape(location.hash.slice(1)) + '"]');
                if (t) t.click();
        }

        // ---- AJAX forms: <form data-api="path" data-method="POST" data-reload> ----
        document.addEventListener('submit', async (e) => {
                const form = e.target.closest('form[data-api]');
                if (!form) return;
                e.preventDefault();
                if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
                const data = {};
                new FormData(form).forEach((v, k) => {
                        if (k.endsWith('[]')) { (data[k.slice(0, -2)] = data[k.slice(0, -2)] || []).push(v); } else data[k] = v;
                });
                form.querySelectorAll('input[type=checkbox][data-bool]').forEach((c) => { data[c.name] = c.checked ? 1 : 0; });
                const btn = form.querySelector('[type=submit]');
                if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }
                try {
                        const res = await api(form.dataset.api, data, form.dataset.method || 'POST');
                        toast(res.message || 'ذخیره شد.', 'success');
                        if (form.dataset.reload !== undefined) setTimeout(reloadPage, 600);
                        if (res.redirect) setTimeout(() => navigate(res.redirect), 600);
                        form.dispatchEvent(new CustomEvent('aim:done', { detail: res }));
                } catch (err) {
                        toast(err.message, 'error');
                } finally {
                        if (btn) { btn.disabled = false; btn.classList.remove('is-loading'); }
                }
        });

        // ---- Row actions: <button data-action="path" data-confirm="..." data-body='{}'> ----
        document.addEventListener('click', async (e) => {
                const b = e.target.closest('[data-action]');
                if (!b) return;
                e.preventDefault();
                if (b.dataset.confirm && !window.confirm(b.dataset.confirm)) return;
                b.disabled = true;
                try {
                        const res = await api(b.dataset.action, JSON.parse(b.dataset.body || '{}'), b.dataset.method || 'POST');
                        if (res.reveal !== undefined) {
                                const target = document.querySelector(b.dataset.revealTarget);
                                if (target) { target.textContent = res.reveal; target.classList.add('is-revealed'); }
                                b.dispatchEvent(new CustomEvent('aim:revealed', { bubbles: true, detail: res }));
                        } else {
                                toast(res.message || 'انجام شد.', 'success');
                                setTimeout(reloadPage, 600);
                        }
                } catch (err) {
                        toast(err.message, 'error');
                } finally {
                        b.disabled = false;
                }
        });

        // ---- Copy to clipboard ----
        document.addEventListener('click', async (e) => {
                const c = e.target.closest('[data-copy]');
                if (!c) return;
                const el = c.dataset.copy.startsWith('#') ? document.querySelector(c.dataset.copy) : null;
                // A hidden secret («•••• محرمانه») must be revealed first, or the placeholder would be copied.
                if (el && el.classList.contains('secret') && !el.classList.contains('is-revealed')) { toast('ابتدا اطلاعات را با دکمهٔ نمایش ببینید، بعد کپی کنید.', 'warning'); return; }
                const src = c.dataset.copy.startsWith('#') ? el?.textContent : c.dataset.copy;
                try { await navigator.clipboard.writeText(src || ''); toast('کپی شد.', 'success'); } catch (err) { toast('کپی نشد؛ متن را دستی انتخاب کنید.', 'warning'); }
        });

        // ---- Persian digits → English in numeric inputs ----
        document.addEventListener('input', (e) => {
                const i = e.target;
                if (i.matches('input[inputmode=numeric], input[type=tel], input[data-num]')) {
                        const v = toEn(i.value);
                        if (v !== i.value) i.value = v;
                }
        });

        // ---- Auto-submit filter selects ----
        document.addEventListener('change', (e) => {
                if (e.target.matches('[data-autosubmit]')) e.target.form.requestSubmit();
        });

        // ---- Embedded wp-admin screen (templates/admin/embed.php) ----
        // The framed page reports its path and title (assets/js/wp-embed.js); mirror them in the address bar.
        window.addEventListener('message', (e) => {
                const embed = document.querySelector('[data-embed]');
                const frame = embed && embed.querySelector('[data-embed-frame]');
                if (!frame || e.origin !== location.origin || e.source !== frame.contentWindow || !e.data || e.data.aim !== 'embed') return;
                embed.querySelector('[data-embed-loading]').hidden = true;
                if (e.data.title) {
                        embed.querySelector('[data-embed-title]').textContent = e.data.title;
                        document.title = e.data.title + ' ‹ ' + (cfg.shop || '');
                }
                const url = new URL(location.href);
                if (e.data.path === embed.dataset.default) url.searchParams.delete('path');
                else url.searchParams.set('path', e.data.path);
                history.replaceState(history.state, '', url);
                loadedUrl = url.href.split('#')[0];
        });
        function initEmbed() {
                const embed = document.querySelector('[data-embed]');
                if (!embed) return;
                const frame = embed.querySelector('[data-embed-frame]');
                const loading = embed.querySelector('[data-embed-loading]');
                frame.addEventListener('load', () => {
                        loading.hidden = true;
                        // Navigating inside the frame shows the spinner again until the next page reports in.
                        try { frame.contentWindow.addEventListener('beforeunload', () => { loading.hidden = false; }); } catch (err) { /* cross-origin */ }
                });
                embed.addEventListener('click', (e) => {
                        if (e.target.closest('[data-embed-back]')) { try { frame.contentWindow.history.back(); } catch (err) { history.back(); } }
                        if (e.target.closest('[data-embed-reload]')) { loading.hidden = false; frame.contentWindow.location.reload(); }
                });
        }

        // ---- Bulk prices (templates/admin/bulk-prices.php) ----
        function initPrices() {
                const priceTable = document.querySelector('.prices-table');
                if (!priceTable) return;
                const checks = () => [...priceTable.querySelectorAll('[data-check]')];
                const chosen = () => checks().filter((c) => c.checked).map((c) => Number(c.value));
                const applyBtn = document.querySelector('[data-bulk-apply]');
                const saveBtn = document.querySelector('[data-prices-save]');
                const count = document.querySelector('[data-selected-count]');
                const bulk = (name) => document.querySelector('[data-bulk="' + name + '"]');
                const refresh = () => {
                        const n = chosen().length;
                        count.textContent = n ? toFa(n) + ' ردیف انتخاب شده' : 'ردیفی انتخاب نشده';
                        applyBtn.disabled = !n;
                        checks().forEach((c) => c.closest('tr').classList.toggle('is-selected', c.checked));
                        const changed = priceTable.querySelectorAll('.price-input.is-changed').length;
                        saveBtn.disabled = !changed;
                };
                // Explain the chosen operation in words, so a percentage can't be misread.
                const hint = () => {
                        const f = bulk('field').value === 'sale' ? 'قیمت ویژه' : 'قیمت عادی';
                        const v = toFa(toEn(bulk('value').value) || '…');
                        const unit = bulk('mode').value === 'percent' ? v + '٪' : v + ' تومان';
                        const text = {
                                increase: f + ' به اندازهٔ ' + unit + ' بیشتر می‌شود.',
                                decrease: f + ' به اندازهٔ ' + unit + ' کمتر می‌شود.',
                                set: bulk('mode').value === 'percent' && bulk('field').value === 'sale' ? 'قیمت ویژه روی ' + v + '٪ تخفیف از قیمت عادی تنظیم می‌شود.' : f + ' روی ' + unit + ' تنظیم می‌شود.',
                                clear: 'قیمت ویژه (تخفیف) ردیف‌های انتخاب‌شده حذف می‌شود.',
                        }[bulk('op').value];
                        document.querySelector('[data-bulk-hint]').textContent = text;
                        bulk('value').disabled = bulk('mode').disabled = bulk('op').value === 'clear';
                };
                document.querySelectorAll('[data-bulk]').forEach((el) => el.addEventListener('input', hint));
                hint();
                priceTable.addEventListener('change', (e) => {
                        if (e.target.matches('[data-check-all]')) checks().forEach((c) => { c.checked = e.target.checked; });
                        refresh();
                });
                priceTable.addEventListener('input', (e) => {
                        const i = e.target.closest('.price-input');
                        if (!i) return;
                        i.classList.toggle('is-changed', toEn(i.value) !== i.dataset.initial);
                        refresh();
                });
                applyBtn.addEventListener('click', async () => {
                        applyBtn.disabled = true;
                        try {
                                const res = await api('prices/bulk', {
                                        ids: chosen(), field: bulk('field').value, op: bulk('op').value, mode: bulk('mode').value,
                                        value: toEn(bulk('value').value), round: bulk('round').value,
                                });
                                toast(res.message, 'success');
                                setTimeout(reloadPage, 700);
                        } catch (err) { toast(err.message, 'error'); refresh(); }
                });
                saveBtn.addEventListener('click', async () => {
                        const rows = [...priceTable.querySelectorAll('tr[data-row]')]
                                .filter((tr) => tr.querySelector('.price-input.is-changed'))
                                .map((tr) => ({ id: Number(tr.dataset.row), regular: toEn(tr.querySelector('[data-price=regular]').value), sale: toEn(tr.querySelector('[data-price=sale]').value) }));
                        saveBtn.disabled = true;
                        try {
                                const res = await api('prices/save', { rows });
                                toast(res.message, 'success');
                                setTimeout(reloadPage, 700);
                        } catch (err) { toast(err.message, 'error'); refresh(); }
                });
        }

        // ---- Settings: CodeMirror boxes, search across every tab ----
        if (window.AIM_CODE_EDITORS && window.wp && wp.codeEditor) {
                document.querySelectorAll('[data-code-editor]').forEach((ta) => {
                        const settings = window.AIM_CODE_EDITORS[ta.dataset.codeEditor];
                        if (!settings) return;
                        const ed = wp.codeEditor.initialize(ta, settings);
                        ed.codemirror.on('change', (cm) => cm.save()); // keep the <textarea> current for the AJAX form
                        // A box inside a hidden tab measures 0 px; refresh it when its tab opens.
                        document.addEventListener('click', (e) => { if (e.target.closest('[data-tab="code"]')) setTimeout(() => ed.codemirror.refresh(), 0); });
                });
        }
        const saveBar = document.querySelector('[data-save-bar]');
        if (saveBar) {
                const ownSave = ['code', 'menu']; // tabs with their own form and save button
                saveBar.hidden = ownSave.includes(location.hash.slice(1));
                document.addEventListener('click', (e) => {
                        const t = e.target.closest('[data-tab]');
                        if (t) saveBar.hidden = ownSave.includes(t.dataset.tab);
                });
        }
        const settingsSearch = document.querySelector('[data-settings-search]');
        if (settingsSearch) {
                const root = document.querySelector('[data-tabs-root]');
                settingsSearch.addEventListener('input', () => {
                        const q = settingsSearch.value.trim().toLowerCase();
                        root.classList.toggle('is-searching', q !== '');
                        let hits = 0;
                        root.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                                let panelHits = 0;
                                // A match on the section title (or its tab) shows the whole section.
                                const tab = root.querySelector('[data-tab="' + panel.dataset.tabPanel + '"]');
                                const titles = [...panel.querySelectorAll('.card-head')].map((h) => h.textContent).join(' ') + ' ' + (tab ? tab.textContent : '');
                                const whole = q !== '' && titles.toLowerCase().includes(q);
                                panel.querySelectorAll('.form-row, .menu-switch').forEach((row) => {
                                        const hit = q === '' || whole || row.textContent.toLowerCase().includes(q);
                                        row.classList.toggle('is-miss', !hit);
                                        if (hit) panelHits++;
                                });
                                panel.classList.toggle('is-miss', q !== '' && !panelHits);
                                hits += panelHits;
                        });
                        document.querySelector('[data-settings-empty]').hidden = q === '' || hits > 0;
                });
        }

        // ---- «تنظیمات قالب» (templates/admin/theme-options.php): list rows and saving ----
        document.addEventListener('click', (e) => {
                const add = e.target.closest('[data-row-add]');
                if (add) {
                        const key = add.dataset.rowAdd;
                        const tpl = document.querySelector('[data-row-template="' + key + '"]');
                        const list = document.querySelector('[data-list="' + key + '"]');
                        list.appendChild(tpl.content.cloneNode(true));
                        const first = list.lastElementChild.querySelector('input, textarea, select');
                        if (first) first.focus();
                        return;
                }
                // Image fields: pick from the media library (the URL box also accepts a pasted link).
                const pick = e.target.closest('[data-image-pick]');
                if (pick && window.wp && wp.media) {
                        const box = pick.closest('[data-image]');
                        const frame = wp.media({ title: 'انتخاب تصویر', library: { type: 'image' }, multiple: false, button: { text: 'استفاده از این تصویر' } });
                        frame.on('select', () => {
                                const url = frame.state().get('selection').first().toJSON().url;
                                box.querySelector('input').value = url;
                                setImageThumb(box);
                        });
                        frame.open();
                        return;
                }
                const row = e.target.closest('[data-row]');
                if (!row) return;
                if (e.target.closest('[data-row-remove]')) row.remove();
                if (e.target.closest('[data-row-up]') && row.previousElementSibling) row.parentElement.insertBefore(row, row.previousElementSibling);
        });
        // Thumbnail of an image field follows its URL box (picked or pasted).
        function setImageThumb(box) {
                const thumb = box.querySelector('.opt-thumb');
                const url = box.querySelector('input').value.trim();
                if (!url) { thumb.innerHTML = '<i class="ic-image-2-line" aria-hidden="true"></i>'; return; }
                const img = document.createElement('img');
                img.src = url;
                img.alt = '';
                thumb.replaceChildren(img);
        }
        document.addEventListener('change', (e) => {
                const box = e.target.closest('[data-image]');
                if (box && e.target.matches('input')) setImageThumb(box);
        });
        document.addEventListener('submit', async (e) => {
                const form = e.target.closest('form[data-theme-options]');
                if (!form) return;
                e.preventDefault();
                const values = {};
                form.querySelectorAll('[data-opt]').forEach((el) => { values[el.dataset.opt] = el.value; });
                form.querySelectorAll('[data-list]').forEach((list) => {
                        values[list.dataset.list] = [...list.querySelectorAll('[data-row]')].map((row) => {
                                const item = {};
                                row.querySelectorAll('[data-sub]').forEach((el) => { item[el.dataset.sub] = el.value; });
                                return item;
                        });
                });
                const btn = form.querySelector('[type=submit]');
                btn.disabled = true;
                try {
                        const res = await api('theme-options', { values });
                        toast(res.message, 'success');
                } catch (err) {
                        toast(err.message, 'error');
                } finally {
                        btn.disabled = false;
                }
        });

        // ---- «قرارداد سفارش» (templates/admin/contract.php) ----
        document.addEventListener('submit', async (e) => {
                const form = e.target.closest('form[data-contract]');
                if (!form) return;
                e.preventDefault();
                const values = {};
                form.querySelectorAll('[data-c]').forEach((el) => { values[el.dataset.c] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value; });
                values.articles = [...form.querySelectorAll('[data-list=articles] [data-row]')].map((row) => ({
                        title: row.querySelector('[data-sub=title]').value,
                        body: row.querySelector('[data-sub=body]').value,
                }));
                const btn = form.querySelector('[type=submit]');
                btn.disabled = true;
                try {
                        const res = await api('contract/settings', { values });
                        toast(res.message, 'success');
                } catch (err) {
                        toast(err.message, 'error');
                } finally {
                        btn.disabled = false;
                }
        });

        // ---- AJAX navigation between admin panel sections ----
        // Links and GET filter forms inside the panel load the next section with fetch() and swap the
        // sidebar, top bar and content, so the browser keeps its CSS/JS and nothing flashes. Sections
        // that need extra assets (cfg.fullLoad, e.g. settings with CodeMirror) load the classic way.
        const shell = document.querySelector('.aim-shell');
        let loadedUrl = location.href.split('#')[0];
        let pending = null;

        const panelUrl = (href) => {
                let url;
                try { url = new URL(href, location.href); } catch (e) { return null; }
                if (url.origin !== location.origin || url.pathname !== location.pathname || url.searchParams.get('page') !== cfg.slug) return null;
                if ((cfg.fullLoad || []).includes(url.searchParams.get('section') || 'dashboard')) return null;
                return url;
        };

        // Lists marked data-autorefresh="seconds" (orders) reload quietly while nobody is working on the page.
        let refreshTimer = null;
        function initAutoRefresh() {
                clearInterval(refreshTimer);
                const el = document.querySelector('[data-autorefresh]');
                if (!el) return;
                refreshTimer = setInterval(() => {
                        const busy = document.hidden || document.body.classList.contains('is-navigating') || document.querySelector('.modal.is-open') ||
                                (document.activeElement && document.activeElement.closest('#content input, #content select, #content textarea'));
                        if (!busy) reloadPage();
                }, Number(el.dataset.autorefresh) * 1000);
        }
        document.addEventListener('click', (e) => {
                const b = e.target.closest('[data-reload-page]');
                if (!b) return;
                b.classList.add('is-loading');
                reloadPage();
        });

        // SMS gateway: show only the chosen provider's own credential/pattern fields
        // (every provider keeps its values; hidden inputs still save with the form).
        function initSmsProvider() {
                const chosen = document.querySelector('input[name="sms_driver"]:checked');
                document.querySelectorAll('[data-sms-provider]').forEach((el) => { el.hidden = !chosen || el.dataset.smsProvider !== chosen.value; });
        }
        document.addEventListener('change', (e) => {
                if (e.target && e.target.name === 'sms_driver') initSmsProvider();
        });

        function pageInit() {
                initEmbed();
                initPrices();
                initAutoRefresh();
                initSmsProvider();
                openHashTab();
        }

        async function navigate(href, opts = {}) {
                const url = panelUrl(href);
                if (!url || !shell) { location.href = href; return; }
                if (pending) pending.abort();
                const ctrl = pending = new AbortController();
                document.body.classList.add('is-navigating');
                try {
                        const res = await fetch(url.href, { credentials: 'same-origin', cache: 'no-store', signal: ctrl.signal, headers: { 'X-AIM-Nav': '1' } });
                        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                        const next = doc.querySelector('.aim-shell');
                        // Signed out, redirected elsewhere or an error page: let the browser handle it.
                        if (!res.ok || !next || !panelUrl(res.url)) { location.href = res.url || url.href; return; }

                        const navEl = shell.querySelector('.sidebar-nav');
                        const navScroll = navEl.scrollTop;
                        const keepScroll = opts.keepScroll ? window.scrollY : 0;
                        ['.sidebar-nav', '.topbar', '#content'].forEach((sel) => {
                                const from = next.querySelector(sel);
                                if (from) shell.querySelector(sel).replaceWith(document.importNode(from, true));
                        });
                        shell.querySelector('.sidebar-nav').scrollTop = navScroll;
                        document.title = doc.title;

                        const finalUrl = new URL(res.url);
                        finalUrl.hash = url.hash;
                        // Clicking the section already open must not stack a duplicate history entry.
                        if (opts.push !== false && finalUrl.href !== location.href) history.pushState({ aim: 1 }, '', finalUrl);
                        else history.replaceState({ aim: 1 }, '', finalUrl);
                        loadedUrl = finalUrl.href.split('#')[0];

                        // Inline <script>s in the new content do not run by themselves.
                        shell.querySelectorAll('#content script').forEach((old) => {
                                const sc = document.createElement('script');
                                sc.textContent = old.textContent;
                                old.replaceWith(sc);
                        });
                        setTheme(document.documentElement.dataset.theme || '', false);
                        document.body.classList.remove('sidebar-open');
                        // A modal inside the old content (e.g. after adding a user) is gone with it; its scroll lock must go too.
                        document.body.classList.remove('modal-open');
                        window.scrollTo(0, keepScroll);
                        pageInit();
                        shell.querySelector('#content').dispatchEvent(new CustomEvent('aim:page', { bubbles: true }));
                } catch (err) {
                        if (err.name !== 'AbortError') location.href = url.href;
                } finally {
                        // An aborted request was superseded; the newer navigation owns the loading state now.
                        if (!ctrl.signal.aborted) document.body.classList.remove('is-navigating');
                        if (pending === ctrl) pending = null;
                }
        }

        /** Re-load the current section in place (after saving, deleting, …), keeping the scroll position. */
        function reloadPage() {
                if (panelUrl(location.href)) navigate(location.href, { push: false, keepScroll: true });
                else location.reload();
        }

        document.addEventListener('click', (e) => {
                const a = e.target.closest('a[href]');
                if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                if (a.target && a.target !== '_self') return;
                if (a.hasAttribute('download') || a.hasAttribute('data-no-ajax') || a.getAttribute('href').startsWith('#')) return;
                const url = panelUrl(a.href);
                if (!url) return;
                e.preventDefault();
                navigate(url.href);
        });

        document.addEventListener('submit', (e) => {
                const form = e.target;
                if (e.defaultPrevented || form.dataset.api !== undefined || (form.method || 'get').toLowerCase() !== 'get') return;
                const url = panelUrl(form.action || location.href);
                if (!url) return;
                e.preventDefault();
                url.search = new URLSearchParams(new FormData(form)).toString();
                navigate(url.href);
        });

        window.addEventListener('popstate', () => {
                const now = location.href.split('#')[0];
                if (now === loadedUrl) { openHashTab(); return; } // only the #tab changed
                if (panelUrl(location.href)) navigate(location.href, { push: false });
                else location.reload();
        });
        history.replaceState({ aim: 1 }, '', location.href);
        pageInit();

        window.AIMUI = { api, toast, toEn, toFa, openModal, closeModal, setTheme, navigate, reloadPage };
})();
