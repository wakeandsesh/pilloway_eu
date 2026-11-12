/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCallback, useRef, useState } from '@wordpress/element';
import { __experimentalInputControl as InputControl, Spinner } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';

import { useDivisionSearch } from '@/hooks/useDivisionSearch.js';
import { DIVISION_TYPE_TO_CATEGORIES, ALL_DIVISION_CATEGORIES } from '@/constants';

/**
 * Internal dependencies
 */
import { DivisionItem } from './DivisionItem';

import styles from './styles.module.scss';

const SelectDivision = ({
    countryCode,
    city,
    type = '',
    prohibitedSending,
    prohibitedIssuance,
    presetDivision,
    onDivisionSelect,
    label = '',
    placeholder = __('Enter division name', 'nova-post-for-woocommerce'),
    help = '',
    inputClasses = '',
    loadingMessage = __('Loading divisions…', 'nova-post-for-woocommerce'),
    emptyMessage = __('No divisions found', 'nova-post-for-woocommerce'),
    errorMessage = __('Error loading divisions', 'nova-post-for-woocommerce'),
}) => {
    const [query, setQuery] = useState(presetDivision?.name || '');
    const [showDropdown, setShowDropdown] = useState(false);
    const [skipSearch, setSkipSearch] = useState(false);
    const inputRef = useRef(null);

    const {
        error,
        divisions,
        isLoading: isLoadingDivisions,
        searchDivisions,
    } = useDivisionSearch();

    const debouncedSearch = useDebounce(
        useCallback(
            (searchQuery) => {
                if (searchQuery && !skipSearch) {
                    searchDivisions({
                        countryCode,
                        city,
                        query: searchQuery,
                        divisionCategories:
                            DIVISION_TYPE_TO_CATEGORIES?.[type] || ALL_DIVISION_CATEGORIES,
                        ...(typeof prohibitedSending === 'boolean' && { prohibitedSending }),
                        ...(typeof prohibitedIssuance === 'boolean' && { prohibitedIssuance }),
                    });
                    setShowDropdown(true);
                }
            },
            [
                countryCode,
                city,
                prohibitedIssuance,
                prohibitedSending,
                searchDivisions,
                skipSearch,
                type,
            ]
        ),
        500
    );

    const handleInputChange = (value) => {
        setSkipSearch(false);
        debouncedSearch(value);
    };

    const handleSelection = (division) => {
        setSkipSearch(true);
        setQuery(division.name);
        setShowDropdown(false);

        if (onDivisionSelect) {
            onDivisionSelect(division);
        }

        // Allow quick subsequent searches after selection
        setTimeout(() => setSkipSearch(false), 500);
    };

    const handleInputFocus = () => {
        if (divisions.length > 0 && query) {
            setShowDropdown(true);
        }
    };

    const handleInputBlur = () => {
        // Delay hiding dropdown to allow clicks on items
        setTimeout(() => setShowDropdown(false), 200);
    };

    return (
        <div className={styles['division-container']}>
            <InputControl
                className={inputClasses}
                key={query}
                ref={inputRef}
                __next40pxDefaultSize
                name="sender_division_name"
                label={label}
                value={query}
                onChange={handleInputChange}
                onFocus={handleInputFocus}
                onBlur={handleInputBlur}
                placeholder={placeholder}
                help={help}
            />

            {showDropdown && (
                <div className={styles['division-dropdown']}>
                    {isLoadingDivisions && (
                        <div className={styles['division-loading']}>
                            <Spinner />
                            {loadingMessage}
                        </div>
                    )}

                    {!isLoadingDivisions && error && (
                        <div className={styles['division-error']}>
                            {error?.message || errorMessage}
                        </div>
                    )}

                    {!isLoadingDivisions && !error && divisions.length === 0 && query && (
                        <div className={styles['division-empty']}>{emptyMessage}</div>
                    )}

                    {!isLoadingDivisions && divisions.length > 0 && (
                        <div className={styles['division-list']}>
                            {divisions.map((division) => (
                                <div
                                    key={division.id}
                                    className={styles['division-item']}
                                    role="button"
                                    tabIndex={0}
                                    onClick={() => handleSelection(division)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') {
                                            event.preventDefault();
                                            handleSelection(division);
                                        }
                                    }}
                                    onMouseDown={(event) => event.preventDefault()}
                                >
                                    <DivisionItem
                                        item={{
                                            value: division.id,
                                            label: division.name,
                                            address: division.address,
                                        }}
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default SelectDivision;
