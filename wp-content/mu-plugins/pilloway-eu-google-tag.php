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
		</script>
		<script async src="https://www.googletagmanager.com/gtm.js?id=GTM-W739L7NB"></script>
		<?php
	},
	5
);
