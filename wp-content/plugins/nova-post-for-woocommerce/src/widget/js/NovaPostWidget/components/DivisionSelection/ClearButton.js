import styles from './styles.module.scss';

export const ClearButton = ({ onClick }) => {
  return (
    <button
      className={styles['division-input__clear']}
      title="Clear"
      onClick={onClick}
      type="button"
    >
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="1.25" y="1.25" width="13.5" height="13.5" rx="6.75" fill="currentColor" />
        <path d="M11.0605 6L9.06055 8L11.0605 10L10 11.0605L8 9.06055L6 11.0605L4.93945 10L6.93945 8L4.93945 6L6 4.93945L8 6.93945L10 4.93945L11.0605 6Z" fill="white" />
      </svg>
    </button>
  )
}