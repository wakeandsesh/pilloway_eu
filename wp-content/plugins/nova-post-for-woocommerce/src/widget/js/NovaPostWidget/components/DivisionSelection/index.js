import { useState, useEffect, useRef } from '@wordpress/element';

import styles from './styles.module.scss';
import { DivisionItem } from './DivisionItem';
import { ClearButton } from './ClearButton';
import { __ } from '@wordpress/i18n';

export const DivisionAutocomplete = ({
  divisions = [],
  isLoading = false,
  onSearch,
  onSelect,
  onClear,
  label = 'Nova Poshta Division',
  selectedDivision = null,
}) => {
  const [inputValue, setInputValue] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const [focusedIndex, setFocusedIndex] = useState(-1);
  const inputRef = useRef(null);
  const listRef = useRef(null);
  const listId = `division-list-${Math.random().toString(36).substr(2, 9)}`;

  // Обновляем значение поля при выборе отделения
  useEffect(() => {
    setInputValue(selectedDivision?.name || selectedDivision?.address || '');
  }, [selectedDivision]);

  // Сброс фокуса при закрытии списка
  useEffect(() => {
    if (!isOpen) {
      setFocusedIndex(-1);
    }
  }, [isOpen]);

  // Прокрутка к выбранному элементу при навигации с клавиатуры
  useEffect(() => {
    if (focusedIndex >= 0 && listRef.current) {
      const focusedElement = listRef.current.querySelector(`#${listId}-item-${focusedIndex}`);
      if (focusedElement) {
        focusedElement.scrollIntoView({
          block: 'nearest',
          behavior: 'smooth'
        });
      }
    }
  }, [focusedIndex, listId]);

  const handleInputChange = (e) => {
    const newValue = e.target.value;
    setInputValue(newValue);
    setIsOpen(true);
    setFocusedIndex(-1);

    if (onSearch) {
      onSearch(newValue);
    }
  };

  const handleInputKeyDown = (e) => {
    if (!isOpen || divisions.length === 0) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setFocusedIndex(prev => {
          const newIndex = prev < 0 ? 0 : Math.min(prev + 1, divisions.length - 1);
          return newIndex;
        });
        break;
      case 'ArrowUp':
        e.preventDefault();
        setFocusedIndex(prev => {
          const newIndex = prev <= 0 ? divisions.length - 1 : Math.max(prev - 1, 0);
          return newIndex;
        });
        break;
      case 'Enter':
        e.preventDefault();
        if (focusedIndex >= 0 && focusedIndex < divisions.length) {
          handleDivisionSelect(divisions[focusedIndex]);
        }
        break;
      case 'Tab':
        // Allow Tab to move to first list item if no item is focused
        if (focusedIndex < 0 && divisions.length > 0) {
          e.preventDefault();
          setFocusedIndex(0);
          // Focus the first list item
          setTimeout(() => {
            const firstItem = listRef.current?.querySelector(`#${listId}-item-0`);
            if (firstItem) {
              firstItem.focus();
            }
          }, 0);
        }
        break;
      case 'Escape':
        e.preventDefault();
        setIsOpen(false);
        setFocusedIndex(-1);
        break;
    }
  };

  const handleDivisionSelect = (division) => {
    setInputValue(division.name || division.address || '');
    setIsOpen(false);
    setFocusedIndex(-1);

    if (onSelect) {
      onSelect(division);
    }
  };

  const handleClear = () => {
    setInputValue('');
    setIsOpen(false);
    setFocusedIndex(-1);

    if (onClear) {
      onClear();
    }
  };

  const handleInputFocus = () => {
    if (divisions.length > 0) {
      setIsOpen(true);
    }
  };

  const handleClickOutside = (e) => {
    if (
      (!listRef.current || !listRef.current.contains(e.target)) &&
      (!inputRef.current || !inputRef.current.contains(e.target))
    ) {
      setIsOpen(false);
      setFocusedIndex(-1);
    }
  };

  useEffect(() => {
    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, []);

  const listClasses = `${styles['autocomplete-list']} ${isOpen ? styles['autocomplete-list--open'] : ''}`;

  return (
    <div className={styles["division-autocomplete"]}>
      <div className={styles["division-input__wrapper"]}>
        <input
          ref={inputRef}
          className={styles["division-input"]}
          type="text"
          id="division-input"
          placeholder=" "
          value={inputValue}
          onChange={handleInputChange}
          onKeyDown={handleInputKeyDown}
          onFocus={handleInputFocus}
          role="combobox"
          aria-expanded={isOpen}
          aria-controls={isOpen ? listId : undefined}
          aria-activedescendant={focusedIndex >= 0 ? `${listId}-item-${focusedIndex}` : undefined}
          aria-autocomplete="list"
        />
        <label className={styles["division-input__label"]} htmlFor="division-input">
          {label}
        </label>
        {isLoading && (
          <div className={styles['division-input__loader']} style={{ display: 'block' }}></div>
        )}
        {inputValue && (
          <ClearButton onClick={handleClear} />
        )}
      </div>

      {divisions.length > 0 && (
        <div
          ref={listRef}
          className={listClasses}
          id={listId}
          role="listbox"
          aria-label="Division options"
        >
          {divisions.map((division, index) => (
            <DivisionItem
              key={division.id}
              name={division.name}
              address={division.address}
              search={inputValue}
              onClick={() => handleDivisionSelect(division)}
              onFocus={() => setFocusedIndex(index)}
              isFocused={index === focusedIndex}
              itemId={`${listId}-item-${index}`}
            />
          ))}
        </div>
      )}
      {isOpen && divisions.length === 0 && !isLoading && (
        <div className={listClasses}>
          <div className={styles['autocomplete-item'] }>{__('No divisions found', 'nova-post-for-woocommerce') }</div>
        </div>
      )}
    </div>
  );
};
