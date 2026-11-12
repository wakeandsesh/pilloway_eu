/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { __experimentalInputControl as InputControl, Button } from '@wordpress/components';
import { useState, useCallback } from '@wordpress/element';
import { trash } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { useFieldErrorIndexed } from '../../hooks/useFieldError';
import styles from './styles.module.scss';

/**
 * Single custom box row with per-field error highlighting.
 *
 * @param {Object}   props
 * @param {Object}   props.box           - Box data { id, width, length, height }.
 * @param {number}   props.index         - Row index in the boxes array.
 * @param {boolean}  props.showDelete    - Whether to render the delete button.
 * @param {Function} props.onFieldChange - Called with (id, field, value).
 * @param {Function} props.onRemove      - Called with (id).
 */
const CustomBoxRow = ({ box, index, showDelete, onFieldChange, onRemove }) => {
    const lengthError = useFieldErrorIndexed('np_custom_boxes', index, 'length');
    const widthError = useFieldErrorIndexed('np_custom_boxes', index, 'width');
    const heightError = useFieldErrorIndexed('np_custom_boxes', index, 'height');

    const ruleValidBox = (item) =>
        item.width !== '' &&
        Number(item.width) > 0 &&
        item.length !== '' &&
        Number(item.length) > 0 &&
        item.height !== '' &&
        Number(item.height) > 0;

    return (
        <div>
            <div className={styles.customBoxRow}>
                <div className={styles.boxLabel}>
                    {
                        // translators: %d: box number
                        sprintf(__('Box %d', 'nova-post-for-woocommerce'), index + 1)
                    }
                </div>

                <InputControl
                    className={lengthError.wrapperClass}
                    label={__('L', 'nova-post-for-woocommerce')}
                    labelPosition="side"
                    value={box.length}
                    onChange={(val) => onFieldChange(box.id, 'length', val)}
                    type="number"
                    min="0"
                    step="1"
                    placeholder={__('cm', 'nova-post-for-woocommerce')}
                    __next40pxDefaultSize
                    help={lengthError.help}
                />

                <InputControl
                    className={widthError.wrapperClass}
                    label={__('W', 'nova-post-for-woocommerce')}
                    labelPosition="side"
                    value={box.width}
                    onChange={(val) => onFieldChange(box.id, 'width', val)}
                    type="number"
                    min="0"
                    step="1"
                    placeholder={__('cm', 'nova-post-for-woocommerce')}
                    __next40pxDefaultSize
                    help={widthError.help}
                />

                <InputControl
                    className={heightError.wrapperClass}
                    label={__('H', 'nova-post-for-woocommerce')}
                    labelPosition="side"
                    value={box.height}
                    onChange={(val) => onFieldChange(box.id, 'height', val)}
                    type="number"
                    min="0"
                    step="1"
                    placeholder={__('cm', 'nova-post-for-woocommerce')}
                    __next40pxDefaultSize
                    help={heightError.help}
                />

                <div className={styles.deleteButtonContainer}>
                    {showDelete && (
                        <Button
                            className={styles.deleteButton}
                            icon={trash}
                            onClick={() => onRemove(box.id)}
                            label={__('Remove box', 'nova-post-for-woocommerce')}
                        />
                    )}
                </div>

                {!ruleValidBox(box) && (
                    <p className={styles.errorMessageBox}>
                        {__(
                            'All dimensions are required for each box and must be positive numbers.',
                            'nova-post-for-woocommerce'
                        )}
                    </p>
                )}
            </div>
        </div>
    );
};

const CustomBoxManager = ({ value = [], onChange }) => {
    const [boxes, setBoxes] = useState(() => {
        if (value && value.length > 0) {
            return value.map((box, index) => ({
                width: box.width || box.w || '',
                length: box.length || box.l || '',
                height: box.height || box.h || '',
                id: Date.now() + index,
            }));
        }
        return [{ width: '', length: '', height: '', id: Date.now() }];
    });

    const ruleValidBox = (item) =>
        item.width !== '' &&
        Number(item.width) > 0 &&
        item.length !== '' &&
        Number(item.length) > 0 &&
        item.height !== '' &&
        Number(item.height) > 0;
    const ruleLimitBoxes = boxes.length >= 10;

    const hasValidBoxes = boxes.some(ruleValidBox);

    const updateGlobalSettings = useCallback(
        (currentBoxes) => {
            const normalizedBoxes = currentBoxes.map(({ width, length, height }) => ({
                width: Number(width),
                length: Number(length),
                height: Number(height),
            }));

            onChange(normalizedBoxes);
        },
        [onChange]
    );

    const handleBoxChange = (id, field, newValue) => {
        const updatedBoxes = boxes.map((box) => {
            if (box.id === id) {
                return { ...box, [field]: Number(newValue) };
            }
            return box;
        });
        setBoxes(updatedBoxes);
        updateGlobalSettings(updatedBoxes);
    };

    const addBox = () => {
        if (ruleLimitBoxes) return;
        const newBoxes = [...boxes, { width: '', length: '', height: '', id: Date.now() }];
        setBoxes(newBoxes);
    };

    const removeBox = (id) => {
        const newBoxes = boxes.filter((box) => box.id !== id);
        setBoxes(newBoxes);
        updateGlobalSettings(newBoxes);
    };

    return (
        <div className={styles.customBoxesContainer}>
            {boxes.map((box, index) => (
                <CustomBoxRow
                    key={box.id}
                    box={box}
                    index={index}
                    showDelete={boxes.length > 1}
                    onFieldChange={handleBoxChange}
                    onRemove={removeBox}
                />
            ))}

            <div className={styles.footer}>
                <Button
                    variant="secondary"
                    onClick={addBox}
                    className={styles.addBoxButton}
                    icon="plus"
                    disabled={ruleLimitBoxes}
                >
                    {__('Add Box', 'nova-post-for-woocommerce')}
                </Button>

                <div className={styles.messages}>
                    {!hasValidBoxes && (
                        <p className={styles.errorMessage}>
                            {__(
                                'At least one custom box with valid dimensions is required.',
                                'nova-post-for-woocommerce'
                            )}
                        </p>
                    )}

                    <p className={styles.limitMessage}>
                        {__(
                            'You can define up to 10 custom boxes. At least one box is required.',
                            'nova-post-for-woocommerce'
                        )}
                    </p>
                </div>
            </div>
        </div>
    );
};

export default CustomBoxManager;
