<?php
/**
 * Admin View: Product Add-ons.
 *
 * @package WCPBC/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<table style="display:none;">
	<tr id="wcpbc-addon-pricing">
		<th>
			<label for="wcpbc-addons"><?php esc_html_e( 'Add-ons zone pricing', 'woocommerce-product-price-based-on-countries' ); ?></label>
		</th>
		<td class="postbox">
			<?php echo wp_kses_post( $get_pro ); ?>
		</td>
	</tr>
</table>
<script type="text/javascript">
	jQuery(document).ready(function($){
		// Insert addon pricing
		$('#poststuff').closest('tr').after('<tr id="clear"><th></th><td></td></tr>')
		$('#wcpbc-addon-pricing').insertAfter($('#clear'));

	});
</script>
