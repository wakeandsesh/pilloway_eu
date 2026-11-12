<?php
/**
 * Display the license settings page.
 *
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Your license', 'woocommerce-product-price-based-on-countries' ); ?></h2>
<div class="wcpbc-license-panel flex-panel">
	<div class="row is-ext-header">
		<div class="ext-title">
			<h3>Price Based on Country Pro</h3>
		</div>
		<div class="ext-description">
			<?php if ( $license['is_connected'] ) : ?>
				<span class="renews">
					<?php esc_html_e( 'License key', 'woocommerce-product-price-based-on-countries' ); ?>:&nbsp;
					<span class="license-key">...<?php echo esc_html( substr( $license['key'], -5 ) ); ?></span>
				</span>
				<?php if ( $license['expired'] ) : ?>
					<span class="renews">
						<strong><?php esc_html_e( 'Expired', 'woocommerce-product-price-based-on-countries' ); ?></strong>:&nbsp;
						<?php echo esc_html( $license['expires'] ); ?>
					</span>
				<?php elseif ( $license['expiring'] ) : ?>
					<span class="renews">
						<strong><?php esc_html_e( 'Expiring soon!', 'woocommerce-product-price-based-on-countries' ); ?></strong>
						<?php echo esc_html( $license['expires'] ); ?>
					</span>
				<?php else : ?>
					<span class="renews">
						<?php esc_html_e( 'Expires on', 'woocommerce-product-price-based-on-countries' ); ?>:&nbsp;
						<?php echo esc_html( $license['expires'] ); ?>
					</span>
				<?php endif; ?>
			<?php else : ?>
				<div class="wcpbc-input-container">
					<label class="wcpbc-input-label"><?php esc_html_e( 'License key', 'woocommerce-product-price-based-on-countries' ); ?></label>
					<div class="wcpbc-input-wrap">
						<?php
						printf(
							'<input type="text" name="%1$s" id="%1$s" autocomplete="off" placeholder="%2$s" value="%3$s" />',
							esc_attr( WCPBC_License_Settings::instance()->get_field_key( 'license_key' ) ),
							esc_attr__( 'Paste your license key here', 'woocommerce-product-price-based-on-countries' ),
							esc_attr( $license['key'] )
						);
						?>
					</div>
					<p class="wcpbc-input-help"><?php esc_html_e( 'Enter your license key and click "Activate" to activate Price Based on Country Pro!', 'woocommerce-product-price-based-on-countries' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<div class="ext-actions">
			<?php if ( $license['is_connected'] ) : ?>
				<div class="wcpbc-input-container">
					<label class="wcpbc-input-label -label-true-false"><span class="-input-label-text"><?php esc_html_e( 'Active', 'woocommerce-product-price-based-on-countries' ); ?></span></label>
					<a href="javascript:void(0);" onclick="document.getElementById('wc_price_based_country_license_deactivate').form.submit();" role="switch" id="wcpbc-toggle-activation">
						<span class="woocommerce-input-toggle woocommerce-input-toggle--enabled" aria-label="<?php esc_html_e( 'Active', 'woocommerce-product-price-based-on-countries' ); ?>"></span>
					</a>
					<input type="hidden" id="wc_price_based_country_license_deactivate" style="display:none;" name="save" value="deactivate" />
				</div>
			<?php else : ?>
				<p class="submit">
					<button class="button" name="save" type="submit" value="<?php esc_html_e( 'Activate', 'woocommerce-product-price-based-on-countries' ); ?>"><?php esc_html_e( 'Activate', 'woocommerce-product-price-based-on-countries' ); ?></button>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php foreach ( $license['actions'] as $license_action ) : ?>
		<div class="row ext-updates">
			<div class="ext-status -<?php echo sanitize_html_class( $license_action['status'] ); ?>">
				<p>
					<span class="dashicons dashicons-<?php echo sanitize_html_class( $license_action['icon'] ); ?>"></span>
					<span><?php echo wp_kses_post( $license_action['message'] ); ?></span>
				</p>
			</div>
			<?php if ( ! empty( $license_action['button_url'] ) ) : ?>
			<div class="ext-actions">
				<a class="button" href="<?php echo esc_url( $license_action['button_url'] ); ?>"><?php echo esc_html( $license_action['button_label'] ); ?></a>
			</div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
