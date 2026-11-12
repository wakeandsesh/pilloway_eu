<?php
/**
 * Multilang Interface
 *
 * @version 3.0.0
 * @package WCPBC\Interface
 */

/**
 * WCPBC_Multilang_Interface
 */
interface WCPBC_Multilang_Interface {

	/**
	 * Get class instance.
	 *
	 * @return object Instance.
	 */
	public static function instance();

	/**
	 * Syncs the queue.
	 */
	public function sync_queue();

	/**
	 * Enqueue a post ID for multilang sync.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $zone_id Zone ID. Optional.
	 */
	public function enqueue( $post_id, $zone_id = false );
}
