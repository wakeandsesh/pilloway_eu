<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * This has been slightly modified (to read environment variables) for use in Docker.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// IMPORTANT: this file needs to stay in-sync with https://github.com/WordPress/WordPress/blob/master/wp-config-sample.php
// (it gets parsed by the upstream wizard in https://github.com/WordPress/WordPress/blob/f27cb65e1ef25d11b535695a660e7282b98eb742/wp-admin/setup-config.php#L356-L392)

// a helper function to lookup "env_FILE", "env", then fallback
if (!function_exists('getenv_docker')) {
	// https://github.com/docker-library/wordpress/issues/588 (WP-CLI will load this file 2x)
	function getenv_docker($env, $default) {
		if ($fileEnv = getenv($env . '_FILE')) {
			return rtrim(file_get_contents($fileEnv), "\r\n");
		}
		else if (($val = getenv($env)) !== false) {
			return $val;
		}
		else {
			return $default;
		}
	}
}

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', getenv_docker('WORDPRESS_DB_NAME', 'wordpress') );

/** Database username */
define( 'DB_USER', getenv_docker('WORDPRESS_DB_USER', 'example username') );

/** Database password */
define( 'DB_PASSWORD', getenv_docker('WORDPRESS_DB_PASSWORD', 'example password') );

/**
 * Docker image fallback values above are sourced from the official WordPress installation wizard:
 * https://github.com/WordPress/WordPress/blob/1356f6537220ffdc32b9dad2a6cdbe2d010b7a88/wp-admin/setup-config.php#L224-L238
 * (However, using "example username" and "example password" in your database is strongly discouraged.  Please use strong, random credentials!)
 */

/** Database hostname */
define( 'DB_HOST', getenv_docker('WORDPRESS_DB_HOST', 'mysql') );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', getenv_docker('WORDPRESS_DB_CHARSET', 'utf8mb4') );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', getenv_docker('WORDPRESS_DB_COLLATE', '') );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         getenv_docker('WORDPRESS_AUTH_KEY',         '') );
define( 'SECURE_AUTH_KEY',  getenv_docker('WORDPRESS_SECURE_AUTH_KEY',  '') );
define( 'LOGGED_IN_KEY',    getenv_docker('WORDPRESS_LOGGED_IN_KEY',    '') );
define( 'NONCE_KEY',        getenv_docker('WORDPRESS_NONCE_KEY',        '') );
define( 'AUTH_SALT',        getenv_docker('WORDPRESS_AUTH_SALT',        '') );
define( 'SECURE_AUTH_SALT', getenv_docker('WORDPRESS_SECURE_AUTH_SALT', '') );
define( 'LOGGED_IN_SALT',   getenv_docker('WORDPRESS_LOGGED_IN_SALT',   '') );
define( 'NONCE_SALT',       getenv_docker('WORDPRESS_NONCE_SALT',       '') );
// (See also https://wordpress.stackexchange.com/a/152905/199287)

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = getenv_docker('WORDPRESS_TABLE_PREFIX', 'wp_');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');
define( 'WP_DEBUG', !!getenv_docker('WORDPRESS_DEBUG', '') );

/* Add any custom values between this line and the "stop editing" line. */

// Trust HTTPS only for the exact active ngrok host and its one-time proxy token.
$__wpNgrokPreviewPath = '/run/wp-ngrok-preview/preview.json';
$__wpNgrokPreview = @file_get_contents( $__wpNgrokPreviewPath );
$__wpNgrokPreview = is_string( $__wpNgrokPreview ) ? json_decode( $__wpNgrokPreview, true ) : null;
$__wpNgrokPreviewHost = is_array( $__wpNgrokPreview ) && isset( $__wpNgrokPreview['host'] )
	? strtolower( (string) $__wpNgrokPreview['host'] )
	: '';
$__wpNgrokPreviewRequestHost = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) );
$__wpNgrokPreviewHostMatches = hash_equals( $__wpNgrokPreviewHost, $__wpNgrokPreviewRequestHost )
	|| hash_equals( $__wpNgrokPreviewHost . ':443', $__wpNgrokPreviewRequestHost );
$__wpNgrokPreviewSecret = is_array( $__wpNgrokPreview ) && isset( $__wpNgrokPreview['secret'] )
	? (string) $__wpNgrokPreview['secret']
	: '';
$__wpNgrokPreviewRequestSecret = (string) ( $_SERVER['HTTP_X_WP_PREVIEW_TOKEN'] ?? '' );
$__wpNgrokPreviewForwardedProto = strtolower( trim( (string) ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) ) );
$__wpNgrokPreviewExpires = is_array( $__wpNgrokPreview ) && isset( $__wpNgrokPreview['expiresAt'] )
	? filter_var( $__wpNgrokPreview['expiresAt'], FILTER_VALIDATE_INT )
	: false;
