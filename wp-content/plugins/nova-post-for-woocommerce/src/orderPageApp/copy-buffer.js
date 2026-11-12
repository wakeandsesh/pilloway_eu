export const handleClipboardClickPickupStatus = (event) => {
    const target = event.target.closest('.npw-status-pickup-icon');

    if (!target || target.getAttribute('data-status-pickup') !== 'added') {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    const beforeCopy = () => {
        target.classList.add('pickup-copy-success');
        setTimeout(() => {
            target.classList.remove('pickup-copy-success');
        }, 2500);
    };

    const getPickupNumber = (text) => {
        let result = null;

        const hashIndex = text.indexOf('#');
        if (hashIndex !== -1) {
            result = text.substring(hashIndex + 1);
        }

        return result;
    };

    const tooltipEl = target.querySelector('[data-tooltip]');
    const tooltipText = tooltipEl ? tooltipEl.getAttribute('data-tooltip') : null;

    if (tooltipText) {
        const pickupNumber = getPickupNumber(tooltipText);

        if (!pickupNumber) {
            return;
        }

        navigator.clipboard
            .writeText(pickupNumber)
            .then(() => {
                beforeCopy();
            })
            .catch((err) => {
                console.error('Failed to copy text: ', err);
            });
    }
};
