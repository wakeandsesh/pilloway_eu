import { useCallback, useEffect, useState } from '@wordpress/element';
import apiService from '../../../../services/ApiService.js';

/**
 * Custom Hook to save the division data through the API
 */
export const useDivisionData = () => {
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState(null);

  const saveDivisionData = useCallback(async (payload) => {
    setIsSaving(true);
    setSaveError(null);

    try {
      const response = await apiService.post('/user-storage', {
        division: payload?.division || null,
        division_id: payload?.division_id || null,
        division_type: payload?.division_type || null,
      });

      console.log('Division data saved successfully:', response);
      setIsSaving(false);
      return { success: true, data: response };
    } catch (error) {
      console.error('Error saving division data:', error);
      const errorMessage = error.message || 'Failed to save division data';
      setSaveError(errorMessage);
      setIsSaving(false);
      return { success: false, error: errorMessage };
    }
  }, []);

  return {
    saveDivisionData,
    isSaving,
    saveError,
  };
};
