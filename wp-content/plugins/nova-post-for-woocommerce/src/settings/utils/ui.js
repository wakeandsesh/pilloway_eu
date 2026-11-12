export const setHiddenState = (element, isHidden) => {
    if (!element) return;

    if (isHidden) {
        element.setAttribute('hidden', '');
    } else {
        element.removeAttribute('hidden');
    }
};

export const setLoadingState = (element, isLoading) => {
    if (!element) return;

    if (isLoading) {
        element.setAttribute('loading', '');
    } else {
        element.removeAttribute('loading');
    }
};

export const setDisabledState = (element, isDisabled) => {
    if (!element) return;

    element.disabled = isDisabled;
};
