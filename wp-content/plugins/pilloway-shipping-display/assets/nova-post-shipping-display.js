(function () {
	'use strict';

	var metadataKey = 'pilloway_shipping_display_mode';
	var carrierMode = 'carrier_rates';
	var scheduled = false;

	function getShippingRates(cartData) {
		var packages = cartData && Array.isArray(cartData.shippingRates) ? cartData.shippingRates : [];
		var rates = [];

		for (var packageIndex = 0; packageIndex < packages.length; packageIndex++) {
			var packageRates = Array.isArray(packages[packageIndex].shipping_rates) ? packages[packageIndex].shipping_rates : [];
			for (var rateIndex = 0; rateIndex < packageRates.length; rateIndex++) {
				rates.push(packageRates[rateIndex]);
			}
		}

		return rates;
	}

	function isCarrierRate(rate) {
		var metadata = Array.isArray(rate.meta_data) ? rate.meta_data : [];
		for (var index = 0; index < metadata.length; index++) {
			if (metadata[index].key === metadataKey && metadata[index].value === carrierMode) {
				return true;
			}
		}

		return false;
	}

	function clearCarrierClasses() {
		var marked = document.querySelectorAll('.pilloway-nova-post-carrier-rate');
		for (var index = 0; index < marked.length; index++) {
			marked[index].classList.remove('pilloway-nova-post-carrier-rate');
		}
	}

	function getRateOption(input) {
		return input.closest(
			'.wc-block-components-radio-control__option, ' +
			'.wc-block-components-radio-control-accordion-option, ' +
			'.wc-block-checkout__shipping-option'
		) || input.closest('label');
	}

	function updateDisplay() {
		var store = window.wp.data.select('wc/store/cart');
		if (!store || typeof store.getCartData !== 'function') {
			return;
		}

		var cartData = store.getCartData();
		var rates = getShippingRates(cartData);
		var carrierRateIds = Object.create(null);
		var selectedRates = [];

		for (var rateIndex = 0; rateIndex < rates.length; rateIndex++) {
			var rate = rates[rateIndex];
			if (isCarrierRate(rate) && rate.rate_id) {
				carrierRateIds[String(rate.rate_id)] = true;
			}
			if (rate.selected) {
				selectedRates.push(rate);
			}
		}

		clearCarrierClasses();

		var rateInputs = document.querySelectorAll('input[type="radio"]');
		for (var inputIndex = 0; inputIndex < rateInputs.length; inputIndex++) {
			var input = rateInputs[inputIndex];
			if (carrierRateIds[String(input.value)]) {
				var option = getRateOption(input);
				if (option) {
					option.classList.add('pilloway-nova-post-carrier-rate');
				}
			}
		}

		var allSelectedRatesUseCarrierPricing = selectedRates.length > 0;
		for (var selectedIndex = 0; selectedIndex < selectedRates.length; selectedIndex++) {
			if (!isCarrierRate(selectedRates[selectedIndex])) {
				allSelectedRatesUseCarrierPricing = false;
				break;
			}
		}

		var shippingTotals = document.querySelectorAll('.wc-block-components-totals-shipping');
		for (var totalsIndex = 0; totalsIndex < shippingTotals.length; totalsIndex++) {
			if (allSelectedRatesUseCarrierPricing) {
				shippingTotals[totalsIndex].classList.add('pilloway-nova-post-carrier-rate');
			}
		}
	}

	function scheduleUpdate() {
		if (scheduled) {
			return;
		}
		scheduled = true;
		window.requestAnimationFrame(function () {
			scheduled = false;
			updateDisplay();
		});
	}

	function initialize() {
		if (!window.wp || !window.wp.data || typeof window.wp.data.subscribe !== 'function') {
			return;
		}

		window.wp.data.subscribe(scheduleUpdate);

		if (window.MutationObserver && document.body) {
			var observer = new MutationObserver(scheduleUpdate);
			observer.observe(document.body, { childList: true, subtree: true });
		}

		scheduleUpdate();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
})();
