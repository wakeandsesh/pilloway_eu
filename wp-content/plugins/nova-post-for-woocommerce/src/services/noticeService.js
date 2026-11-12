/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Notice Service for Nova Post WooCommerce
 *
 * Handles displaying notices, alerts, and user feedback messages
 */
class NoticeService {
    constructor() {
        this.defaultOptions = {
            autoDismiss: true,
            dismissDelay: 5000,
            fadeInDuration: 300,
            fadeOutDuration: 1000,
            // Scroll behavior
            scrollToTop: true, // Scroll page to top in notice flow
            scrollBeforeShow: true, // Perform scroll first, then insert notice
            scrollInsertDelay: 200, // Delay (ms) after starting scroll before inserting
            scrollBehavior: 'smooth', // 'auto' | 'smooth'
            scrollOffset: 0, // Extra offset from top (px)
        };
    }

    /**
     * Show success notice
     *
     * @param {string} message - The success message
     * @param {Object} options - Additional options
     */
    showSuccess(message, options = {}) {
        this.showNotice(message, 'success', options);
    }

    /**
     * Show error notice
     *
     * @param {string} message - The error message
     * @param {Object} options - Additional options
     */
    showError(message, options = { autoDismiss: false }) {
        this.showNotice(message, 'error', options);
    }

    /**
     * Show warning notice
     *
     * @param {string} message - The warning message
     * @param {Object} options - Additional options
     */
    showWarning(message, options = {}) {
        this.showNotice(message, 'warning', options);
    }

    /**
     * Show info notice
     *
     * @param {string} message - The info message
     * @param {Object} options - Additional options
     */
    showInfo(message, options = {}) {
        this.showNotice(message, 'info', options);
    }

    /**
     * Show notice message
     *
     * @param {string} message - The message to display
     * @param {string} type    - The notice type ('success', 'error', 'info', 'warning')
     * @param {Object} options - Additional options
     * @return {HTMLElement} - The created notice element
     */
    showNotice(message, type = 'info', options = {}) {
        const config = { ...this.defaultOptions, ...options };
        const noticeClass = this.getNoticeClass(type);

        // Create notice element
        const notice = document.createElement('div');
        notice.className = `notice ${noticeClass} is-dismissible npw-fade-in`;
        notice.innerHTML = `
      <p>${this.escapeHtml(message)}</p>
      <button type="button" class="notice-dismiss">
        <span class="screen-reader-text">Dismiss this notice.</span>
      </button>
    `;

        const insertionPoint = this.findNoticeInsertionPoint();

        const finalizeNotice = () => {
            if (insertionPoint && !notice.parentNode) {
                insertionPoint.insertAdjacentElement('afterend', notice);
            }

            // Add dismiss functionality
            this.addDismissFunctionality(notice, config);

            // Auto dismiss if enabled
            if (config.autoDismiss) {
                this.autoDismiss(notice, config.dismissDelay, config.fadeOutDuration);
            }
        };

        if (config.scrollToTop && config.scrollBeforeShow) {
            this.scrollPageTop(config);
            // Insert after a short delay to allow scroll to initiate/complete
            setTimeout(finalizeNotice, config.scrollInsertDelay);
        } else {
            if (insertionPoint) {
                insertionPoint.insertAdjacentElement('afterend', notice);
            }
            if (config.scrollToTop) {
                this.scrollPageTop(config); // legacy behavior: scroll after insertion
            }
            finalizeNotice();
        }

        return notice; // Returned immediately; may not yet be in DOM if scrollBeforeShow
    }

    /**
     * Scroll the window to the very top (optionally with offset)
     *
     * @param {Object} config - Configuration (expects scrollBehavior, scrollOffset)
     */
    scrollPageTop(config) {
        const behavior = config.scrollBehavior || 'smooth';
        const offset = typeof config.scrollOffset === 'number' ? config.scrollOffset : 0;
        const target = 0 + offset; // offset can push slightly below the absolute top
        try {
            window.scrollTo({ top: target, behavior });
        } catch (_) {
            window.scrollTo(0, target);
        }
    }

    /**
     * Add dismiss functionality to notice
     *
     * @param {HTMLElement} notice - The notice element
     * @param {Object}      config - Configuration options
     */
    addDismissFunctionality(notice, config) {
        const dismissButton = notice.querySelector('.notice-dismiss');
        if (dismissButton) {
            dismissButton.addEventListener('click', () => {
                this.dismissNotice(notice, config.fadeOutDuration);
            });
        }

        // Add click-to-dismiss functionality
        notice.addEventListener('click', (event) => {
            if (event.target === notice) {
                this.dismissNotice(notice, config.fadeOutDuration);
            }
        });
    }

