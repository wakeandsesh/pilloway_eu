<?php
/**
 * Scheduled work: promote idle carts, send due reminders, retention cleanup.
 *
 * @package CatCodeAbandonedCart
 */

namespace CatCode\AbandonedCart\Core;

defined( 'ABSPATH' ) || exit;

class Cron {

	public const SCAN_HOOK    = 'catcode_abandoned_cart_scan';
	public const CLEANUP_HOOK = 'catcode_abandoned_cart_cleanup';
	public const SCHEDULE     = 'catcode_abandoned_cart_15min';

	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- 15 min is the documented scan cadence.
		add_action( self::SCAN_HOOK, array( $this, 'scan' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup' ) );

		// Self-heal if the events were lost (e.g. a database restore).
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * @param array<string,array{interval:int,display:string}> $schedules Existing schedules.
	 * @return array<string,array{interval:int,display:string}>
	 */
	public function add_schedule( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes (CatCode Abandoned Cart)', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
		);
		return $schedules;
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::SCAN_HOOK ) ) {
			wp_schedule_event( time() + 300, self::SCHEDULE, self::SCAN_HOOK );
		}
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + 600, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function unschedule(): void {
		$scan = wp_next_scheduled( self::SCAN_HOOK );
		if ( $scan ) {
			wp_unschedule_event( $scan, self::SCAN_HOOK );
		}
		$cleanup = wp_next_scheduled( self::CLEANUP_HOOK );
		if ( $cleanup ) {
			wp_unschedule_event( $cleanup, self::CLEANUP_HOOK );
		}
	}

	/**
	 * Every 15 minutes: promote idle carts, then send whatever is due.
	 */
	public function scan(): void {
		$this->promote_idle();
		$this->send_due();
	}

	private function promote_idle(): void {
		$minutes = max( 1, Settings::get_int( 'abandon_after', 60 ) );

		foreach ( Repository::stale_active( $minutes ) as $row ) {
			Repository::update(
				(int) $row['id'],
				array(
					'status'       => Repository::STATUS_ABANDONED,
					'abandoned_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s' )
			);

			$row['status']       = Repository::STATUS_ABANDONED;
			$row['abandoned_at'] = current_time( 'mysql' );

			/**
			 * Fires when a cart is marked as abandoned.
			 *
			 * @param array $row The cart row.
			 */
			do_action( 'catcode_abandoned_cart_abandoned', $row );
		}
	}

	private function send_due(): void {
		$steps = Settings::email_steps();
		$step  = $steps[0];

		$cooldown = Settings::get_int( 'email_cooldown', 3 );
		$now      = Repository::now();

		foreach ( Repository::pending_reminders( (int) $step['step'] ) as $row ) {
			// The reminder is measured from the moment the cart was abandoned.
			$anchor = (string) $row['abandoned_at'];
			if ( '' === $anchor || null === $row['abandoned_at'] ) {
				continue;
			}

			$due_at = strtotime( $anchor ) + ( (int) $step['delay'] * MINUTE_IN_SECONDS );
			if ( $due_at > $now ) {
				continue;
			}

			// Do not pester an address that another cart already mailed recently.
			if ( Repository::emailed_recently( (string) $row['email'], $cooldown, (int) $row['id'] ) ) {
				continue;
			}

			/**
			 * Short-circuit a reminder just before it goes out.
			 *
			 * @param bool  $send Whether to send.
			 * @param array $row  The cart row.
			 * @param array $step The reminder step.
			 */
			if ( ! apply_filters( 'catcode_abandoned_cart_should_send', true, $row, $step ) ) {
				continue;
			}

			Mailer::send( $row, $step );
		}
	}

	/**
	 * Daily: close carts past their recovery window, then honour retention.
	 */
	public function cleanup(): void {
		Repository::expire_stale( max( 1, Settings::get_int( 'token_lifetime', 7 ) ) );

		$retention = Settings::get_int( 'retention_days', 90 );
		if ( $retention > 0 ) {
			Repository::purge_old( $retention );
		}
	}
}
