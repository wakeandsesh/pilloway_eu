/**
 * External dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import BulkManagerApp from './components/BulkManagerApp';
import { handleClipboardClickPickupStatus } from './copy-buffer';

document.addEventListener('DOMContentLoaded', () => {
    const rootId = 'nova-post-order-page-app-root';
    const el = document.getElementById(rootId);

    const root = createRoot(el);
    root.render(<BulkManagerApp />);
});

document.addEventListener('click', handleClipboardClickPickupStatus, { capture: true });
