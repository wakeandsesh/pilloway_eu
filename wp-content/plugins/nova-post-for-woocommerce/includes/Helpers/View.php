<?php
/**
 * View Orders pages.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Helpers;

/**
 * Class View.
 */
final class View {

    /**
     * Method to render specific view.
     *
     * @param string $view Name of the view.
     * @param array  $data View params.
     * @return void
     */
    public static function render( $view, $data = array() ) {
        $view_file = NPFORWC_PLUGIN_PATH . 'views/' . $view . '.php';

        if ( ! file_exists( $view_file ) ) {
            return;
        }

        extract( $data ); //phpcs:ignore

        include $view_file;
    }
}
