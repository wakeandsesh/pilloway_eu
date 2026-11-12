/**
 * Modal Renderer Utility
 *
 * Provides helper function to render React modals in the DOM.
 */

/**
 * External dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Render a modal component in the document body
 *
 * @param {Function} ModalComponent - The modal component to render
 * @param {Object}   componentProps - Props to pass to the modal component
 * @return {Promise} Promise that resolves with the modal result
 */
export function renderModal(ModalComponent, componentProps = {}) {
    return new Promise((resolve) => {
        const container = document.createElement('div');
        container.className = 'npw-modal-root';
        document.body.appendChild(container);

        const root = createRoot(container);

        const cleanup = () => {
            root.unmount();
            if (container.parentNode) {
                container.remove();
            }
        };

        const enhancedProps = {
            ...componentProps,
            onClose: () => {
                cleanup();
                if (componentProps.onClose) {
                    componentProps.onClose();
                }
                resolve({ confirmed: false });
            },
            onAction: async (data) => {
                try {
                    if (componentProps.onAction) {
                        await componentProps.onAction(data);
                    }
                    cleanup();
                    resolve({ confirmed: true, data });
                } catch (error) {
                    throw error;
                }
            },
        };

        root.render(<ModalComponent {...enhancedProps} />);
    });
}
