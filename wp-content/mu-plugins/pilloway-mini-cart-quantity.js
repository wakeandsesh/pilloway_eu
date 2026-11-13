/* Keep WooCommerce's own validation, request queue and totals. */
(() => {
    'use strict';
    const selector = '.wc-block-mini-cart__drawer input[data-wp-on--change="actions.changeQuantity"]';
    const pending = new Map();
    let requests = 0;
    const messages = {
        uk: '\u041e\u043d\u043e\u0432\u043b\u044e\u0454\u043c\u043e \u043a\u043e\u0448\u0438\u043a\u2026',
        ru: '\u041e\u0431\u043d\u043e\u0432\u043b\u044f\u0435\u043c \u043a\u043e\u0440\u0437\u0438\u043d\u0443\u2026',
        en: 'Updating cart\u2026', de: 'Warenkorb wird aktualisiert\u2026',
        fr: 'Mise \u00e0 jour du panier\u2026', pl: 'Aktualizowanie koszyka\u2026'
    };
    const label = messages[document.documentElement.lang.split('-')[0]] || messages.en;
    function render() {
        const busy = requests > 0 || pending.size > 0;
        document.querySelectorAll('.wc-block-mini-cart__drawer').forEach(drawer => {
            drawer.classList.toggle('pilloway-cart-updating', busy);
            drawer.setAttribute('aria-busy', String(busy));
            let status = drawer.querySelector('.pilloway-cart-status');
            if (!status) {
                status = document.createElement('div');
                status.className = 'pilloway-cart-status';
                status.setAttribute('role', 'status');
                status.setAttribute('aria-live', 'polite');
                status.setAttribute('aria-atomic', 'true');
                const anchor = drawer.querySelector('.wc-block-mini-cart__footer') || drawer.querySelector('.wc-block-mini-cart__items');
                (anchor || drawer).prepend(status);
            }
            status.textContent = busy ? label : '';
        });
    }
    const cancel = input => {
        clearTimeout(pending.get(input));
        pending.delete(input);
    };
    document.addEventListener('input', event => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.matches(selector)) return;
        cancel(input);
        if (!input.value || !input.checkValidity() || input.readOnly || input.disabled) { render(); return; }
        pending.set(input, setTimeout(() => {
            pending.delete(input);
            if (input.isConnected && input.value && input.checkValidity()) {
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            render();
        }, 600));
        render();
    });
    document.addEventListener('change', event => {
        cancel(event.target);
        // Woo's asynchronous handler starts the request after this event.
        requestAnimationFrame(render);
    }, true);
    // Observe only Store API cart writes; preserve the original fetch response/errors.
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
        const request = args[0];
        const url = request instanceof Request ? request.url : String(request);
        const method = (args[1]?.method || (request instanceof Request ? request.method : 'GET')).toUpperCase();
        const tracked = method !== 'GET' && /(?:wc\/store\/v\d+\/(?:batch|cart(?:\/|\?|$)))/.test(decodeURIComponent(url));
        if (!tracked) return originalFetch.apply(this, args);
        requests++;
        render();
        const finish = () => {
            requests--;
            requestAnimationFrame(() => requestAnimationFrame(render));
        };
        try {
            return originalFetch.apply(this, args).then(response => {
                // Wait for the body too, including slow or failed responses.
                response.clone().arrayBuffer().then(finish, finish);
                return response;
            }, error => { finish(); throw error; });
        } catch (error) { finish(); throw error; }
    };
    document.addEventListener('click', event => {
        if (!(event.target instanceof Element)) return;
        const link = event.target.closest('.wc-block-mini-cart__drawer a');
        if (link && (requests > 0 || pending.size > 0) && link.closest('.wc-block-mini-cart__footer')) {
            event.preventDefault();
        }
    }, true);
})();
