<?php
/**
 * Sync all variable products price with they children.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Job_Sync_Price_With_Children_Batch class.
 */
class WCPBC_Product_Meta_Job_Sync_Price_With_Children_Batch extends WCPBC_Product_Meta_Job_Sync_Price_With_Children {

	/**
	 * Runs the job.
	 */
	public function run_job() {

		$offset      = isset( $this->args['offset'] ) ? absint( $this->args['offset'] ) : 0;
		$batch_size  = intval( $this->get_batch_size() / 2 );
		$product_ids = get_posts(
			[
				'fields'                 => 'ids',
				'posts_per_page'         => $batch_size,
				'offset'                 => $offset,
				'post_type'              => 'product',
				'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => [ // phpcs:ignore
					[
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => wcpbc_wrapper_product_types(),
						'operator' => 'IN',
					],
				],
			]
		);

		if ( $product_ids ) {

			foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {

				$this->sync_price( $zone, $product_ids );
			}

			if ( ! ( count( $product_ids ) < $batch_size ) ) {
				// Re-enqueue the job to process the next batch.
				$this->args['offset'] = $offset + $batch_size;
				$this->run_async();
			}
		}
	}
}
