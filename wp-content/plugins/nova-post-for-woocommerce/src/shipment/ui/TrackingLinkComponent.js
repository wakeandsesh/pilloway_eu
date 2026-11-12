/**
 * Tracking Link Component
 * Manages the tracking number link and updates it when shipment is created/deleted.
 *
 * @class TrackingLinkComponent
 */

/**
 * Internal dependencies
 */
import { BaseComponent } from '../core';

export class TrackingLinkComponent extends BaseComponent {
    constructor(rootElement, eventBus) {
        super(rootElement, eventBus);

        this.elements = {
            trackLink: this.querySelector('.npw-content__track-number'),
            trackLinkText: this.querySelector('.npw-content__track-number-text'),
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
        this.subscribe('waybill:created', (data) => {
            if (this.isForThisOrder(data)) {
                this.updateTrackingLink(data.trackNumber, data.trackingUrl);
            }
        });

        this.subscribe('shipment:deleted', (data) => {
            if (this.isForThisOrder(data)) {
                this.clearTrackingLink();
            }
        });
    }

    /**
     * Update tracking link with new data
     *
     * @param {string} trackNumber - Tracking number
     * @param {string} trackingUrl - Tracking URL
     */
    updateTrackingLink(trackNumber, trackingUrl) {
        if (!this.elements.trackLink) {
            return;
        }

        // Update href
        if (trackingUrl) {
            this.elements.trackLink.href = trackingUrl;
        }

        // Update text content
        const textElement = this.elements.trackLinkText || this.elements.trackLink;
        if (textElement) {
            textElement.textContent = trackNumber;
        }

        // Show the link
        this.setHidden(this.elements.trackLink, false);
    }

    /**
     * Clear and hide tracking link
     */
    clearTrackingLink() {
        if (!this.elements.trackLink) {
            return;
        }

        this.elements.trackLink.href = '#';

        const textElement = this.elements.trackLinkText || this.elements.trackLink;
        if (textElement) {
            textElement.textContent = '';
        }

        this.setHidden(this.elements.trackLink, true);
    }
}
