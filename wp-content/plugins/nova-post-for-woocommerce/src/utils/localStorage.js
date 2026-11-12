export const localStorageIsAvailable = () => {
    return typeof window !== 'undefined' && window.localStorage !== undefined;
};

export const createLocalStorageKey = (prefix, key) => `${prefix}:${key}`;
