<?php
/**
 * WP All import addon integration - Ad.
 *
 * @since 3.4.9
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_WPAllImport_Ad Class
 */
final class WCPBC_WPAllImport_Ad {

	/**
	 * Parse data.
	 *
	 * @var array.
	 */
	protected $parse_data;

	/**
	 * Init integration.
	 */
	public static function init() {
		if ( wcpbc_is_pro() ) {
			return;
		}
		add_action( 'pmxi_extend_options_main', [ __CLASS__, 'pmxi_extend_options' ], 15, 2 );
	}

	/**
	 * Display the addon options.
	 *
	 * @param string $entry The current post type to import.
	 * @param Array  $post  The current import post.
	 */
	public static function pmxi_extend_options( $entry, $post ) {
		if ( 'product' !== $entry ) {
			return false;
		}
		?>
		<div class="wpallimport-collapsed closed" id="wcpbc-wpallimport-metabox">
			<div class="wpallimport-content-section">
				<div class="wpallimport-collapsed-header">
					<h3>Price Based on Country Add-On</h3>
				</div>
				<div class="wpallimport-collapsed-content" style="padding:0;">
					<div class="wpallimport-collapsed-content-inner">
						<?php foreach ( array_values( WCPBC_Pricing_Zones::get_zones() ) as $index => $zone ) : ?>
							<?php
							if ( $index > 1 ) {
								break;
							}
							?>
							<h4 class="wcpbc-allimport-title -title-<?php echo esc_attr( $index ) . ( $index < 2 ? ' expanded' : '' ); ?>">
								<?php echo esc_html( wcpbc_price_method_label( __( 'Price for', 'woocommerce-product-price-based-on-countries' ), $zone ) ); ?>
							</h4>
							<div class="wcpbc-allimport-fields -fields-<?php echo esc_attr( $index ); ?>">
								<?php
								self::price_fields( $index );
								self::sale_price_date_fields( $index );
								?>
							</div> <!-- .wcpbc-allimport-fields -->
						<?php endforeach; ?>

						<div id="wcpbc-paywall">
							<aside class="wcpbc-paywall-content">
								<?php self::upgrade_pro_content(); ?>
							</aside>
						</div> <!-- #wcpbc-paywall -->

					</div>
				</div>
			</div>
		</div>
		<script>
		;( function( $ ) {
			$(document).ready(function(){
				$('#wcpbc-wpallimport-metabox h4.wcpbc-allimport-title').each(function() {
					let target = '.' + $(this).attr('class').replace(/-title/g, '-fields').replace(' ', '.').replace('expanded', '');
					$(target).toggle( $(this).hasClass('expanded') );
				});
			});
		})( jQuery );
		</script>
		<style>#wcpbc-wpallimport-metabox .wpallimport-collapsed-content{position:relative!important}#wcpbc-wpallimport-metabox .wpallimport-collapsed-content-inner h4{cursor:pointer}#wcpbc-wpallimport-metabox .wpallimport-collapsed-content-inner h4:before{display:inline-block;content:"\f347";font-family:dashicons;display:inline-block;line-height:1;font-weight:400;font-style:normal;margin-right:2px;font-size:1.3em;vertical-align:bottom}#wcpbc-wpallimport-metabox .wpallimport-collapsed-content-inner h4.expanded:before{content:"\f343"}#wcpbc-wpallimport-metabox .form-field label{margin:.25em 0 .5em 0}#wcpbc-wpallimport-metabox .form-field.wcpbc-wpallimport-text-field label{display:block}#wcpbc-wpallimport-metabox .wcpbc-wpallimport-col-2{display:flex;width:100%}#wcpbc-wpallimport-metabox .wcpbc-wpallimport-col-2 .input{width:50%}#wcpbc-wpallimport-metabox{--paywall-gradient-height:350px}#wcpbc-wpallimport-metabox #wcpbc-paywall{display:block;position:absolute;width:100%;height:100%;top:0;background:linear-gradient(#fff3,#fff)}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-paywall-content{width:500px;margin:10% auto;background:#fff;border-radius:5px;box-shadow:0 0 10px #000;padding:20px 30px}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-upgrade-pro-logo img{max-width:200px}#wcpbc-wpallimport-metabox #wcpbc-paywall h2{font-size:22px}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-upgrade-txt{font-size:1rem!important;font-weight:300;margin:1.5rem 0}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-upgrade-cta{display:flex}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-upgrade-cta p{margin:1em 0}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-upgrade-cta a.button-primary{font-weight:700;font-size:16px;padding:5px 15px;color:#fff!important}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-upgrade-cta .wcpbc-upgrade-rate{margin-left:auto;padding:1em 0}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-upgrade-cta .wcpbc-upgrade-rate h5{margin:0 0 .4em 0}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-upgrade-cta .wcpbc-upgrade-rate p{margin:0}#wcpbc-wpallimport-metabox #wcpbc-paywall .wcpbc-upgrade-cta .wcpbc-upgrade-rate .dashicons-star-filled{color:#FAA71A}</style>
		<?php
	}


