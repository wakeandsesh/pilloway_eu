/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import NovaPostSettingsPage from '@/settings/components/SettingsPage';
import { CountriesProvider } from '@/settings/contexts/CountriesContext';
import { SettingsProvider } from '@/settings/contexts/SettingsContext';

import '@/styles/admin.scss';

/**
 * Root component that wraps the settings page with all necessary providers
 */
const NovaPostApp = () => {
    return (
        <SettingsProvider>
            <CountriesProvider>
                <NovaPostSettingsPage />
            </CountriesProvider>
        </SettingsProvider>
    );
};

/**
 * Add Nova Post settings page to the admin pages list
 */
addFilter('woocommerce_admin_pages_list', 'nova-post-settings', (pages) => {
    pages.push({
        container: NovaPostApp,
        path: '/nova-post-settings',
        breadcrumbs: [__('Nova Post', 'nova-post-for-woocommerce')],
        wpOpenMenu: 'toplevel_page_woocommerce',
        navArgs: {
            id: 'nova-post-settings',
        },
    });

    return pages;
});
