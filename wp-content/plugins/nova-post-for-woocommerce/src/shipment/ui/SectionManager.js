/**
 * Section Manager Component
 * Manages visibility of different sections (create, details, errors) in the Nova Post order block.
 *
 * @class SectionManager
 */

/**
 * Internal dependencies
 */
import { BaseComponent } from '../core';

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
                this.showDetailsSection();
            }
        });

        this.subscribe('shipment:deleted', (data) => {
            if (this.isForThisOrder(data)) {
                this.showCreateSection();
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