	/**
	 * Prices fields.
	 *
	 * @param int $index Index.
	 */
	private static function price_fields( $index ) {
		$value = $index < 1 ? 'manual' : 'exchange_rate';

		foreach ( wcpbc_price_method_options() as $key => $label ) {
			?>
		<div class="input">
			<p class="form-field wpallimport-radio-field">
				<input type="radio" id="<?php echo esc_attr( "_price_method_{$index}_{$key}" ); ?>" name="<?php echo esc_attr( "_price_method_{$index}" ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $value, $key ); ?> class="switcher <?php echo ( 'exchange_rate' === $key ? ' switcher-reversed' : '' ); ?>"/>
				<label for="<?php echo esc_attr( "_price_method_{$index}_{$key}" ); ?>"><?php echo wp_kses_post( $label ); ?></label>
			</p>
		</div>
		<?php } ?>
		<div class="form-field wpallimport-radio-field">
			<input type="radio" id="<?php echo esc_attr( "_price_method_{$index}_xpath" ); ?>" class="switcher" name="<?php echo esc_attr( "_price_method_{$index}" ); ?>" value="xpath"/>
			<label for="<?php echo esc_attr( "_price_method_{$index}_xpath" ); ?>"><?php esc_html_e( 'Set with XPath', 'woocommerce-product-price-based-on-countries' ); ?></label>
			<div class="switcher-target-<?php echo esc_attr( "_price_method_{$index}_xpath" ); ?> set_with_xpath">
				<span class="wpallimport-slide-content" style="padding-left:0;">
					<input type="text" class="smaller-text" name="<?php echo esc_attr( "_price_method_{$index}_xpath" ); ?>" style="width:300px;" value="">
					<a href="#help" style="top:0;" class="wpallimport-help" aria-describedby="tipsy" title="<?php esc_attr_e( "The value of presented XPath should be one of the following: ('manual', 'exchange_rate').", 'woocommerce-product-price-based-on-countries' ); ?>" tabindex="0">?</a>
				</span>
			</div> <!-- .switcher-target -->
		</div> <!-- .form-field wpallimport-radio-field -->

		<div class="wcpbc-wpallimport-col-2 switcher-target-<?php echo esc_attr( "_price_method_{$index}_exchange_rate" ); ?>">
			<div class="input">
				<p class="form-field wcpbc-wpallimport-text-field">
					<label for="<?php echo esc_attr( "_regular_price_{$index}" ); ?>">
						<?php esc_html_e( 'Regular price', 'woocommerce-product-price-based-on-countries' ); ?>
					</label>
					<input type="text" name="<?php echo esc_attr( "_regular_price_{$index}" ); ?>" id="<?php echo esc_attr( "_regular_price_{$index}" ); ?>" value=""/>
				</p>
			</div>
			<div class="input">
				<p class="form-field wcpbc-wpallimport-text-field">
					<label for="<?php echo esc_attr( "_sale_price_{$index}" ); ?>">
						<?php esc_html_e( 'Sale price', 'woocommerce-product-price-based-on-countries' ); ?>
					</label>
					<input type="text" name="<?php echo esc_attr( "_sale_price_{$index}" ); ?>" id="<?php echo esc_attr( "_sale_price_{$index}" ); ?>" value=""/>
				</p>
			</div>
		</div> <!-- .wcpbc-wpallimport-col-2 -->
		<?php
	}

