/**
 * Auto-submits the WayForPay redirect form on the order-pay screen.
 * The form keeps a visible submit button, so the payment still works
 * when JavaScript is unavailable.
 */
( function () {
	const form = document.getElementById( 'catcode-wayforpay-form' );
	if ( form ) {
		form.submit();
	}
} )();
