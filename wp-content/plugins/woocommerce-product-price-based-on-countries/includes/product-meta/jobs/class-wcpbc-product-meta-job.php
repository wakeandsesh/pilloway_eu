<?php
/**
 * Execute a job on the product meta storage.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Job class.
 */
abstract class WCPBC_Product_Meta_Job {

	/**
	 * Action Scheduler hook name.
	 *
	 * @var string
	 */
	const ACTION_HOOK = 'wc_price_based_country_product_meta_job';

	/**
	 * Table names
	 *
	 * @var stdClass
	 */
	protected $table;

	/**
	 * Job arguments.
	 *
	 * @var array
	 */
	protected $args;

	/**
	 * Job name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Clear cache flag.
	 *
	 * @var bool
	 */
	protected $clear_cache = false;

	/**
	 * Constructor.
	 *
	 * @param mixed $args Task arguments.
	 */
	protected function __construct( $args = false ) {
		global $wpdb;
		$this->args  = is_array( $args ) ? $args : [];
		$this->name  = substr( get_class( $this ), 23 );
		$this->table = (object) [
			'prefix'              => $wpdb->prefix,
			'posts'               => $wpdb->posts,
			'postmeta'            => $wpdb->postmeta,
			'product_meta_lookup' => $wpdb->prefix . 'wc_product_meta_lookup',
		];
	}

	/**
	 * Runs the job asynchronous.
	 */
	public function run_async() {
		as_enqueue_async_action(
			self::ACTION_HOOK,
			[
				'job'  => $this->name,
				'args' => $this->args,
			],
			self::ACTION_HOOK,
			false,
			5
		);
	}

	/**
	 * Cancel a pending job.
	 *
	 * @return \WCPBC_Product_Meta_Job;
	 */
	public function cancel() {
		$actions = as_get_scheduled_actions(
			[
				'hook'     => self::ACTION_HOOK,
				'group'    => self::ACTION_HOOK,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => -1,
			]
		);

		foreach ( $actions as $action_id => $action ) {
			$action_args = is_callable( [ $action, 'get_args' ] ) ? $action->get_args() : [];
			if ( ! ( isset( $action_args['job'] ) && $this->name === $action_args['job'] ) ) {
				continue;
			}

			$action_args['args'] = isset( $action_args['args'] ) ? (array) $action_args['args'] : [];

			if ( $this->args != $action_args['args'] ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				continue;
			}

			ActionScheduler::store()->cancel_action( $action_id );
		}

		return $this;
	}

	/**
	 * Runs the job.
	 */
	public function run() {
		$this->args        = wc_clean( $this->args );
		$this->clear_cache = false;

		$this->run_job();

		if ( $this->clear_cache ) {
			$this->clear_caches();
		}
	}

	/**
	 * Clear any caches.
	 */
	protected function clear_caches() {
		// Increments the transient version to invalidate cache.
		WC_Cache_Helper::get_transient_version( 'product', true );
		WC_Cache_Helper::get_transient_version( 'product_query', true );
	}

	/**
	 * Does the action of the job.
	 */
	abstract protected function run_job();

	/**
	 * Returns the wpdb instance.
	 *
	 * @return wpdb
	 */
	protected function db() {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Returns the exclude post status filter.
	 *
	 * @param string $tablename Post table name.
	 * @return string
	 */
	protected function get_post_filter( $tablename = 'posts' ) {
		return "{$tablename}.post_type IN ('product', 'product_variation') AND {$tablename}.post_status IN ('publish', 'draft', 'pending', 'private')";
	}

	/**
	 * Returns the batch size.
	 *
	 * @since 4.3.0
	 * @return int
	 */
	protected function get_batch_size() {
		return (int) apply_filters( 'wc_price_based_country_job_batch_size', 1000, $this );
	}

	/**
	 * Prepare for IN stament.
	 *
	 * @param string $query Query statement with one placeholder.
	 * @param array  $values In values.
	 */
	protected function prepare_in( $query, $values ) {

		$pos = strpos( $query, '%' );

		if ( false === $pos ) {
			return $query;
		}

		$specifier = substr( $query, $pos, 2 );
		$specifier = '%d' !== $specifier ? '%s' : '%d';
		$query     = str_replace( '%d', '%s', $query );

		return $this->db()->prepare(
			sprintf(
				$query,
				implode( ', ', array_fill( 0, count( $values ), $specifier ) )
			),
			$values
		);
	}

	/**
	 * Create a new job.
	 *
	 * @param string $job Task name.
	 * @param array  $args Task arguments.
	 * @return WCPBC_Product_Meta_Job|bool
	 */
	public static function create( $job, $args = false ) {
		$classname = 'WCPBC_Product_Meta_Job_' . $job;
		$job       = false;

		if ( class_exists( $classname ) ) {
			$job = new $classname( $args );
		}

		return $job;
	}
}
