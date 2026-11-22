<?php
/**
 * Plugin Name: Pilloway EU Google tag
 * Description: Loads the Google tag assigned to the Pilloway EU Ads account.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	static function () {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! in_array( $host, array( 'pilloway.eu', 'www.pilloway.eu' ), true ) ) {
			return;
		}
		?>
		<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		(function () {
			var started = false;
			window.pillowayStartAnalytics = function () {
				if (started || !/(?:^|;\s*)pilloway_consent=all(?:;|$)/.test(document.cookie)) return started;
				started = true;
				var script = document.createElement('script');
				script.async = true;
				script.src = 'https://www.googletagmanager.com/gtag/js?id=G-X8MQ0SF2YQ';
				document.head.appendChild(script);
				gtag('config', 'G-X8MQ0SF2YQ');
				return true;
			};
			window.addEventListener('pilloway:consent', window.pillowayStartAnalytics);
			window.pillowayStartAnalytics();
		})();
		</script>
		<script async src="https://www.googletagmanager.com/gtm.js?id=GTM-W739L7NB"></script>
		<?php
	},
	5
);
