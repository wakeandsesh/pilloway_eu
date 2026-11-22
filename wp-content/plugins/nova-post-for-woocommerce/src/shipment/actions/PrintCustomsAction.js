/**
 * Print international marking action handler.
 *
 * @class PrintCustomsAction
 */

/**
 * External dependencies
 */
import printJS from 'print-js';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { BaseAction } from '../core';
import shipmentService from '../../services/shipmentService.js';
import noticeService from '../../services/noticeService.js';

export class PrintCustomsAction extends BaseAction {
    constructor(eventBus) {
        super(eventBus);
        this.actionName = 'print_customs';
    }

    /**
     * Execute international marking printing
     *
     * @param {HTMLElement} button - The print international marking button
     * @return {Promise<void>}
     */
    async execute(button) {
        if (!this.validateElement(button)) {
            return;
        }

        const orderId = this.getOrderId(button);

        this.emit('action:start', {
            action: this.actionName,
            orderId,
        });

        this.setLoading(button, true);

        try {
            const base64Data = await shipmentService.getCustomsDocument(orderId);

            printJS({ printable: base64Data, type: 'pdf', base64: true });

            this.emit('document:printed', {
                orderId,
                documentType: 'customs',
            });

            noticeService.showSuccess(
                __(
                    'International marking generated and printing initiated successfully!',
                    'nova-post-for-woocommerce'
                ),
                { scrollToTop: false }
            );
        } catch (error) {
            noticeService.showError(
                error.message ||
                    __(
                        'Failed to generate international marking. Please try again.',
                        'nova-post-for-woocommerce'
                    )
            );
        } finally {
            this.setLoading(button, false);
        }
    }
}
