import { useMemo } from '@wordpress/element';
import styles from './styles.module.scss';

// Function to highlight search string in text
const highlightText = (text, search) => {
  if (!text || !search) return text;

  const searchLower = search.toLowerCase();
  const textLower = text.toLowerCase();
  const index = textLower.indexOf(searchLower);

  if (index === -1) return text;

  const before = text.substring(0, index);
  const match = text.substring(index, index + search.length);
  const after = text.substring(index + search.length);

  return (
    <>
      {before}
      <span style={{ color: 'var(--color-accent)' }}>{match}</span>
      {after}
    </>
  );
};

export const DivisionItem = ({ name, address, search, onClick, onFocus, isFocused = false, itemId }) => {
  const highlightedName = useMemo(() => highlightText(name, search), [name, search]);
  const highlightedAddress = useMemo(() => highlightText(address, search), [address, search]);

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onClick();
    }
  };

  const handleFocus = () => {
    if (onFocus) {
      onFocus();
    }
  };

  return (
    <div
      className={styles['autocomplete-item']}
      onClick={onClick}
      onKeyDown={handleKeyDown}
      onFocus={handleFocus}
      tabIndex={0}
      role="option"
      aria-selected={isFocused}
      id={itemId}
    >
      <div className={styles['autocomplete-item__name']}>{highlightedName}</div>
      {address && (
        <div className={styles['autocomplete-item__address']}>{highlightedAddress}</div>
      )}
    </div>
  );
};
