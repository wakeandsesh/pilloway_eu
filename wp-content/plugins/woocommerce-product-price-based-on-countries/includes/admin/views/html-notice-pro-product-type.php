<?php
/**
 * Admin View: Notice - Pro Product type supported
 *
 * @package WCPBC/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$class = 'show';
if ( ! empty( $type ) ) {
	$class = is_array( $type ) ? 'wc-pbc-show-if-' . implode( ' wc-pbc-show-if-', $type ) : 'wc-pbc-show-if-' . $type;
}
$class .= ' wc-price-based-country-upgrade-' . $utm_source;
?>
<p <?php echo ( empty( $type ) ? '' : 'style="display:none;clear: both;"' ); ?>class="wc-price-based-country-upgrade-notice <?php echo esc_attr( $class ); ?>">
	<?php // Translators: HTML tags. ?>
	<?php printf( esc_html__( '%1$sUpgrade to Price Based on Country Pro to enable compatibility with %2$s%3$s.', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" href="' . esc_url( wcpbc_home_url( $utm_source ) ) . '">', esc_html( $name ), '</a>' ); ?>
</p>
