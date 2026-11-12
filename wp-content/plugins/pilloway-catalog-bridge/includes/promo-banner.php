<?php
/**
 * Localized promotional announcement below the shared WordPress header.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_catalog_promo_offer_url(): string {
	if ( ! function_exists( 'pll_current_language' ) || ! function_exists( 'pll_home_url' ) ) {
		return '';
	}
	$language = pll_current_language( 'slug' );
	if ( ! is_string( $language ) || '' === $language ) {
		return '';
	}
	$home_url = pll_home_url( $language );
	if ( ! is_string( $home_url ) || '' === $home_url ) {
		return '';
	}
	return add_query_arg(
		array(
			'post_type' => 'product',
			'ccf'       => 'o:1',
		),
		$home_url
	);
}

function pilloway_catalog_render_promo_banner(): string {
	$url = pilloway_catalog_promo_offer_url();
	if ( '' === $url ) {
		return '';
	}
	ob_start();
	?>
	<aside class="pilloway-promo-banner alignfull" aria-label="<?php esc_attr_e( 'Pilloway offers', 'pilloway-catalog-bridge' ); ?>">
		<div class="pilloway-promo-banner__inner">
			<span class="pilloway-promo-banner__discount"><?php esc_html_e( 'Up to 30% off', 'pilloway-catalog-bridge' ); ?></span>
			<span class="pilloway-promo-banner__message"><?php esc_html_e( 'on selected Pilloway orthopedic pillows', 'pilloway-catalog-bridge' ); ?></span>
			<a class="pilloway-promo-banner__link" href="<?php echo esc_url( $url ); ?>">
				<?php esc_html_e( 'Explore offers', 'pilloway-catalog-bridge' ); ?>
				<span aria-hidden="true">→</span>
			</a>
		</div>
	</aside>
	<?php
	return (string) ob_get_clean();
}

/** Append the banner after the active Polylang header template part on every page. */
function pilloway_catalog_append_promo_banner_after_header( string $block_content, array $block ): string {
	if (
		'core/template-part' !== ( $block['blockName'] ?? '' )
		|| 'header' !== ( $block['attrs']['slug'] ?? '' )
		|| false !== strpos( $block_content, 'pilloway-promo-banner' )
	) {
		return $block_content;
	}
	return $block_content . pilloway_catalog_render_promo_banner();
}
add_filter( 'render_block', 'pilloway_catalog_append_promo_banner_after_header', 100, 2 );

function pilloway_catalog_enqueue_promo_banner_style(): void {
	$path = dirname( __DIR__ ) . '/assets/promo-banner.css';
	if ( ! is_readable( $path ) ) {
		return;
	}
	wp_enqueue_style(
		'pilloway-promo-banner',
		plugins_url( 'assets/promo-banner.css', dirname( __DIR__ ) . '/pilloway-catalog-bridge.php' ),
		array(),
		(string) filemtime( $path )
	);
}
add_action( 'wp_enqueue_scripts', 'pilloway_catalog_enqueue_promo_banner_style', 30 );
