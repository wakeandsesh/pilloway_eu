/**
 * Internal dependencies
 */
import { ClassicCheckoutAdapter } from '../CheckoutStateProvider/adapters/classic';
import { CheckoutStateProvider } from '../CheckoutStateProvider/context';
import { NovaPostWidgetContainer } from '../NovaPostWidgetContainer';

export function NovaPostWidgetClassicCheckout() {
    return (
        <CheckoutStateProvider>
            <ClassicCheckoutAdapter />
            <NovaPostWidgetContainer />
        </CheckoutStateProvider>
    );
}
