<?php
/**
 * Display a notices to prevent user to saves option if Pro version is unsupported.
 *
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wcpbc-pro-incompatible-warning" style="display:none;">
	<div>
		<p><strong><?php esc_html_e( 'Heads up!', 'woocommerce-product-price-based-on-countries' ); ?></strong><br>
		<?php
		// translators: HTML Tags.
		echo sprintf( esc_html__( 'You are using a deprecated version of the %1$sPrice Based on Country Pro%2$s plugin. All plugin features have been disabled to avoid issues. Please, update Price Based on Country %1$sPro%2$s to the latest version.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>' );
		?>
		</p>
		<p>
			<?php
			// translators: HTML Tags.
			echo sprintf( esc_html__( 'You need a valid license to receive %1$sPro updates%2$s.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>' );
			?>
		</p>
		<p>
			<a class="button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=price-based-country&section=license' ) ); ?>"><?php esc_html_e( 'Check license status', 'woocommerce-product-price-based-on-countries' ); ?></a>
		</p>
	</div>
</div>
<script>
;(function($) {
	$('.wrap.woocommerce ul.subsubsub+.clear').nextAll().wrapAll( '<div id="wcpbc-settings-page-wrap"></div>' );
	$('#wcpbc-settings-page-wrap').append('<div class="wcpbc-pro-incompatible-overlay"></div>');
	$('#wcpbc-pro-incompatible-warning').appendTo('#wcpbc-settings-page-wrap').show();
})(jQuery);
</script>
<style>
	#wcpbc-settings-page-wrap {
		position: relative;
		margin: 15px 0 0 0;
	}
	.wcpbc-pro-incompatible-overlay {
		position: absolute;
		width: 100%;
		height: 100%;
		background: linear-gradient(rgba(240, 240, 241,0), 20%,rgba(240, 240, 241, 0.95));
		z-index: 999;
		top: 0;
		left: 0;
	}
	#wcpbc-pro-incompatible-warning {
		position: absolute;
		z-index: 9999;
		top: 15%;
		left: 10%;
		max-width: 80%;
		background: #fff;
		border: 1px solid #c3c4c7;
		border-left-color: #d63638;
		border-left-width: 6px;
		padding: 10px 20px;
	}
	#wcpbc-pro-incompatible-warning p {
		margin: 14px 0 14px 8px;
		font-size: 14px;
	}
</style>
<?php