    /**
     * Dismiss notice with fade out animation
     *
     * @param {HTMLElement} notice          - The notice element
     * @param {number}      fadeOutDuration - Fade out duration in milliseconds
     */
    dismissNotice(notice, fadeOutDuration = 1000) {
        if (!notice.parentNode) return;

        notice.style.transition = `opacity ${fadeOutDuration}ms ease`;
        notice.style.opacity = '0';

        setTimeout(() => {
            if (notice.parentNode) {
                notice.remove();
            }
        }, fadeOutDuration);
    }

    /**
     * Auto dismiss notice after specified delay
     *
     * @param {HTMLElement} notice          - The notice element
     * @param {number}      delay           - Delay before auto dismiss in milliseconds
     * @param {number}      fadeOutDuration - Fade out duration in milliseconds
     */
    autoDismiss(notice, delay = 5000, fadeOutDuration = 1000) {
        setTimeout(() => {
            if (notice.parentNode) {
                this.dismissNotice(notice, fadeOutDuration);
            }
        }, delay);
    }

    /**
     * Get CSS class for notice type
     *
     * @param {string} type - The notice type
     * @return {string} - The CSS class name
     */
    getNoticeClass(type) {
        const typeMap = {
            success: 'notice-success',
            error: 'notice-error',
            warning: 'notice-warning',
            info: 'notice-info',
        };

        return typeMap[type] || 'notice-info';
    }

    /**
     * Find the best insertion point for notices
     *
     * @return {HTMLElement|null} - The insertion point element
     */
    findNoticeInsertionPoint() {
        // Try multiple potential insertion points in order of preference
        const selectors = [
            '.npw-notice-insert-point', // Custom insertion point for our notices
            '.wrap .wp-header-end',
            '.wrap .wp-heading-inline',
            '.wrap .page-title-action',
            '.wrap h1',
        ];

        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element) {
                return element;
            }
        }

        // Fallback to body if no suitable insertion point found
        return document.body;
    }

    /**
     * Escape HTML to prevent XSS
     *
     * @param {string} text - The text to escape
     * @return {string} - The escaped HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Clear all notices of a specific type
     *
     * @param {string} type - The notice type to clear (optional)
     */
    clearNotices(type = null) {
        const selector = type ? `.notice-${type}` : '.notice';
        const notices = document.querySelectorAll(selector);

        notices.forEach((notice) => {
            if (notice.parentNode) {
                notice.remove();
            }
        });
    }

    /**
     * Show confirmation dialog
     *
     * @param {string} message - The confirmation message
     * @param {string} title   - The dialog title
     * @return {Promise<boolean>} - Promise that resolves to true if confirmed, false if cancelled
     */
    showConfirmationDialog(message, title = __('Confirm Action', 'nova-post-for-woocommerce')) {
        return new Promise((resolve) => {
            let fullMessage = title ? `${title}\n\n${message}` : message;
            fullMessage =
                `${message}` + `\n\n` + __('Do you want to continue?', 'nova-post-for-woocommerce');
            const result = confirm(fullMessage);
            resolve(result);
        });
    }

    /**
     * Show loading notice
     *
     * @param {string} message - The loading message
     * @return {HTMLElement} - The created loading notice element
     */
    showLoading(message = 'Loading...') {
        const notice = document.createElement('div');
        notice.className = 'notice notice-info is-dismissible npw-fade-in';
        notice.innerHTML = `
      <p>
        <span class="npw-loading-spinner">
          <span class="npw-spinner"></span>
        </span>
        ${this.escapeHtml(message)}
      </p>
    `;

        const insertionPoint = this.findNoticeInsertionPoint();
        if (insertionPoint) {
            insertionPoint.insertAdjacentElement('afterend', notice);
        }

        return notice;
    }

    /**
     * Update loading notice with new message or dismiss it
     *
     * @param {HTMLElement} notice  - The loading notice element
     * @param {string}      message - New message (optional)
     * @param {boolean}     dismiss - Whether to dismiss the notice
     */
    updateLoadingNotice(notice, message = null, dismiss = false) {
        if (!notice || !notice.parentNode) return;

        if (dismiss) {
            this.dismissNotice(notice);
        } else if (message) {
            const messageElement = notice.querySelector('p');
            if (messageElement) {
                messageElement.innerHTML = `
          <span class="npw-loading-spinner">
            <span class="npw-spinner"></span>
          </span>
          ${this.escapeHtml(message)}
        `;
            }
        }
    }
}

const noticeService = new NoticeService();
export default noticeService;
