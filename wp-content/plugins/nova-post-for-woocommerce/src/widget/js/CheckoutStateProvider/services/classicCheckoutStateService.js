/**
 * External dependencies
 */
import apiService from '@/services/ApiService';

export function getClassicCheckoutState() {
    return apiService.get('/classic-checkout-state');
}
