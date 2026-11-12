<?php
/**
 * Title: Footer
 * Slug: twentytwentyfive/footer
 * Categories: footer
 * Block Types: core/template-part/footer
 * Description: Compact Pilloway footer with a dynamic site title and copyright year.
 */
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:separator {"align":"wide","className":"is-style-wide"} -->
	<hr class="wp-block-separator alignwide has-alpha-channel-opacity is-style-wide"/>
	<!-- /wp:separator -->

	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|20"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--20)">
		<!-- wp:site-title {"level":2} /-->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><?php echo esc_html( '© ' . wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->