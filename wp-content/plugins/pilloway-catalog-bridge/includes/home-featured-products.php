<?php
/**
 * Polylang-aware Pilloway product selection placed after homepage reviews.
 * Product translations are resolved natively; missing translations never fall back.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_catalog_home_featured_products(): array {
	static $cache = array();
	if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'pll_current_language' ) || ! function_exists( 'pll_get_post' ) || ! function_exists( 'pll_get_post_language' ) ) {
		return array();
	}
	$language = pll_current_language( 'slug' );
	if ( ! is_string( $language ) || '' === $language ) {
		return array();
	}
	if ( isset( $cache[ $language ] ) ) {
		return $cache[ $language ];
	}

	$selection = array(
		array( 'slug' => 'podushka-roller-pillow-2-tencel-na-zhyvoti-pudrovyi', 'brand' => 'Beauty Balance®', 'copy' => __( 'Comfort when resting on your stomach.', 'pilloway-catalog-bridge' ), 'class' => 'beauty-balance' ),
		array( 'slug' => 'shkilna-podushka-school-comfort-m1-s-5-10-chornyi', 'brand' => 'Correct Shape®', 'copy' => __( 'A comfortable seat for studying at a desk.', 'pilloway-catalog-bridge' ), 'class' => 'correct-shape' ),
		array( 'slug' => 'podushka-dlya-doroslykh-family-dream-m-interlok-myatnyi', 'brand' => 'Family Dream®', 'copy' => __( 'Everyday comfort for adult sleep.', 'pilloway-catalog-bridge' ), 'class' => 'family-dream' ),
		array( 'slug' => 'komplekt-podushok-lash-plyushevyi-velyur-3-sht-rozhevyi', 'brand' => 'Beauty Balance® LASH', 'copy' => __( 'Three pillows for lash professionals.', 'pilloway-catalog-bridge' ), 'class' => 'beauty-lash' ),
	);

	$products = array();
	foreach ( $selection as $item ) {
		$source = get_page_by_path( $item['slug'], OBJECT, 'product' );
		if ( ! ( $source instanceof WP_Post ) || 'publish' !== $source->post_status || 'uk' !== pll_get_post_language( $source->ID, 'slug' ) ) {
			continue;
		}
		$product_id = absint( pll_get_post( $source->ID, $language ) );
		if ( ! $product_id || $language !== pll_get_post_language( $product_id, 'slug' ) ) {
			continue;
		}
		$product_post = get_post( $product_id );
		$product      = wc_get_product( $product_id );
		if (
			! ( $product_post instanceof WP_Post )
			|| 'product' !== $product_post->post_type
			|| 'publish' !== $product_post->post_status
			|| ! $product
			|| ! $product->is_in_stock()
			|| ! has_post_thumbnail( $product_id )
		) {
			continue;
		}
		$products[] = array( 'product' => $product, 'brand' => $item['brand'], 'copy' => $item['copy'], 'class' => $item['class'] );
	}
	$cache[ $language ] = $products;
	return $products;
}

function pilloway_catalog_append_home_featured_products( string $block_content, array $block ): string {
	if (
		'woocommerce/product-collection' !== ( $block['blockName'] ?? '' )
		|| ! function_exists( 'pilloway_catalog_is_collections_home' )
		|| ! pilloway_catalog_is_collections_home()
		|| false === strpos( $block_content, 'pilloway-collections__list' )
	) {
		return $block_content;
	}
	return $block_content . pilloway_catalog_render_home_featured_products();
}
add_filter( 'render_block', 'pilloway_catalog_append_home_featured_products', 21, 2 );

/** Append the product selection after the complete homepage content and reviews. */
function pilloway_catalog_append_home_featured_products_to_content( string $content ): string {
	if (
		is_admin()
		|| ! in_the_loop()
		|| ! is_main_query()
		|| ! function_exists( 'pilloway_catalog_is_collections_home' )
		|| ! pilloway_catalog_is_collections_home()
		|| false === strpos( $content, 'pilloway-home-reviews' )
		|| false !== strpos( $content, 'class="pilloway-home-featured' )
	) {
		return $content;
	}

	return $content . pilloway_catalog_render_home_featured_products();
}
add_filter( 'the_content', 'pilloway_catalog_append_home_featured_products_to_content', 99 );


function pilloway_catalog_render_home_featured_products(): string {
	$products = pilloway_catalog_home_featured_products();
	if ( empty( $products ) ) {
		return '';
	}
	$title_id = wp_unique_id( 'pilloway-home-featured-title-' );
	ob_start();
	?>
	<section class="pilloway-home-featured alignfull" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
		<div class="pilloway-home-featured__inner">
			<header class="pilloway-home-featured__header">
				<p class="pilloway-home-featured__eyebrow"><?php esc_html_e( 'Pilloway selection', 'pilloway-catalog-bridge' ); ?></p>
				<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'Pilloway favourites', 'pilloway-catalog-bridge' ); ?></h2>
				<p class="pilloway-home-featured__intro"><?php esc_html_e( 'Comfort picks for rest, study and professional work.', 'pilloway-catalog-bridge' ); ?></p>
			</header>
			<div class="pilloway-home-featured__grid">
				<?php foreach ( $products as $entry ) : ?>
					<?php $product = $entry['product']; ?>
					<a class="pilloway-home-featured__card pilloway-home-featured__card--<?php echo esc_attr( $entry['class'] ); ?>" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
						<span class="pilloway-home-featured__image"><?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'decoding' => 'async' ) ) ); ?></span>
						<span class="pilloway-home-featured__content">
							<span class="pilloway-home-featured__brand"><?php echo esc_html( $entry['brand'] ); ?></span>
							<span class="pilloway-home-featured__name"><?php echo esc_html( $product->get_name() ); ?></span>
							<span class="pilloway-home-featured__copy"><?php echo esc_html( $entry['copy'] ); ?></span>
							<span class="pilloway-home-featured__bottom">
								<span class="pilloway-home-featured__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
								<span class="pilloway-home-featured__arrow" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M5 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
							</span>
							<span class="screen-reader-text"><?php esc_html_e( 'View product', 'pilloway-catalog-bridge' ); ?></span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

function pilloway_catalog_enqueue_home_featured_style(): void {
	if ( ! function_exists( 'pilloway_catalog_is_collections_home' ) || ! pilloway_catalog_is_collections_home() ) {
		return;
	}
	$path = dirname( __DIR__ ) . '/assets/home-featured-products.css';
	if ( ! is_readable( $path ) ) {
		return;
	}
	wp_enqueue_style(
		'pilloway-home-featured-products',
		plugins_url( 'assets/home-featured-products.css', dirname( __DIR__ ) . '/pilloway-catalog-bridge.php' ),
		array( 'pilloway-home-reviews' ),
		(string) filemtime( $path )
	);
}
add_action( 'wp_enqueue_scripts', 'pilloway_catalog_enqueue_home_featured_style', 21 );
