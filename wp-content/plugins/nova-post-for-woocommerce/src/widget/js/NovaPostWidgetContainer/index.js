/**
 * Internal dependencies
 */
import { NovaPostWidget, NoticeMessage } from '../NovaPostWidget';
import { useNoticeMessage } from '../NovaPostWidgetBlock/hooks/useNoticeMessage';
import { useCheckoutState } from '../CheckoutStateProvider/useCheckoutState';
import { useDivisionData } from './useDivisionData';
import { useDivisionSelection } from './useDivisionSelection';

const emptyShippingAddress = {
    country: '',
    state: '',
    postcode: '',
    city: '',
    address_1: '',
    address_2: '',
};

export function NovaPostWidgetContainer() {
    const {
        shippingAddress,
        senderCountry,
        divisionData,
        divisionFilters,
        errorCalculation,
        isNovaPostDivisionActive,
    } = useCheckoutState();
    const { saveDivisionData, isSaving, saveError } = useDivisionData();
    const noticeMessage = useNoticeMessage(errorCalculation);

    const resolvedShippingAddress = shippingAddress || emptyShippingAddress;

    const { currentDivision, handleChangeDivision, handleClearDivision } = useDivisionSelection({
        shippingAddress: resolvedShippingAddress,
        divisionData,
        saveDivisionData,
    });

    return (
        <>
            <NoticeMessage message={noticeMessage} />
            {isNovaPostDivisionActive && (
                <NovaPostWidget
                    shippingAddress={resolvedShippingAddress}
                    senderCountry={senderCountry}
                    divisionsFilters={divisionFilters}
                    currentDivision={currentDivision}
                    onSelect={handleChangeDivision}
                    onClear={handleClearDivision}
                    isSaving={isSaving}
                    saveError={saveError}
                />
            )}
        </>
    );
}
