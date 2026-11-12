import { __ } from "@wordpress/i18n";
import { useEffect, useRef, useState, useCallback } from "@wordpress/element";
import { useDebounce } from "@wordpress/compose";

import { getLocale } from "@/utils";

import styles from "./styles.module.scss";

export const DivisionSelectionMap = ({
  className,
  country,
  city,
  show,
  divisionType,
  divisionCategories,
  currentDivision,
  onSelect,
}) => {
  const containerRef = useRef(null);
  const widgetRef = useRef(null);
  const onSelectRef = useRef(onSelect);
  const [isReady, setIsReady] = useState(false);
  const [sdkError, setSdkError] = useState(false);

  const isSDKAvailable = window && window?.NovaPostWidget;

  const isVisible = Boolean(className && className.includes("--active"));

  useEffect(() => {
    onSelectRef.current = onSelect;
  }, [onSelect]);

  const handleSelect = useCallback((sdkDivision) => {
    onSelectRef.current?.(sdkDivision);
  }, []);

  // Create the SDK widget once it first becomes visible. The widget registers a
  // global `window` "message" listener that is shared by every instance, so it
  // must be created exactly once per mount and torn down on unmount via
  // `destroy()` — otherwise leaked instances keep reacting to iframe messages
  // (duplicate `onReady`/`onSelect` calls, selection loops).
  useEffect(() => {
    if (!isVisible || widgetRef.current || !isSDKAvailable) {
      return;
    }

    try {
      const locale = getLocale();

      const NovaPostWidget = window.NovaPostWidget.NovaPostWidget;

      widgetRef.current = new NovaPostWidget({
        container: containerRef.current,
        locale: locale.slice(0, 2).toLowerCase(),
        country,
        city,
        divisionId: currentDivision?.id,
        divisionCategories,
        onSelect: handleSelect,
        onReady: () => {
          console.log("[NovaPostWidget SDK] Widget ready");
          setIsReady(true);
        },
        onError: (error) => {
          console.error("[NovaPostWidget SDK] Error:", error);
        },
      });
    } catch (error) {
      console.error("[NovaPostWidget SDK] Initialization error:", error);
      setSdkError(true);
    }
    // Initial params are read on creation; later changes are pushed through the
    // `updateParams` effect below, so they intentionally stay out of the deps.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isVisible, isSDKAvailable]);

  // Destroy the SDK widget on unmount to remove its global message/resize
  // listeners and iframe (prevents leaks across cart-refresh remounts).
  useEffect(() => {
    return () => {
      if (widgetRef.current) {
        widgetRef.current.destroy();
        widgetRef.current = null;
      }
    };
  }, []);

  const debouncedUpdateParams = useDebounce(
    useCallback(
      (params) => {
        if (widgetRef.current && isReady) {
          widgetRef.current.updateParams(params);
          console.log("[NovaPostWidget SDK] Updated params:", params);
        }
      },
      [isReady],
    ),
    500,
  );

  useEffect(() => {
    if (!isReady || !widgetRef.current) {
      return;
    }

    debouncedUpdateParams({
      country,
      city,
      divisionId: currentDivision?.id || null,
      divisionCategories,
    });
  }, [country, city, divisionCategories, currentDivision?.id, debouncedUpdateParams]);

  useEffect(() => {
    if (widgetRef.current) {
      if (show) {
        widgetRef.current.show();
        console.log("[NovaPostWidget SDK] Widget shown");
      } else {
        widgetRef.current.hide();
        console.log("[NovaPostWidget SDK] Widget hidden");
      }
    }
  }, [show]);

  if (!isSDKAvailable || sdkError) {
    return (
      <div className={`${styles["map-wrapper"]} ${className}`}>
        <div className={styles["error-message"]}>
          {__(
            "Could not load map. Please use the search in the list.",
            "nova-post-for-woocommerce",
          )}
        </div>
      </div>
    );
  }

  return <div ref={containerRef} className={`${styles["map-wrapper"]} ${className}`} />;
};
