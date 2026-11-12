export const DIVISION_TYPE = Object.freeze({
    BRANCH: 'branch',
    POSTMAT: 'postomat',
});

export const DIVISION_CATEGORIES = Object.freeze({
    POST_BRANCH: 'PostBranch',
    POSTOMAT: 'Postomat',
    CARGO_BRANCH: 'CargoBranch',
    PUDO: 'PUDO',
});

export const DIVISION_TYPE_TO_CATEGORIES = Object.freeze({
    [DIVISION_TYPE.BRANCH]: [
        DIVISION_CATEGORIES.POST_BRANCH,
        DIVISION_CATEGORIES.CARGO_BRANCH,
        DIVISION_CATEGORIES.PUDO,
    ],
    [DIVISION_TYPE.POSTMAT]: [DIVISION_CATEGORIES.POSTOMAT],
});

export const ALL_DIVISION_CATEGORIES = Object.values(DIVISION_CATEGORIES);
