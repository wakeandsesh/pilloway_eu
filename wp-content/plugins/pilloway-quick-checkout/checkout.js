(() => {
    'use strict';
    const config = window.pillowayQuickCheckout;
    if (!config) return;
    const { labels } = config;
    const element = (tag, cls, text) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text) node.textContent = text;
        return node;
    };

    const reportedOrders = new Set();

    function track(event, fields = {}) {
        (window.dataLayer ||= []).push({ event, checkout_type: 'quick', ...fields });
    }

    function trackOrder(transactionId) {
        if (typeof transactionId !== 'string' || !transactionId || reportedOrders.has(transactionId)) return;
        reportedOrders.add(transactionId);
        const key = 'pilloway-qc-reported:' + transactionId;
        try {
            if (sessionStorage.getItem(key)) return;
            sessionStorage.setItem(key, '1');
        } catch {
            // The current page still prevents duplicates if storage is unavailable.
        }
        track('quick_checkout_submit', { transaction_id: transactionId });
        if (['pilloway.eu', 'www.pilloway.eu'].includes(window.location.hostname)
            && /(?:^|;\s*)pilloway_consent=all(?:;|$)/.test(document.cookie)
            && typeof window.gtag === 'function') {
            window.gtag('event', 'conversion', {
                send_to: 'AW-18585303888/ihXcCKSqkL0dEND2lJ5F',
                transaction_id: transactionId,
            });
        }
    }

    async function request(url, data) {
        const response = await fetch(url, {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            body: new URLSearchParams(data),
        });
        const json = await response.json();
        if (!response.ok || !json.success) throw new Error(json.data?.code || 'retry');
        return json.data;
    }

    function open(button) {
        const drawer = button.closest('.wc-block-mini-cart__drawer');
        if (!drawer || drawer.classList.contains('pilloway-cart-updating') ||
            drawer.querySelector('.pilloway-qc-panel')) return;
        const cart = drawer.querySelector('.wc-block-mini-cart__template-part');
        if (!cart) return;

        const panel = element('section', 'pilloway-qc-panel');
        const top = element('header', 'pilloway-qc-header');
        const back = element('button', 'pilloway-qc-back', '← ' + labels.back);
        back.type = 'button';
        const title = element('h2', '', labels.title);
        title.id = 'pilloway-qc-title';
        const originalLabel = drawer.getAttribute('aria-labelledby');
        drawer.setAttribute('aria-labelledby', title.id);
        top.append(back, title);
        const content = element('div', 'pilloway-qc-content');
        const summary = element('div', 'pilloway-qc-summary');
        const form = element('form', 'pilloway-qc-form');
        form.id = 'pilloway-qc-form';
        const actions = element('footer', 'pilloway-qc-actions');
        const status = element('p', 'pilloway-qc-status');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        const submit = element('button', 'pilloway-qc-open', labels.submit);
        submit.type = 'submit';
        submit.setAttribute('form', form.id);
        submit.disabled = true;

        for (const [key, type, max, auto] of [
            ['name', 'text', 100, 'given-name'],
            ['phone', 'tel', 40, 'tel'],
            ['contact', 'text', 150, 'off'],
        ]) {
            const label = element('label', '', labels[key]);
            const input = element('input');
            Object.assign(input, { name: key, type, maxLength: max, autocomplete: auto, required: key !== 'contact' });
            if (key === 'contact') input.placeholder = labels.placeholder;
            label.append(input);
            form.append(label);
        }

        const trap = element('input');
        Object.assign(trap, { name: 'website', type: 'text', hidden: true, tabIndex: -1, autocomplete: 'off' });
        const info = element('p', 'pilloway-qc-note', labels.info);
        const privacy = config.privacy ? element('a', 'pilloway-qc-note', labels.privacy) : element('p', 'pilloway-qc-note', labels.privacy);
        if (config.privacy) privacy.href = config.privacy;
        form.append(trap, summary, info, privacy);
        content.append(form);
        actions.append(status, submit);
        panel.append(top, content, actions);
        cart.hidden = true;
        cart.after(panel);
        back.focus();
        track('quick_checkout_open');

        let state;
        let sending = false;
        let completed = false;
        const dialog = drawer.closest('dialog');
        const leave = () => {
            dialog?.removeEventListener('close', leave);
            panel.remove();
            cart.hidden = false;
            if (originalLabel) drawer.setAttribute('aria-labelledby', originalLabel);
            else drawer.removeAttribute('aria-labelledby');
            if (completed) {
                window.location.reload();
            } else {
                button.focus();
            }
        };
        back.addEventListener('click', leave);
        panel.addEventListener('keydown', event => {
            if (sending && event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);
        if (dialog) dialog.addEventListener('close', leave, { once: true });

        async function load() {
            state = await request(config.preview, {});
            const items = element('ul', 'pilloway-qc-items');
            items.tabIndex = 0;
            for (const item of state.items) {
                const row = element('li');
                const photo = element('img', 'pilloway-qc-product-image');
                photo.src = item.image;
                photo.alt = '';
                photo.width = photo.height = 64;
                photo.loading = 'lazy';
                const details = element('div', 'pilloway-qc-product-details');
                details.append(element('strong', '', item.name),
                    element('span', 'pilloway-qc-product-price', item.quantity + ' ×  /  ' + item.price));
                row.append(photo, details);
                items.append(row);
            }
            summary.replaceChildren(items);
            if (state.coupons.length) summary.append(element('p', '', labels.discount + ' (' + state.coupons.join(', ') + '): ' + state.discount));
            summary.append(element('p', 'pilloway-qc-total', labels.total + ': ' + state.total));
            submit.disabled = false;
        }
        status.textContent = labels.loading;
        load().then(() => { status.textContent = ''; }).catch(error => {
            status.textContent = labels[error.message] || labels.retry;
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (!state || sending || completed) return;
            sending = true;
            back.disabled = submit.disabled = true;
            submit.textContent = labels.loading;
            status.textContent = '';
            try {
                const fields = Object.fromEntries(new FormData(form));
                const result = await request(config.submit, { ...fields, lang: config.lang, token: state.token, proof: state.proof, cart: state.cart });
                completed = true;
                trackOrder(result.transaction_id);
                form.hidden = submit.hidden = true;
                status.textContent = labels.success + ' #' + result.number + '. ' + labels.info;
                document.body.dispatchEvent(new CustomEvent('wc-blocks_removed_from_cart'));
            } catch (error) {
                status.textContent = labels[error.message] || labels.retry;
                if (['changed', 'expired'].includes(error.message)) {
                    state = null;
                    try { await load(); } catch (refreshError) {
                        status.textContent = labels[refreshError.message] || labels.retry;
                    }
                }
            } finally {
                sending = false;
                back.disabled = false;
                if (completed) back.focus();
                submit.disabled = completed || !state;
                submit.textContent = labels.submit;
            }
        });
    }

    document.addEventListener('click', event => {
        const button = event.target instanceof Element && event.target.closest('.pilloway-qc-open[type="button"]');
        if (button) open(button);
    });
})();