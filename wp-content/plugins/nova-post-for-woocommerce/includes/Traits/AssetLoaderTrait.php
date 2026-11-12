<?php
/**
 * Asset Loader Trait
 *
 * Provides unified methods for registering and enqueuing scripts and styles
 * with translations support.
 *
 * @package NovaPostForWoocommerce\Traits
 * @since   1.3.0
 */

namespace NovaPostForWoocommerce\Traits;

/**
 * Trait for loading assets with translations.
 *
 * @since 1.3.0
 */
trait AssetLoaderTrait {

    /**
     * Register and enqueue a script with translations.
     *
     * @since 1.3.0
     *
     * @param string $handle       Script handle.
     * @param string $file_name    File name in build directory (e.g., 'admin.js').
     * @param array  $dependencies Optional. Script dependencies. Default empty array.
     * @param bool   $enqueue      Optional. Whether to enqueue immediately. Default true.
     * @return void
     */
    protected function register_script_with_translations( $handle, $file_name, $dependencies = array(), $enqueue = true ) {
        $plugin_url  = plugin_dir_url( NPFORWC_PLUGIN_FILE_PATH );
        $plugin_path = plugin_dir_path( NPFORWC_PLUGIN_FILE_PATH );

        $script_path = $plugin_path . 'build/' . $file_name;
        $asset_file  = $plugin_path . 'build/' . str_replace( '.js', '.asset.php', $file_name );

        list( $asset_dependencies, $asset_version ) = $this->get_asset_metadata( $asset_file );

        $all_dependencies = array_merge( $dependencies, $asset_dependencies );

        $version = $asset_version;
        if ( null === $version && file_exists( $script_path ) ) {
            $version = filemtime( $script_path );
        }

        wp_register_script(
            $handle,
            $plugin_url . 'build/' . $file_name,
            $all_dependencies,
            $version,
            true
        );

        wp_set_script_translations(
            $handle,
            'nova-post-for-woocommerce',
            NPFORWC_PLUGIN_PATH . 'languages/'
        );

        if ( $enqueue ) {
            wp_enqueue_script( $handle );
        }
    }

    /**
     * Register and enqueue a style.
     *
     * @since 1.3.0
     *
     * @param string $handle       Style handle.
     * @param string $file_name    File name in build directory (e.g., 'admin.css').
     * @param array  $dependencies Optional. Style dependencies. Default empty array.
     * @param bool   $enqueue      Optional. Whether to enqueue immediately. Default true.
     * @return void
     */
    protected function register_style( $handle, $file_name, $dependencies = array(), $enqueue = true ) {
        $plugin_url  = plugin_dir_url( NPFORWC_PLUGIN_FILE_PATH );
        $plugin_path = plugin_dir_path( NPFORWC_PLUGIN_FILE_PATH );

        $style_path = $plugin_path . 'build/' . $file_name;

        $version = file_exists( $style_path ) ? filemtime( $style_path ) : NPFORWC_PLUGIN_VERSION;

        wp_register_style(
            $handle,
            $plugin_url . 'build/' . $file_name,
            $dependencies,
            $version
        );

        if ( $enqueue ) {
            wp_enqueue_style( $handle );
        }
    }

    /**
     * Get dependencies and version from generated asset file.
     *
     * @since 1.3.0
     *
     * @param string $asset_file Path to webpack asset file.
     * @return array{0: array<int, string>, 1: string|null}
     */
    private function get_asset_metadata( $asset_file ) {
        if ( ! file_exists( $asset_file ) ) {
            return array( array(), NPFORWC_PLUGIN_VERSION );
        }

        $asset = include $asset_file;

        if ( ! is_array( $asset ) ) {
            return array( array(), NPFORWC_PLUGIN_VERSION );
        }

        $dependencies = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
            ? $asset['dependencies']
            : array();

        $version = isset( $asset['version'] ) ? (string) $asset['version'] : NPFORWC_PLUGIN_VERSION;

        return array( $dependencies, $version );
    }
}
