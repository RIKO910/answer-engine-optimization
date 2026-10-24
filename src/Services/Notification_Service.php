<?php
/**
 * Weekly maintenance: audit, citation scan and email digest.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on the aeo_weekly_maintenance cron event.
 */
class Notification_Service {

	/**
	 * Run the weekly job.
	 *
	 * @return array<string, mixed> Summary of what ran.
	 */
	public function run_weekly() {
		$summary = array(
			'audit'     => null,
			'citations' => null,
		);

		if ( Settings::is_module_enabled( 'site_audit' ) ) {
			$audit              = ( new Audit_Service() )->run_site_audit();
			$summary['audit']   = $audit['summary'] ?? null;
		}

		// Scheduled AI visibility scans are a Pro feature (filter set by the Pro add-on).
		$citations = new Citation_Service();
		if ( apply_filters( 'aeo_scheduled_visibility_scan', false ) && Settings::is_module_enabled( 'analytics' ) && $citations->is_available() && Settings::get( 'tracked_keywords', array() ) ) {
			$result = $citations->scan_citations();
			if ( ! is_wp_error( $result ) ) {
				$summary['citations'] = $result;
			}
		}

		$this->send_digest( $summary );

		return $summary;
	}

	/**
	 * Email the site admin a short digest, if enabled.
	 *
	 * @param array<string, mixed> $summary Summary.
	 * @return bool Whether an email was sent.
	 */
	public function send_digest( array $summary ) {
		$prefs = Settings::get( 'notifications', array() );
		$lines = array();

		if ( ! empty( $prefs['email_audit'] ) && is_array( $summary['audit'] ) ) {
			$a       = $summary['audit'];
			$lines[] = sprintf(
				/* translators: 1: total, 2: critical, 3: warnings, 4: opportunities */
				__( 'Site audit: %1$d issues (%2$d critical, %3$d warnings, %4$d opportunities).', 'answer-engine-optimization' ),
				(int) ( $a['total'] ?? 0 ),
				(int) ( $a['critical'] ?? 0 ),
				(int) ( $a['warning'] ?? 0 ),
				(int) ( $a['opportunity'] ?? 0 )
			);
		}

		if ( ! empty( $prefs['email_citations'] ) && is_array( $summary['citations'] ) ) {
			$c       = $summary['citations'];
			$lines[] = sprintf(
				/* translators: 1: keywords checked, 2: answers citing the site */
				__( 'AI citations: %1$d keywords checked on Perplexity, %2$d answers cited your site.', 'answer-engine-optimization' ),
				(int) ( $c['checked'] ?? 0 ),
				(int) ( $c['cited'] ?? 0 )
			);
		}

		if ( empty( $lines ) ) {
			return false;
		}

		$lines[] = '';
		$lines[] = admin_url( 'admin.php?page=aeo-dashboard' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Weekly AEO summary', 'answer-engine-optimization' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$to = (string) apply_filters( 'aeo_digest_recipient', get_option( 'admin_email' ) );
		return wp_mail( $to, $subject, implode( "\n", $lines ) );
	}
}
