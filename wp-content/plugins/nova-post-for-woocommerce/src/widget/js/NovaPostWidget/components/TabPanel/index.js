import { memo, useCallback } from '@wordpress/element';
import styles from './styles.module.scss';

export const TabPanel = memo(({ items, onChange, activeTab }) => {
  const getClasses = useCallback((item) => [
    styles['tab-button'],
    item.value === activeTab && styles['--active']
  ].filter(Boolean).join(' '), [activeTab]);

  return (
    <div className={styles['tabs-container']}>
      {items.map((item) => (
        <button
          key={item.value}
          type="button"
          className={getClasses(item)}
          onClick={() => onChange(item.value)}
        >
          { item.label }
        </button>
      ))}
    </div>
  );
});