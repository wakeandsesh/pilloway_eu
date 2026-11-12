/**
 * Internal dependencies
 */
import { BlocksCheckoutAdapter } from '../CheckoutStateProvider/adapters/blocks';
import { CheckoutStateProvider } from '../CheckoutStateProvider/context';
import { NovaPostWidgetContainer } from '../NovaPostWidgetContainer';

export const NovaPostWidgetBlock = () => {
    return (
        <CheckoutStateProvider>
            <BlocksCheckoutAdapter />
            <NovaPostWidgetContainer />
        </CheckoutStateProvider>
    );
};