$__wpNgrokPreviewSession = is_array( $__wpNgrokPreview ) && isset( $__wpNgrokPreview['sessionId'] )
	? (string) $__wpNgrokPreview['sessionId']
	: '';
$__wpNgrokPreviewLocalPort = is_array( $__wpNgrokPreview ) && isset( $__wpNgrokPreview['localPort'] )
	? filter_var( $__wpNgrokPreview['localPort'], FILTER_VALIDATE_INT )
	: false;
$__wpNgrokPreviewSessionIsValid = 1 === preg_match( '/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i', $__wpNgrokPreviewSession )
	&& false !== $__wpNgrokPreviewExpires
	&& $__wpNgrokPreviewExpires > time()
	&& $__wpNgrokPreviewExpires <= time() + 86400 + 300
	&& false !== $__wpNgrokPreviewLocalPort
	&& $__wpNgrokPreviewLocalPort >= 1
	&& $__wpNgrokPreviewLocalPort <= 65535;
$__wpNgrokPreviewIsValid =
	$__wpNgrokPreviewHostMatches
	&& 1 === preg_match( '/^[a-z0-9][a-z0-9-]*\.ngrok(?:-free)?\.(?:app|dev)$/D', $__wpNgrokPreviewHost )
	&& 1 === preg_match( '/^[a-f0-9]{64}$/D', $__wpNgrokPreviewSecret )
	&& hash_equals( $__wpNgrokPreviewSecret, $__wpNgrokPreviewRequestSecret )
	&& 'https' === $__wpNgrokPreviewForwardedProto
	&& $__wpNgrokPreviewSessionIsValid
;

// LocalTunnel forwards the public Host and HTTPS scheme to the local WordPress server.
$__wpLocalTunnelHost = strtolower( trim( (string) ( $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '' ) ) );
$__wpLocalTunnelRequestHost = strtolower( trim( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );
$__wpLocalTunnelForwardedProto = strtolower( trim( (string) ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) ) );
$__wpLocalTunnelIsValid =
    '' !== $__wpLocalTunnelHost
    && ( hash_equals( $__wpLocalTunnelHost, $__wpLocalTunnelRequestHost ) || hash_equals( $__wpLocalTunnelHost . ':443', $__wpLocalTunnelRequestHost ) )
    && 1 === preg_match( '/^[a-z0-9][a-z0-9-]*\.loca\.lt$/D', $__wpLocalTunnelHost )
    && 'https' === $__wpLocalTunnelForwardedProto
;

$__pillowayHome = rtrim((string) getenv_docker('PILLOWAY_HOME', ''), '/');
if ($__pillowayHome && PHP_SAPI !== 'cli' && !hash_equals((string) parse_url($__pillowayHome, PHP_URL_HOST), explode(':', $__wpNgrokPreviewRequestHost)[0])) {
    http_response_code(400); exit('Unknown Host header.');
}
if ($__pillowayHome) {
    define('WP_HOME', $__pillowayHome);
    define('WP_SITEURL', $__pillowayHome);
    if ('https' === parse_url($__pillowayHome, PHP_URL_SCHEME)) $_SERVER['HTTPS'] = 'on';
}
elseif ( $__wpNgrokPreviewIsValid ) {
	define( 'WP_HOME', 'https://' . $__wpNgrokPreviewHost );
	define( 'WP_SITEURL', 'https://' . $__wpNgrokPreviewHost );
	define( 'WP_NGROK_PREVIEW_HOST', $__wpNgrokPreviewHost );
	define( 'WP_NGROK_PREVIEW_PORT', (int) $__wpNgrokPreviewLocalPort );
	$_SERVER['HTTPS'] = 'on';
}
elseif ( $__wpLocalTunnelIsValid ) {
    define( 'WP_HOME', 'https://' . $__wpLocalTunnelHost );
    define( 'WP_SITEURL', 'https://' . $__wpLocalTunnelHost );
    define( 'WP_NGROK_PREVIEW_HOST', $__wpLocalTunnelHost );
    define( 'WP_NGROK_PREVIEW_PORT', 8098 );
    $_SERVER['HTTPS'] = 'on';
}
elseif ( PHP_SAPI !== 'cli' && 1 !== preg_match( '/^(?:127\.0\.0\.1|localhost|192\.168\.0\.[0-9]{1,3}):8098$/D', $__wpNgrokPreviewRequestHost ) ) {
	http_response_code( 400 );
	header( 'Content-Type: text/plain; charset=UTF-8' );
	exit( 'Unknown Host header for this local WordPress project.' );
}

if ($configExtra = getenv_docker('WORDPRESS_CONFIG_EXTRA', '')) {
	eval($configExtra);
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
