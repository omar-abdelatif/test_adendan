/*
 * Adendan dashboard behaviour (direction 1). Needs jQuery + DataTables 1.13 (engine only)
 * and feather icons. Everything here works through data attributes:
 *   data-nb-open="#id"     open a modal          data-nb-close   close the modal it sits in
 *   data-nb-table          client-side table     data-nb-tabs    tab bar (buttons with data-nb-tab="#panel")
 *   data-nb-filter="#tbl"  chip that filters a table by column text (data-col, data-value)
 *   POST forms are sent in the background with a "please wait" popup (data-nb-native opts out,
 *   data-nb-busy="…" sets the popup text). The header menu button shrinks the sidebar on desktop.
 */
(function ($) {
    'use strict';

    const icons = () => { if (window.feather) window.feather.replace({ 'stroke-width': 1.9 }); };

    /* ---------- modals ---------- */
    let lastFocus = null;
    function openModal(modal) {
        if (!modal) return;
        lastFocus = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('nb-modal-open');
        const first = modal.querySelector('input:not([type=hidden]):not([readonly]), select, textarea, button.nb-btn');
        if (first) setTimeout(() => first.focus(), 30);
    }
    function closeModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        if (!document.querySelector('.nb-modal:not([hidden])')) document.body.classList.remove('nb-modal-open');
        if (lastFocus) lastFocus.focus();
    }
    window.nbModal = { open: (sel) => openModal(document.querySelector(sel)), close: (sel) => closeModal(document.querySelector(sel)) };

    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-nb-open]');
        if (opener) { e.preventDefault(); openModal(document.querySelector(opener.dataset.nbOpen)); return; }
        const closer = e.target.closest('[data-nb-close]');
        if (closer) { e.preventDefault(); closeModal(closer.closest('.nb-modal')); return; }
        if (e.target.classList.contains('nb-modal__backdrop')) closeModal(e.target.closest('.nb-modal'));
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const open = [...document.querySelectorAll('.nb-modal:not([hidden])')].pop();
        if (open) closeModal(open);
    });

    /* ---------- tabs ---------- */
    document.addEventListener('click', (e) => {
        const tab = e.target.closest('[data-nb-tab]');
        if (!tab) return;
        e.preventDefault();
        const bar = tab.closest('[data-nb-tabs]');
        bar.querySelectorAll('[data-nb-tab]').forEach((t) => {
            const on = t === tab;
            t.classList.toggle('is-on', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            const panel = document.querySelector(t.dataset.nbTab);
            if (panel) panel.hidden = !on;
        });
        $(document.querySelector(tab.dataset.nbTab)).find('table.dataTable').DataTable().columns.adjust();
    });

    /* ---------- file inputs: show the chosen name ---------- */
    document.addEventListener('change', (e) => {
        const input = e.target.closest('.nb-file input[type=file]');
        if (!input) return;
        const box = input.closest('.nb-file');
        const name = box.querySelector('.nb-file__name');
        const files = [...input.files].map((f) => f.name);
        box.classList.toggle('has-file', files.length > 0);
        if (name) name.textContent = files.length ? files.join('، ') : name.dataset.empty;
        const preview = input.dataset.preview && document.querySelector(input.dataset.preview);
        if (preview && input.files[0]) {
            const reader = new FileReader();
            reader.onload = (ev) => { preview.src = ev.target.result; preview.hidden = false; };
            reader.readAsDataURL(input.files[0]);
        }
    });

    /* ---------- repeatable inputs (e.g. several video links) ---------- */
    document.addEventListener('click', (e) => {
        const add = e.target.closest('[data-nb-repeat-add]');
        if (add) {
            const list = document.querySelector(add.dataset.nbRepeatAdd);
            const tpl = list.querySelector('template');
            list.insertBefore(tpl.content.cloneNode(true), tpl);
            icons();
            return;
        }
        const del = e.target.closest('[data-nb-repeat-remove]');
        if (del) del.closest('.nb-repeat__row').remove();
    });

    /* ---------- conditional fields: data-nb-when="category:أخرى|other" ---------- */
    function syncWhen(scope) {
        (scope || document).querySelectorAll('[data-nb-when]').forEach((el) => {
            const [field, values] = el.dataset.nbWhen.split(':');
            const form = el.closest('form') || document;
            const inputs = form.querySelectorAll('[name="' + field + '"]');
            let current = '';
            inputs.forEach((i) => { if ((i.type !== 'radio' && i.type !== 'checkbox') || i.checked) current = i.value; });
            const show = values.split('|').includes(current);
            el.hidden = !show;
            el.querySelectorAll('input, select, textarea').forEach((i) => { i.disabled = !show; });
        });
    }
    document.addEventListener('change', (e) => { if (e.target.closest('form')) syncWhen(e.target.closest('form')); });

    /* ---------- forms answered with JSON: <form data-nb-ajax data-nb-remove=".nb-card"> ---------- */
    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-nb-ajax]');
        if (!form) return;
        e.preventDefault();
        const button = form.querySelector('[type=submit]');
        if (button) button.disabled = true;
        busy.show(form.dataset.nbBusy || 'جاري الحفظ…');
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => ({}));
            const ok = res.ok && data.success !== false && data.status !== false;
            const error = data.errors ? Object.values(data.errors).flat()[0] : null;
            await busy.hide();
            window.nbToast(error || data.message || (ok ? 'تم' : 'حصلت مشكلة'), ok ? 'success' : 'error');
            if (ok && form.hasAttribute('data-nb-reload')) setTimeout(() => softVisit(location.href), 600);
            if (ok && form.dataset.nbRemove) {
                const gone = form.closest(form.dataset.nbRemove);
                if (gone) gone.remove();
            }
        } catch (err) {
            await busy.hide();
            window.nbToast('مفيش اتصال بالسيرفر، جرّب تاني', 'error');
        } finally {
            if (button) button.disabled = false;
        }
    });

    /* ---------- "please wait" popup while a form is being sent ---------- */
    const busy = (function () {
        let el = null;
        let shownAt = 0;
        const box = () => {
            if (el && el.isConnected) return el;
            el = document.createElement('div');
            el.className = 'nb-busy';
            el.hidden = true;
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'assertive');
            el.innerHTML = '<div class="nb-busy__card"><span class="nb-busy__spin" aria-hidden="true"></span><strong></strong>'
                + '<span class="nb-busy__note">استنى لحظة لحد ما العملية تخلص.</span><span class="nb-busy__bar" hidden><span></span></span></div>';
            document.body.appendChild(el);
            return el;
        };
        return {
            show(text) {
                const b = box();
                b.querySelector('strong').textContent = text;
                b.querySelector('.nb-busy__bar').hidden = true;
                b.hidden = false;
                shownAt = Date.now();
            },
            progress(percent) {
                const b = box();
                b.querySelector('.nb-busy__bar').hidden = false;
                b.querySelector('.nb-busy__bar > span').style.width = percent + '%';
                b.querySelector('strong').textContent = percent < 100 ? 'جاري رفع الملف… ' + percent + '%' : 'الملف اترفع، جاري الحفظ…';
            },
            // Stays at least a moment, so a fast answer doesn't just flash.
            async settle() {
                const wait = 350 - (Date.now() - shownAt);
                if (wait > 0) await new Promise((resolve) => setTimeout(resolve, wait));
            },
            async hide() {
                await this.settle();
                if (el) el.hidden = true;
            },
            element: () => el,
        };
    })();
    window.nbBusy = busy;

    /* ---------- forms are sent in the background: no full reload ----------
     * Every POST form in the dashboard goes out with XHR. The page the server answers with
     * (normally the same page after its redirect) replaces the current one in place, so the
     * toast and the fresh data show without the screen going blank. Opt out with data-nb-native.
     */
    const loadedScripts = new Set([...document.scripts].filter((s) => s.src).map((s) => s.src.split('?')[0]));
    let sending = false;
    let movedAway = false;

    function sendsInBackground(form, e) {
        if (e.defaultPrevented || !document.querySelector('.nb-app')) return false;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return false;
        if (form.hasAttribute('data-nb-native') || form.hasAttribute('data-nb-ajax')) return false;
        const target = (e.submitter && e.submitter.getAttribute('formtarget')) || form.getAttribute('target');
        if (target && target !== '_self') return false;
        return new URL(form.action, location.href).origin === location.origin;
    }

    function busyText(form, data) {
        if (form.dataset.nbBusy) return form.dataset.nbBusy;
        if (String(data.get('_method') || '').toUpperCase() === 'DELETE') return 'جاري الحذف…';
        return [...data.values()].some((v) => v instanceof File && v.size) ? 'جاري رفع الملف…' : 'جاري الحفظ…';
    }

    function request(method, url, body, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url);
            // A normal page request, so Laravel answers exactly as it would to the browser (redirect + flash).
            xhr.setRequestHeader('Accept', 'text/html,application/xhtml+xml');
            if (onProgress) xhr.upload.onprogress = (ev) => { if (ev.lengthComputable) onProgress(Math.round(ev.loaded / ev.total * 100)); };
            xhr.onload = () => resolve(xhr);
            xhr.onerror = () => reject(new Error('network'));
            xhr.send(body);
        });
    }

    async function answer(xhr) {
        const type = xhr.getResponseHeader('Content-Type') || '';
        if (xhr.status === 419) {
            await busy.hide();
            window.nbToast('الصفحة فضلت مفتوحة وقت طويل. هنحدّثها، وبعدها جرّب تاني', 'warning');
            setTimeout(() => location.reload(), 1500);
            return;
        }
        if (type.includes('application/json')) {
            const data = JSON.parse(xhr.responseText || '{}');
            const ok = xhr.status < 400 && data.success !== false && data.status !== false;
            const error = data.errors ? Object.values(data.errors).flat()[0] : null;
            await busy.hide();
            window.nbToast(error || data.message || (ok ? 'تم' : 'حصلت مشكلة'), ok ? 'success' : 'error');
            if (ok) softVisit(location.href, true);
            return;
        }
        if (xhr.status >= 400) {
            await busy.hide();
            console.error('Request failed', xhr.status, xhr.responseURL);
            const messages = { 403: 'معندكش صلاحية للعملية دي', 404: 'الحاجة دي مش موجودة، يمكن اتحذفت', 413: 'الملف كبير أوي' };
            window.nbToast(messages[xhr.status] || 'حصلت مشكلة في السيرفر، والعملية ما اتمتش', 'error');
            return;
        }
        await busy.settle();
        await render(xhr.responseText, xhr.responseURL || location.href);
        await busy.hide();
    }

    async function send(form, submitter) {
        const data = new FormData(form);
        if (submitter && submitter.name) data.append(submitter.name, submitter.value);
        const hasFile = [...data.values()].some((v) => v instanceof File && v.size);
        const buttons = [...form.querySelectorAll('button, input[type=submit]')];
        sending = true;
        buttons.forEach((b) => { b.disabled = true; });
        busy.show(busyText(form, data));
        try {
            await answer(await request('POST', form.action, data, hasFile ? busy.progress : null));
        } catch (err) {
            await busy.hide();
            console.error(err);
            window.nbToast('مفيش اتصال بالسيرفر، جرّب تاني', 'error');
        } finally {
            sending = false;
            buttons.forEach((b) => { if (b.isConnected) b.disabled = false; });
        }
    }

    // Reloads a page in place (after a JSON action, or when asked) with the same popup.
    async function softVisit(url, quiet) {
        if (!quiet) busy.show('جاري التحديث…');
        try {
            const xhr = await request('GET', url, null);
            if (xhr.status >= 400) throw new Error(String(xhr.status));
            await render(xhr.responseText, xhr.responseURL || url);
        } catch (err) {
            location.reload();
        } finally {
            await busy.hide();
        }
    }
    window.nbVisit = softVisit;

    // Runs after every other submit handler (it sits on window), so pages that handle their own forms win.
    window.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !sendsInBackground(form, e)) return;
        e.preventDefault();
        if (!sending) send(form, e.submitter);
    });

    async function render(html, url) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        if (!doc.querySelector('.nb-app')) { location.assign(url); return; } // e.g. sent back to the login page
        const target = new URL(url, location.href);
        const samePage = target.pathname === location.pathname && target.search === location.search;
        const kept = samePage ? {
            y: window.scrollY,
            tabs: [...document.querySelectorAll('[data-nb-tabs]')].map((bar) => (bar.querySelector('[data-nb-tab].is-on') || {}).dataset?.nbTab),
        } : null;

        // Tear down what the old page set up; its scripts run again below.
        if ($.fn.dataTable) $.fn.dataTable.tables().forEach((t) => { try { $(t).DataTable().destroy(); } catch (err) { /* already gone */ } });
        $(document).off();
        $(window).off();

        document.title = doc.title;
        const token = doc.querySelector('meta[name="csrf-token"]');
        if (token) document.querySelector('meta[name="csrf-token"]').setAttribute('content', token.content);
        doc.head.querySelectorAll('style, link[rel="stylesheet"]').forEach((node) => {
            if (![...document.head.children].some((n) => n.outerHTML === node.outerHTML)) document.head.appendChild(document.importNode(node, true));
        });

        const scripts = [...doc.body.querySelectorAll('script')];
        scripts.forEach((s) => s.remove());
        const popup = busy.element();
        document.body.className = doc.body.className;
        document.body.replaceChildren(...[...doc.body.childNodes].map((n) => document.importNode(n, true)));
        if (popup) document.body.appendChild(popup);
        if (!samePage) {
            if (!movedAway) history.replaceState({ nbSoft: true }, '', location.href);
            history.pushState({ nbSoft: true }, '', target.href);
            movedAway = true;
        }

        init();
        for (const old of scripts) {
            const src = old.src ? old.src.split('?')[0] : null;
            if (src && loadedScripts.has(src)) continue;
            const s = document.createElement('script');
            [...old.attributes].forEach((a) => s.setAttribute(a.name, a.value));
            if (src) {
                loadedScripts.add(src);
                await new Promise((resolve) => { s.onload = resolve; s.onerror = resolve; document.body.appendChild(s); });
            } else {
                s.textContent = old.textContent;
                document.body.appendChild(s);
            }
        }

        if (kept) {
            kept.tabs.forEach((sel, i) => {
                const bar = document.querySelectorAll('[data-nb-tabs]')[i];
                const tab = sel && bar && [...bar.querySelectorAll('[data-nb-tab]')].find((t) => t.dataset.nbTab === sel);
                if (tab && !tab.classList.contains('is-on')) tab.click();
            });
            window.scrollTo(0, kept.y);
        } else {
            window.scrollTo(0, 0);
        }
    }
    // Back/forward after an in-place page change: just load that page.
    window.addEventListener('popstate', () => { if (movedAway) location.reload(); });

    /* ---------- sidebar: off-canvas on small screens, shrinks to icons on desktop ---------- */
    const wide = window.matchMedia('(min-width: 1100px)');
    const SIDE_KEY = 'nb-side-mini';
    function syncSideButton() {
        const mini = document.documentElement.classList.contains('nb-side-mini');
        document.querySelectorAll('[data-nb-side-toggle]').forEach((b) => {
            if (!wide.matches) { b.setAttribute('aria-label', 'القائمة'); b.removeAttribute('aria-pressed'); return; }
            b.setAttribute('aria-label', mini ? 'كبّر القائمة الجانبية' : 'صغّر القائمة الجانبية');
            b.setAttribute('title', mini ? 'كبّر القائمة الجانبية' : 'صغّر القائمة الجانبية');
            b.setAttribute('aria-pressed', mini ? 'true' : 'false');
        });
    }
    // A flyout needs its <details> open (closed details render nothing); put back how it was after.
    function closeFlyouts(except) {
        document.querySelectorAll('.nb-side details.is-fly').forEach((d) => {
            if (d === except) return;
            d.classList.remove('is-fly');
            d.open = d.dataset.wasOpen === '1';
        });
    }
    function openFlyout(details) {
        details.dataset.wasOpen = details.open ? '1' : '0';
        details.open = true;
        details.classList.add('is-fly');
    }
    const tip = document.createElement('div');
    tip.className = 'nb-side-tip';
    tip.hidden = true;
    const isMini = () => wide.matches && document.documentElement.classList.contains('nb-side-mini');
    function showTip(link) {
        if (!isMini() || link.closest('details.is-fly')) return;
        const r = link.getBoundingClientRect();
        tip.textContent = link.textContent.trim();
        tip.style.top = (r.top + r.height / 2) + 'px';
        tip.style.right = (window.innerWidth - r.left + 10) + 'px';
        if (!tip.isConnected) document.body.appendChild(tip);
        tip.hidden = false;
    }
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-nb-side-toggle]')) {
            if (!wide.matches) { document.body.classList.toggle('nb-side-open'); return; }
            const mini = document.documentElement.classList.toggle('nb-side-mini');
            try { localStorage.setItem(SIDE_KEY, mini ? '1' : '0'); } catch (err) { /* private mode: just this page */ }
            closeFlyouts();
            tip.hidden = true;
            syncSideButton();
            return;
        }
        if (e.target.classList.contains('nb-scrim')) { document.body.classList.remove('nb-side-open'); return; }
        // In the icon-only sidebar a section opens as a flyout beside it.
        const summary = e.target.closest('.nb-side details > summary');
        if (summary && isMini()) {
            e.preventDefault();
            const details = summary.parentElement;
            const r = summary.getBoundingClientRect();
            details.style.setProperty('--nb-fly-top', Math.max(12, Math.min(r.top, window.innerHeight - 320)) + 'px');
            const flying = details.classList.contains('is-fly');
            closeFlyouts();
            if (!flying) openFlyout(details);
            tip.hidden = true;
            return;
        }
        if (!e.target.closest('.nb-side details.is-fly')) closeFlyouts();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeFlyouts(); });
    document.addEventListener('mouseover', (e) => { const link = e.target.closest('.nb-side__link'); if (link) showTip(link); });
    document.addEventListener('mouseout', (e) => { if (e.target.closest('.nb-side__link')) tip.hidden = true; });
    document.addEventListener('focusin', (e) => { const link = e.target.closest('.nb-side__link'); if (link) showTip(link); else tip.hidden = true; });
    wide.addEventListener('change', () => { closeFlyouts(); tip.hidden = true; syncSideButton(); });

    /* ---------- toasts ---------- */
    window.nbToast = function (message, type) {
        let box = document.querySelector('.nb-toasts');
        if (!box) { box = document.createElement('div'); box.className = 'nb-toasts'; document.body.appendChild(box); }
        const el = document.createElement('div');
        el.className = 'nb-toast nb-toast--' + (type || 'info');
        el.setAttribute('role', 'status');
        el.innerHTML = '<span></span><button type="button" aria-label="إغلاق">✕</button>';
        el.firstChild.textContent = message;
        box.appendChild(el);
        setTimeout(() => el.remove(), 6000);
    };
    document.addEventListener('click', (e) => { const b = e.target.closest('.nb-toast button'); if (b) b.closest('.nb-toast').remove(); });

    /* ---------- tables ---------- */
    if ($.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            dom: '<"nb-dt-top"fl>rt<"nb-dt-bottom"ip>',
            pagingType: 'simple_numbers',
            autoWidth: false,
            pageLength: 15,
            lengthMenu: [10, 15, 25, 50, 100],
            language: {
                search: '', searchPlaceholder: 'بحث…',
                lengthMenu: 'عرض _MENU_',
                info: 'عرض _START_ إلى _END_ من _TOTAL_',
                infoEmpty: 'مفيش بيانات',
                infoFiltered: '(من أصل _MAX_)',
                zeroRecords: 'مفيش نتايج مطابقة',
                emptyTable: 'مفيش بيانات لسه',
                loadingRecords: 'جاري التحميل…',
                processing: 'جاري التحميل…',
                paginate: { first: 'الأولى', last: 'الأخيرة', next: '‹', previous: '›' },
            },
        });
    }

    // Everything a page needs once its HTML is in place; runs on load and after every in-place page change.
    function init() {
        icons();
        syncWhen();
        syncSideButton();

        $('table[data-nb-table]').each(function () {
            const $t = $(this);
            const order = $t.data('order');
            $t.DataTable({
                order: order === undefined ? [] : order,
                pageLength: $t.data('pageLength') || 15,
                paging: $t.data('paging') !== false,
                searching: $t.data('searching') !== false,
                info: $t.data('paging') !== false,
                columnDefs: [{ targets: 'no-sort', orderable: false }],
                drawCallback: icons,
            });
        });

        // chips that filter a table column: <a data-nb-filter="#tbl" data-col="3" data-value="مفعل">
        $(document).on('click', '[data-nb-filter]', function (e) {
            e.preventDefault();
            const $chip = $(this);
            const table = $($chip.data('nbFilter')).DataTable();
            $chip.siblings('[data-nb-filter]').removeClass('is-on');
            $chip.addClass('is-on');
            const value = String($chip.data('value') ?? '');
            table.columns().search('');
            if (value !== '') table.column($chip.data('col')).search('^\\s*' + $.fn.dataTable.util.escapeRegex(value) + '\\s*$', true, false);
            table.draw();
        });

        // expandable rows: <button data-nb-child> next to a <template> in the same cell
        $(document).on('click', '[data-nb-child]', function () {
            const $tr = $(this).closest('tr');
            const row = $tr.closest('table').DataTable().row($tr);
            if (row.child.isShown()) { row.child.hide(); $tr.removeClass('dt-hasChild'); return; }
            row.child($(this).siblings('template').html()).show();
            $tr.addClass('dt-hasChild');
            icons();
        });

        // modals that should be open after a failed submit
        document.querySelectorAll('.nb-modal[data-nb-autoopen]').forEach(openModal);

        setTimeout(() => document.querySelectorAll('.nb-toast[data-auto]').forEach((t) => t.remove()), 7000);
    }
    $(init);

    window.nbIcons = icons;
})(jQuery);
