(function () {
	'use strict';
	var settings = window.PillowayMediaFallback || {};
	var blogSelector = '.wp-block-post-featured-image img,.post-thumbnail img,.wp-block-latest-posts__featured-image img';
	var productSelector = '.wc-block-components-product-image img,.wc-block-grid__product-image img,.woocommerce ul.products li.product img,.woocommerce-page ul.products li.product img,.pilloway-home-featured__image img';
	var targetSelector = blogSelector + ',' + productSelector;

	function replaceBrokenImage(image) {
		if (!(image instanceof HTMLImageElement) || image.dataset.pillowayFallbackApplied) return;
		var isBlog = image.closest(blogSelector);
		var url = isBlog ? settings.blog : settings.product;
		if ((!isBlog && !image.closest(productSelector)) || !url) return;
		image.dataset.pillowayFallbackApplied = '1';
		image.removeAttribute('srcset');
		image.removeAttribute('sizes');
		image.classList.add(isBlog ? 'pilloway-blog-image-fallback' : 'pilloway-product-image-fallback');
		image.src = url;
		if (!image.hasAttribute('alt')) {
			var article = image.closest('article');
			var heading = article ? article.querySelector('h1,h2,h3') : null;
			image.alt = heading ? heading.textContent.trim() : '';
		}
	}

	function scan(root) {
		if (!root) return;
		if (root instanceof HTMLImageElement && root.matches(targetSelector)) {
			if (root.complete && 0 === root.naturalWidth) replaceBrokenImage(root);
			return;
		}
		if (root.querySelectorAll) root.querySelectorAll(targetSelector).forEach(function (image) {
			if (image.complete && 0 === image.naturalWidth) replaceBrokenImage(image);
		});
	}

	document.addEventListener('error', function (event) {
		if (event.target instanceof HTMLImageElement) replaceBrokenImage(event.target);
	}, true);
	scan(document);
	if ('MutationObserver' in window) {
		new MutationObserver(function (records) {
			records.forEach(function (record) { record.addedNodes.forEach(scan); });
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
}());