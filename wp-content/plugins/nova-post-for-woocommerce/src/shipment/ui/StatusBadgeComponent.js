/**
 * Status Badge Component
 * Updates the shipment status badge when status changes.
 *
 * @class StatusBadgeComponent
 */

/**
 * Internal dependencies
 */
import { BaseComponent } from '../core';

export class StatusBadgeComponent extends BaseComponent {
    constructor(rootElement, eventBus) {
        super(rootElement, eventBus);

        this.elements = {
            statusBadge: this.querySelector('.npw-status-badge-icon'),
            statusTooltip: this.querySelector('.npw-status-badge-icon .npw-status-badge__tooltip'),
        };

        this.init();
    }

    init() {
        this.initSubscriptions();
    }

    /**
     * Initialize event subscriptions
     */
    initSubscriptions() {
        this.subscribe('shipment:status-updated', (data) => {
            if (this.isForThisOrder(data)) {
                this.updateStatus(data.status, data.statusCode);
            }
        });

        this.subscribe('waybill:created', (data) => {
            if (this.isForThisOrder(data)) {
                this.updateStatus(data.status, data.statusCode);
            }
        });

        this.subscribe('status:loading-start', (data) => {
            if (this.isForThisOrder(data)) {
                this.setLoadingState(true);
            }
        });

        this.subscribe('status:loading-end', (data) => {
            if (this.isForThisOrder(data)) {
                this.setLoadingState(false);
            }
        });
    }

    /**
     * Update status badge
     *
     * @param {string} status     - Status text
     * @param {string} statusCode - Status code
     */
    updateStatus(status, statusCode) {
        if (!this.elements.statusBadge) {
            return;
        }

        // Update status code text
        if (this.elements.statusBadge && statusCode) {
            this.elements.statusBadge.dataset.statusCode = statusCode;
        }

        // Update tooltip with full status text
        if (this.elements.statusTooltip && status) {
            this.elements.statusTooltip.setAttribute('data-tooltip', status);
        }

        // Show the badge
        this.showStatusBadge();
    }

    /**
     * Show status badge
     */
    showStatusBadge() {
        if (this.elements.statusBadge) {
            this.setHidden(this.elements.statusBadge, false);
        }
    }

    /**
     * Hide status badge
     */
    hideStatus() {
        if (this.elements.statusBadge) {
            this.setHidden(this.elements.statusBadge, true);
        }
    }

    /**
     * Set loading state for status badge
     *
     * @param {boolean} isLoading - Loading state
     */
    setLoadingState(isLoading) {
        if (!this.elements.statusBadge) {
            return;
        }

        if (isLoading) {
            this.elements.statusBadge.setAttribute('loading', '');
        } else {
            this.elements.statusBadge.removeAttribute('loading');
        }
    }
}
