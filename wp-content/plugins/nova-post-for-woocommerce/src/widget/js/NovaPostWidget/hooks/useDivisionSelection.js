import { useState, useEffect, useCallback, useMemo } from "@wordpress/element";
import { useDivisionSearch } from "@/hooks/useDivisionSearch";
import { __ } from "@wordpress/i18n";

/**
 * Caste Hook to manage the choice of department
 */
export const useDivisionSelection = ({ division, country, city, divisionCategories }) => {
  const { divisions, isLoading, searchDivisions } = useDivisionSearch();
  const [localDivisions, setLocalDivisions] = useState([]);
  const [error, setError] = useState(null);

  const isValidParams = () => {
    if (!country || !divisionCategories) {
      return false;
    }
    if (!Array.isArray(divisionCategories) || divisionCategories.length === 0) {
      return false;
    }
    return true;
  };

  useEffect(() => {
    if (!isValidParams()) {
      setError(
        __(
          "Missing required parameters: country or divisionCategories",
          "nova-post-for-woocommerce",
        ),
      );
    }

    setLocalDivisions([]);
  }, [country, divisionCategories]);

  useEffect(() => {
    if (division) {
      setLocalDivisions([division]);
    }
  }, [division]);

  useEffect(() => {
    setLocalDivisions(divisions);

    setError(null);
  }, [divisions]);

  const handleSearch = useCallback(
    async (query) => {
      if (!isValidParams()) {
        return;
      }

      try {
        if (!query.trim()) {
          if (division) {
            setLocalDivisions([division]);
          } else {
            setLocalDivisions([]);
          }
          setError(null);
          return;
        }

        await searchDivisions({
          countryCode: country,
          city,
          query,
          divisionCategories,
        });

        setError(null);
      } catch (err) {
        console.error("Error searching divisions:", err);
        setError(__("Failed to search divisions. Please try again.", "nova-post-for-woocommerce"));
        setLocalDivisions([]);
      }
    },
    [country, city, divisionCategories, division, searchDivisions, isValidParams],
  );

  const handleClear = () => {
    try {
      setLocalDivisions([]);
      setError(null);
    } catch (err) {
      console.error("Error clearing division:", err);
      setError(__("Failed to clear selection.", "nova-post-for-woocommerce"));
    }
  };

  return {
    localDivisions,
    isLoading,
    error,
    handleSearch,
    handleClear,
    isValidParams,
  };
};
