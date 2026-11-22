/**
 * Section Manager Component
 * Manages visibility of different sections (create, details, errors) in the Nova Post order block.
 *
 * @class SectionManager
 */

/**
 * External dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { BaseComponent } from '../core';
import shipmentService from '../../services/shipmentService';
import { ServicesSection } from '../components/ShipmentPreviewModal/sections/ServicesSection';
import { ValidationErrors } from '../components/ShipmentPreviewModal/sections/ValidationErrors';

export class SectionManager extends BaseComponent {
    constructor(rootElement, eventBus) {
        super(rootElement, eventBus);

        this.sections = {
            create: this.querySelector('.npw-content__create'),
            details: this.querySelector('.npw-content__details'),
            errors: this.querySelector('.npw-content__errors'),
        };

        this.errorsList = this.sections.errors
            ? this.sections.errors.querySelector('.npw-content__errors-list')
            : null;

        this.paymentRequest = 0;
        this.init();
    }

    init() {
        this.initPaymentDetails();
        this.initSubscriptions();
    }

    /**
     * Initialize the payment renderer in the order sidebar.
     */
    initPaymentDetails() {
        if (this.rootElement.classList.contains('--order-meta-box') && this.sections.details) {
            const container = document.createElement('div');
            this.sections.details.appendChild(container);
            this.paymentRoot = createRoot(container);
            if (!this.sections.details.hidden) {
                void this.loadPaymentDetails();
            }
        }
    }

    /**
     * Render payment details from the persisted shipment request.
     */
    async loadPaymentDetails() {
        if (!this.paymentRoot) return;
        const request = ++this.paymentRequest;
        try {
            const shipment = await shipmentService.getShipmentRequest(this.orderId);
            if (request !== this.paymentRequest) return;
            this.paymentRoot.render(shipment ? <ServicesSection {...shipment} compact /> : null);
        } catch (error) {
            if (request === this.paymentRequest) {
                this.paymentRoot.render(<ValidationErrors errors={error} />);
            }
        }
    }

    /**
     * Clear payment details and ignore in-flight requests.
     */
    clearPaymentDetails() {
        this.paymentRequest++;
        this.paymentRoot?.render(null);
    }

    /**
     * Release the payment renderer and ignore in-flight requests.
     */
    destroyPaymentDetails() {
        this.paymentRequest++;
        this.paymentRoot?.unmount();
    }

    /**
     * Release renderers and event subscriptions.
     */
    destroy() {
        this.destroyPaymentDetails();
        super.destroy();
    }

    /**
     * Initialize event subscriptions
     */
    initSubscriptions() {
        this.subscribe('waybill:created', (data) => {
            if (this.isForThisOrder(data)) {
                this.showDetailsSection();
                void this.loadPaymentDetails();
            }
        });

        this.subscribe('shipment:deleted', (data) => {
            if (this.isForThisOrder(data)) {
                this.showCreateSection();
                this.clearPaymentDetails();
            }
        });

        this.subscribe('action:error', (data) => {
            if (this.isForThisOrder(data)) {
                this.showErrorSection(data.error);
            }
        });
    }

    /**
     * Show create section, hide others
     */
    showCreateSection() {
        this.setHidden(this.sections.create, false);
        this.setHidden(this.sections.details, true);
        this.hideErrorSection();
    }

    /**
     * Show details section, hide create
     */
    showDetailsSection() {
        this.setHidden(this.sections.create, true);
        this.setHidden(this.sections.details, false);
        this.hideErrorSection();
    }

    /**
     * Show error section
     *
     * @param {string} errorMessage - Error message to display
     */
    showErrorSection(errorMessage) {
        if (!this.sections.errors) {
            return;
        }

        const errorContent = this.sections.errors.querySelector('.npw-content__errors-content');
        if (errorContent && errorMessage) {
            errorContent.textContent = errorMessage;
        }

        this.setHidden(this.sections.errors, false);
    }

    /**
     * Hide error section
     */
    hideErrorSection() {
        if (this.sections.errors) {
            this.setHidden(this.sections.errors, true);
        }
    }
}
