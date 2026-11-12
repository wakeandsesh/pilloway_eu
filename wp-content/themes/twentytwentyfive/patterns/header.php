<?php
/**
 * Title: Header
 * Slug: twentytwentyfive/header
 * Categories: header
 * Block Types: core/template-part/header
 * Description: Site header with site title and navigation.
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.0
 */

?>
<!-- wp:group {"align":"full","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull">
	<!-- wp:group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
		<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
			<!-- wp:group {"className":"pilloway-brand","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
			<div class="wp-block-group pilloway-brand">
				<!-- wp:site-logo {"width":136,"shouldSyncIcon":false} /-->
				<!-- wp:site-title {"level":0,"style":{"typography":{"fontSize":"clamp(18px, 2vw, 24px)","fontWeight":"700","letterSpacing":"0.04em","lineHeight":"1.1"},"spacing":{"margin":{"top":"3px"}}},"textAlign":"center"} /-->
			</div>
			<!-- /wp:group -->
			<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
			<div class="wp-block-group">
				<!-- wp:navigation {"overlayBackgroundColor":"base","overlayTextColor":"contrast","overlayMenu":"mobile","style":{"typography":{"fontSize":"14px"},"spacing":{"blockGap":"12px"}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"wrap"}} -->
				<!-- wp:pilloway/primary-menu /-->
				<!-- wp:pilloway/header-categories /-->
			<!-- /wp:navigation -->
			<!-- wp:html -->
			<script>
			(() => {
				const menu = document.querySelector('.wp-block-navigation__responsive-container');
				if (!menu) return;

				const clearExpandedCategoriesWhenClosed = () => {
					if (!window.matchMedia('(max-width: 781px)').matches || menu.classList.contains('is-menu-open')) return;
					menu.querySelectorAll('.pilloway-category-disclosure[open]').forEach((item) => item.removeAttribute('open'));
				};

				new MutationObserver(clearExpandedCategoriesWhenClosed).observe(menu, {
					attributes: true,
					attributeFilter: ['class']
				});
				clearExpandedCategoriesWhenClosed();
			})();
			</script>
			<!-- /wp:html -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:pilloway/header-category-strip /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
