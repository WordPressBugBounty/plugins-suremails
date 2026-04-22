<?php
/**
 * Analytics Class
 *
 * @since 1.6.0
 * @package SureMails\Inc\Analytics
 */

namespace SureMails\Inc\Analytics;

use SureMails\Inc\DB\EmailLog;
use SureMails\Inc\Onboarding;
use SureMails\Inc\Traits\Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Analytics
 */
class Analytics {

	use Instance;

	/**
	 * Shared events instance.
	 *
	 * @var \BSF_Analytics_Events|null
	 */
	private static $events = null;

	/**
	 * Constructor: hook analytics filter and state events.
	 */
	public function __construct() {
		// BSF Analytics hooks `maybe_track_analytics()` on `init`, which runs
		// on every request (frontend, cron, REST). Register the stats filter
		// unconditionally so `plugin_data.suremails` is always present when the
		// library decides to POST — otherwise a frontend request can win the
		// 2-day throttle and ship an empty payload, suppressing telemetry.
		add_filter( 'bsf_core_stats', [ $this, 'add_analytics_data' ] );

		// Plugin version change detection — hook into the update lifecycle so the
		// event is queued regardless of which request (admin, frontend, cron) wins
		// the race to run Update::init(). Registered outside the is_admin() gate
		// so the listener is bound on every request.
		add_action( 'suremails_update_before', [ $this, 'handle_plugin_updated' ], 10, 2 );

		// State-event detection only needs to run in admin context.
		if ( ! is_admin() ) {
			return;
		}

		// State-based events — throttled to once per day.
		// IMPORTANT: Transient is set INSIDE detect_state_events() after confirming
		// BSF_Analytics_Events class is loaded. If class isn't ready, it retries next load.
		if ( get_transient( 'suremails_state_events_checked' ) === false ) {
			$this->detect_state_events();
		}
	}

	/**
	 * Get shared events tracker.
	 *
	 * @return \BSF_Analytics_Events|null
	 */
	public static function events() {
		if ( null === self::$events ) {
			if ( ! class_exists( 'BSF_Analytics_Events' ) ) {
				return null;
			}

			self::$events = new \BSF_Analytics_Events( 'suremails' );
		}

		return self::$events;
	}

	/**
	 * Add analytics data to bsf_core_stats.
	 *
	 * @param array<string, mixed> $stats_data Existing stats data.
	 * @return array<string, mixed>
	 */
	public function add_analytics_data( $stats_data ) {
		$events = self::events();

		$stats_data['plugin_data']['suremails'] = [
			'version'       => SUREMAILS_VERSION,
			'site_language' => get_locale(),

			// Daily KPIs (last 2 days).
			'kpi_records'   => $this->get_kpi_tracking_data(),

			// One-time events (flushed from pending queue).
			'events_record' => $events ? $events->flush_pending() : [],
		];

		return $stats_data;
	}

	/**
	 * Get number of days since plugin installation.
	 *
	 * @since 1.9.4
	 * @return int Days since install (0 if unknown).
	 */
	public static function get_days_since_install(): int {
		$install_time = (int) get_site_option( 'suremails_usage_installed_time', 0 );

		if ( $install_time <= 0 ) {
			return 0;
		}

		return (int) floor( ( time() - $install_time ) / DAY_IN_SECONDS );
	}

	/**
	 * Handle the plugin update lifecycle event.
	 *
	 * Hooked to `suremails_update_before`, which fires from Update::init() right
	 * before `suremails-version` is rewritten — so `$from_version` is still the
	 * pre-upgrade value. Queues a `plugin_updated` analytics event and clears
	 * the state-events throttle so other state events re-evaluate on the next
	 * admin load instead of waiting up to 24h.
	 *
	 * @param string $from_version Previously stored plugin version.
	 * @param string $to_version   New plugin version.
	 * @return void
	 */
	public function handle_plugin_updated( string $from_version, string $to_version ): void {
		if ( empty( $from_version ) || $from_version === $to_version ) {
			return;
		}

		delete_transient( 'suremails_state_events_checked' );

		$events = self::events();
		if ( null === $events ) {
			return;
		}

		$events->flush_pushed( [ 'plugin_updated' ] );
		$events->track(
			'plugin_updated',
			$to_version,
			[ 'from_version' => $from_version ]
		);
	}

	/**
	 * Detect and queue state-based events on admin page load.
	 *
	 * Runs on every admin load but throttled by a daily transient.
	 * BSF_Analytics_Events dedup prevents duplicate tracking.
	 *
	 * @since 1.9.4
	 * @return void
	 */
	private function detect_state_events(): void {
		$events = self::events();

		if ( null === $events ) {
			// BSF_Analytics_Events class not loaded — do NOT set transient; retry next load.
			return;
		}

		// Class is available — set throttle transient so we don't re-run for 24h.
		set_transient( 'suremails_state_events_checked', 1, DAY_IN_SECONDS );

		// ── 1. plugin_activated ──────────────────────────────────────────
		$bsf_referrers = get_option( 'bsf_product_referers', [] );
		$source        = ! empty( $bsf_referrers['suremails'] )
			? sanitize_text_field( $bsf_referrers['suremails'] )
			: 'self';
		$events->track( 'plugin_activated', SUREMAILS_VERSION, [ 'source' => $source ] );

		// ── 2. onboarding_skipped ────────────────────────────────────────
		$onboarding_skipped = Onboarding::instance()->get_onboarding_skipped_status();
		if ( $onboarding_skipped ) {
			$events->track( 'onboarding_skipped', 'yes' );
		}

		// ── 3. onboarding_completed ──────────────────────────────────────
		$onboarding_done = Onboarding::instance()->get_onboarding_status();
		if ( $onboarding_done ) {
			$events->flush_pushed( [ 'onboarding_completed' ] );
			$events->track(
				'onboarding_completed',
				'completed',
				[ 'previously_skipped' => (string) (int) $onboarding_skipped ]
			);
		}
	}

	/**
	 * Get KPI tracking data for the last 2 days (excluding today).
	 *
	 * @since 1.9.4
	 * @return array<string, array<string, array<string, int>>> KPI records keyed by date.
	 */
	private function get_kpi_tracking_data(): array {
		$kpi_records = [];

		for ( $i = 1; $i <= 2; $i++ ) {
			$date = wp_date( 'Y-m-d', strtotime( "-{$i} days" ) );

			if ( ! $date ) {
				continue;
			}

			$kpi_records[ $date ] = [
				'numeric_values' => [
					'emails_processed' => $this->get_emails_log_count( $date ),
				],
			];
		}

		return $kpi_records;
	}

	/**
	 * Get daily emails entry count for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @since 1.9.4
	 * @return int Count of emails processed for that date.
	 */
	private function get_emails_log_count( string $date ): int {
		global $wpdb;

		$table_name = EmailLog::instance()->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( $table_exists !== $table_name ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table_name}` WHERE DATE(`created_at`) = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$date
			)
		);
	}
}
