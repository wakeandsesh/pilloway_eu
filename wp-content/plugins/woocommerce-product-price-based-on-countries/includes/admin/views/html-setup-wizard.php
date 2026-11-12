<?php
/**
 * Admin View: Setup Wizard.
 *
 * @package WCPBC/Views
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
?>
<div class="wcpbc-setup-wizard-wrap">
	<div class="wcpbc-setup-wizard-sidebar">
		<div class="wcpbc-logo">
			<img src="<?php echo esc_url( WCPBC()->plugin_url() . 'assets/images/pricebasedcountry-logow.png' ); ?>" alt="Price Based on Country for WooCommerce logo" />
		</div>
		<h1>Price Based on Country for WooCommerce</h1>
		<h2><?php esc_html_e( 'First-time configuration.', 'woocommerce-product-price-based-on-countries' ); ?></h2>
		<div class="escape">
			<a href="<?php echo esc_url( admin_url() ); ?>"><span class="dashicons dashicons-arrow-left-alt"></span>&nbsp;<?php esc_html_e( 'Return to WordPress Dashboard', 'woocommerce-product-price-based-on-countries' ); ?></a>
		</div>
	</div>
	<div id="wcpbc-setup-wizard-content">
		<div class="wcpbc-setup-wizard-steps-wrap">
			<!-- Step 1 - Welcome -->
			<div class="wcpbc-setup-step" data-step="welcome">
				<h2 class="wcpbc-content-title"><?php esc_html_e( 'Thanks for installing!', 'woocommerce-product-price-based-on-countries' ); ?></h2>
				<p class="wcpbc-text -text-16">
					<?php esc_html_e( 'This setup wizard will help you to set up the WooCommerce geolocation feature in just a few steps. Click on continue to start the configuration.', 'woocommerce-product-price-based-on-countries' ); ?>
				</p>
				<form action="">
					<div class="wcpbc-input-container -container-submit">
						<button type="submit" class="button wcpbc-button">
							<span><?php esc_html_e( 'Continue', 'woocommerce-product-price-based-on-countries' ); ?>&nbsp;</span>
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</button>
					</div>
				</form>
			</div>

			<!-- Step 2 - Geolocation -->
			<div class="wcpbc-setup-step" data-step="geolocation">
				<h2 class="wcpbc-content-title"><?php esc_html_e( 'GeoIP database.', 'woocommerce-product-price-based-on-countries' ); ?></h2>
				<?php if ( self::maxmind_geoip_required() ) : ?>
					<p class="wcpbc-text -text-16">
						<?php
						// translators: HTML tags.
						printf( esc_html__( 'The WooCommerce geolocation feature requires the %1$sMaxMind GeoLite2 Free%2$s database. You need a free license key from MaxMind.com to access the database.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>' );
						?>
					</p>
					<p class="wcpbc-text -text-16">
						<a target="_blank" rel="noopener noreferrer" href="https://woocommerce.com/document/maxmind-geolocation-integration/#section-1">
							<span><?php esc_html_e( 'Learn how to generate a license key on Maxmind.com', 'woocommerce-product-price-based-on-countries' ); ?></span>
							<span class="dashicons dashicons-external"></span>
						</a>
					</p>
				<?php else : ?>
					<p class="wcpbc-text -text-16">
						<?php esc_html_e( "Great! Your server provides the client's country in an environment variable, so you don't need to download the GeoIP database.", 'woocommerce-product-price-based-on-countries' ); ?>
					</p>
				<?php endif; ?>
				<form action="">
					<?php if ( self::maxmind_geoip_required() ) : ?>
					<div class="wcpbc-input-container -container-text -container-maxmind_license_key">
						<div class="wcpbc-input-label-wrap -text-label">
							<label for="">
								<span><?php esc_html_e( 'MaxMind license key', 'woocommerce-product-price-based-on-countries' ); ?></span>
							</label>
						</div>
						<div class="wcpbc-input-wrap -text-input">
							<input type="text" name="maxmind_license_key" id="maxmind_license_key" value="<?php echo esc_attr( self::get_maxmind_license_key() ); ?>" />
						</div>
						<div class="wcpbc-input-description">
							<span><?php esc_html_e( 'New licenses take up to five minutes to be activated.', 'woocommerce-product-price-based-on-countries' ); ?></span>
						</div>
					</div>
					<?php endif; ?>
					<div class="wcpbc-input-container -container-submit">
						<button type="submit" class="button wcpbc-button">
							<span><?php esc_html_e( 'Continue', 'woocommerce-product-price-based-on-countries' ); ?>&nbsp;</span>
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</button>
					</div>
				</form>
			</div>

			<!-- Step 3 - Cache support -->
			<div class="wcpbc-setup-step" data-step="cache_support">
				<h2 class="wcpbc-content-title"><?php esc_html_e( 'Cache support.', 'woocommerce-product-price-based-on-countries' ); ?></h2>
				<p class="wcpbc-text -text-16">
					<?php esc_html_e( 'Geolocation is powered by a PHP script that does not run on cached pages. If you use a cache plugin, enable the following option, so customers do not see a cached version of prices.', 'woocommerce-product-price-based-on-countries' ); ?>
				</p>
				<form action="">
					<div class="wcpbc-input-container -container-true-false">
						<input type="hidden" id="cachesupport" name="cachesupport" value="<?php echo esc_attr( wc_bool_to_string( WCPBC_Cache_Plugins_Helper::has_cache_plugin() ) ); ?>" />
						<a href="#cachesupport" role="switch"><span class="wcpbc-input-toggle -input-toggle--enabled" aria-label="enabled"></span></a>
						<label for="">
							<span><?php esc_html_e( 'Load the price of the products using Javascript and AJAX.', 'woocommerce-product-price-based-on-countries' ); ?></span>
						</label>
						<div class="wcpbc-input-description">
							<span><?php esc_html_e( "Do not change this option if you're not sure. You can modify it later from the plugin settings.", 'woocommerce-product-price-based-on-countries' ); ?></span>
						</div>
					</div>
					<div class="wcpbc-input-container -container-submit">
						<button type="submit" class="button wcpbc-button">
							<span><?php esc_html_e( 'Continue', 'woocommerce-product-price-based-on-countries' ); ?>&nbsp;</span>
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</button>
					</div>
				</form>
			</div>

			<!-- Ready -->
			<div class="wcpbc-setup-step" data-step="ready">
				<h2 class="wcpbc-content-title"><?php esc_html_e( 'Ready!', 'woocommerce-product-price-based-on-countries' ); ?></h2>
				<p class="wcpbc-text -text-16">
					<?php esc_html_e( 'The Geolocation feature is ready to go. You should now add the first pricing zone.', 'woocommerce-product-price-based-on-countries' ); ?>
				</p>
				<div class="wcpbc-input-container -container-submit">
					<a class="button wcpbc-button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=price-based-country&zone_id=new' ) ); ?>">
						<span><?php esc_html_e( 'Add a pricing zone', 'woocommerce-product-price-based-on-countries' ); ?></span>
					</a>
				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/html" id="tmpl-wcpbc-setup-wizard-notice">
<div class="wcpbc-setup-wizard-notice -error">
	<p>{{{data.message}}}</p>
	<a href="#" class="wcpbc-setup-wizard-notice-dismiss">
		<span class="dashicons dashicons-no-alt"></span>
	</a>
</div>
</script>
<?php
