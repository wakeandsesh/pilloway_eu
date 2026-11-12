import { memo } from "@wordpress/element";
import { useDebounce } from "@wordpress/compose";

import { DivisionAutocomplete } from "../DivisionSelection";
import { useDivisionSelection } from "../../hooks/useDivisionSelection";
import { NoticeMessage } from "../NoticeMessage";

/**
 * General component for the selection of departments
 * Used for branches and posts
 */
export const DivisionSelectionWrapper = memo(
  ({ division, country, city, divisionCategories, label, onChange, onClear }) => {
    const { localDivisions, isLoading, error, handleSearch, handleClear, isValidParams } =
      useDivisionSelection({ division, country, city, divisionCategories });

    // If the parameters are universal, do not display the component
    if (!isValidParams()) {
      return (
        <NoticeMessage
          message={
            error || "Unable to load division selection. Please check your shipping address."
          }
        />
      );
    }

    const onSearch = useDebounce((value) => {
      handleSearch(value);
    }, 500);

    const handleClearAction = () => {
      onClear(null);
      handleClear();
    };

    return (
      <div className="nova-post-widget__division-wrapper">
        <DivisionAutocomplete
          divisions={localDivisions}
          isLoading={isLoading}
          onSearch={onSearch}
          onSelect={onChange}
          onClear={handleClearAction}
          label={label}
          selectedDivision={division}
        />
        <NoticeMessage message={error} />
      </div>
    );
  },
);