	/**
	 * Sale price dates fields.
	 *
	 * @param int $index Index.
	 */
	private static function sale_price_date_fields( $index ) {
		$value = $index < 1 ? 'manual' : 'default';
		?>
		<div class="switcher-target-<?php echo esc_attr( "_price_method_{$index}_exchange_rate" ); ?>">
			<p class="form-field">
				<label><?php esc_html_e( 'Sale price dates', 'woocommerce-product-price-based-on-countries' ); ?></label>
			</p>
			<div class="input">
				<p class="form-field wpallimport-radio-field">
					<input type="radio" id="<?php echo esc_attr( "_sale_price_dates_{$index}_default" ); ?>" class="switcher switcher-reversed" name="<?php echo esc_attr( "_sale_price_dates_{$index}" ); ?>" value="default" <?php checked( $value, 'default' ); ?>/>
					<label for="<?php echo esc_attr( "_sale_price_dates_{$index}_default" ); ?>"><?php echo wp_kses_post( __( 'Same as default price', 'woocommerce-product-price-based-on-countries' ) ); ?></label>
				</p>
			</div>
			<div class="input">
				<p class="form-field wpallimport-radio-field">
					<input type="radio" id="<?php echo esc_attr( "_sale_price_dates_{$index}_manual" ); ?>" class="switcher" name="<?php echo esc_attr( "_sale_price_dates_{$index}" ); ?>" value="manual" <?php checked( $value, 'manual' ); ?>/>
					<label for="<?php echo esc_attr( "_sale_price_dates_{$index}_manual" ); ?>"><?php echo wp_kses_post( __( 'Set specific dates', 'woocommerce-product-price-based-on-countries' ) ); ?></label>
				</p>
			</div>
			<div class="form-field wpallimport-radio-field">
				<input type="radio" id="<?php echo esc_attr( "_sale_price_dates_{$index}_xpath" ); ?>" class="switcher" name="<?php echo esc_attr( "_sale_price_dates_{$index}" ); ?>" value="xpath" <?php checked( $value, 'xpath' ); ?>/>
				<label for="<?php echo esc_attr( "_sale_price_dates_{$index}_xpath" ); ?>"><?php esc_html_e( 'Set with XPath', 'woocommerce-product-price-based-on-countries' ); ?></label>
				<div class="switcher-target-<?php echo esc_attr( "_sale_price_dates_{$index}_xpath" ); ?> set_with_xpath">
					<span class="wpallimport-slide-content" style="padding-left:0;">
						<input type="text" class="smaller-text" name="<?php echo esc_attr( "_sale_price_dates_{$index}_xpath" ); ?>" style="width:300px;" value="">
						<a href="#help" style="top:0;" class="wpallimport-help" aria-describedby="tipsy" title="<?php esc_attr_e( "The value of presented XPath should be one of the following: ('manual', 'default').", 'woocommerce-product-price-based-on-countries' ); ?>" tabindex="0">?</a>
					</span>
				</div>
			</div>
			<div class="input switcher-target-<?php echo esc_attr( "_sale_price_dates_{$index}_default" ); ?>">
				<p class="form-field">
					<span style="display:inline-block;vertical-align:middle">
						<span><?php echo esc_html( _x( 'From', 'placeholder', 'woocommerce-product-price-based-on-countries' ) ); ?></span>
						<input type="text" class="datepicker" name="<?php echo esc_attr( "_sale_price_dates_from_{$index}" ); ?>" value="" style="float:none; width:120px;"/>
						<span><?php echo esc_html( _x( 'To', 'placeholder', 'woocommerce-product-price-based-on-countries' ) ); ?></span>
						<input type="text" class="datepicker" name="<?php echo esc_attr( "_sale_price_dates_to_{$index}" ); ?>" value="" style="float:none !important; width:120px;"/>
					</span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Ouput the Upgrade to Pro Content.
	 */
	private static function upgrade_pro_content() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		?>
		<div class="wcpbc-upgrade-pro-logo">
			<img src="<?php echo esc_url( WCPBC()->plugin_url() . 'assets/images/pricebasedcountry-logo.png' ); ?>" />
		</div>
		<h2>
			<?php esc_html_e( 'Upgrade For Additional Features', 'woocommerce-product-price-based-on-countries' ); ?>
		</h2>
		<p class="wcpbc-upgrade-txt">
			<?php esc_html_e( 'Get access to all the PRO features, compatibilities, and priority support. Click below for all the details.', 'woocommerce-product-price-based-on-countries' ); ?>
		</p>
		<div class="wcpbc-upgrade-cta">
			<p>
				<a target="_blank" rel="noopener noreferrer" class="button button-primary" href="<?php echo esc_url( wcpbc_home_url( 'wp-allimport-addon' ) ); ?>">
				<?php esc_html_e( 'See Features & Pricing', 'woocommerce-product-price-based-on-countries' ); ?>
				</a>
			</p>
			<div class="wcpbc-upgrade-rate">
				<h5><?php esc_html_e( 'Join our happy customers', 'woocommerce-product-price-based-on-countries' ); ?></h5>
				<p>
					<span>4.9&nbsp;</span><?php echo wp_kses_post( str_repeat( '<span class="dashicons dashicons-star-filled"></span>', 5 ) ); ?>
					<span>(200+ reviews)</span>
				</p>
			</div>
		</div>
		<?php
	}

}

WCPBC_WPAllImport_Ad::init();
