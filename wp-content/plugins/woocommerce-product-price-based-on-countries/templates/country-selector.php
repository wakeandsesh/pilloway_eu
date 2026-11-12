<?php
/**
 * The template for displaying the country switcher
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-product-price-based-on-countries/country-selector.php.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WCPBC/Templates
 * @version 1.8.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( empty( $countries ) ) {
	return;
}

add_action( 'wp_footer', [ 'WCPBC_Widget_Country_Selector', 'country_switcher_form' ], 5 );
?>
<select form="wcpbc-widget-country-switcher-form" onchange="document.getElementById('wcpbc-widget-country-switcher-input').value=this.value;this.form.submit();" class="wcpbc-country-switcher country-switcher wp-exclude-emoji" aria-label="<?php echo esc_attr( $label ); ?>">
	<?php foreach ( $countries as $country_code => $country_name ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals ?>
		<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( $country_code, $selected_country ); ?> class="wp-exclude-emoji">
			<?php if ( $show_flags && ( $other_country_id !== $country_code || $remove_other_countries ) ) : ?>
				<?php echo esc_html( WCPBC_Country_Flags::get_by_country( $country_code ) ); ?>&nbsp;
			<?php endif; ?>
			<?php echo esc_html( $country_name ); ?>
		</option>
	<?php endforeach; ?>
</select>
