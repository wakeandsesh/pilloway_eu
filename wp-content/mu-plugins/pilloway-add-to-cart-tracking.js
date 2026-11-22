(() => {
    'use strict';

    function reportCartAdditions() {
        const cookies = document.cookie.split('; ');
        for (const cookie of cookies) {
            const separator = cookie.indexOf('=');
            const name = cookie.slice(0, separator);
            if (!name.startsWith('pilloway_cart_event_')) continue;

            document.cookie = name + '=; Max-Age=0; Path=/; SameSite=Lax';
            if (!cookies.includes('pilloway_consent=all')) continue;

            let ecommerce;
            try {
                ecommerce = JSON.parse(decodeURIComponent(cookie.slice(separator + 1)));
            } catch {
                continue;
            }
            if (window.pillowayStartAnalytics?.()) {
                window.gtag('event', 'add_to_cart', { ...ecommerce, send_to: 'G-X8MQ0SF2YQ' });
            } else {
                const layer = (window.dataLayer ||= []);
                layer.push({ ecommerce: null });
                layer.push({ event: 'add_to_cart', ecommerce });
            }
        }
    }

    window.addEventListener('pageshow', reportCartAdditions);
    document.addEventListener('wc-blocks_added_to_cart', reportCartAdditions);
    window.jQuery(document).on('ajaxComplete', reportCartAdditions);
    reportCartAdditions();
})();
