<?php
/**
 * WP-CLI commands.
 *
 * @package AEO
 */

namespace AEO\CLI;

use AEO\Services\Audit_Service;
use AEO\Services\Score_Engine;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI command handler.
 */
class Commands {

	/**
	 * Run site audit.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aeo audit
	 *
	 * @when after_wp_load
	 */
	public function audit() {
		$service = new Audit_Service();
		$results = $service->run_site_audit();

		\WP_CLI::success(
			sprintf(
				'Audit complete: %d issues found (%d critical, %d warnings, %d opportunities)',
				$results['summary']['total'],
				$results['summary']['critical'],
				$results['summary']['warning'],
				$results['summary']['opportunity']
			)
		);
	}

	/**
	 * Calculate AEO scores for all posts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aeo score
	 *
	 * @when after_wp_load
	 */
	public function score() {
		$posts  = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$engine = new Score_Engine();

		foreach ( $posts as $post_id ) {
			$result = $engine->calculate( $post_id );
			\WP_CLI::log( sprintf( 'Post %d: score %d', $post_id, $result['score'] ) );
		}

		\WP_CLI::success( sprintf( 'Scored %d posts', count( $posts ) ) );
	}

	/**
	 * Auto-fix an audit issue.
	 *
	 * ## OPTIONS
	 *
	 * <issue_id>
	 * : The issue ID to fix
	 *
	 * ## EXAMPLES
	 *
	 *     wp aeo fix missing_schema_123
	 *
	 * @when after_wp_load
	 */
	public function fix( $args ) {
		$issue_id = $args[0] ?? '';
		if ( empty( $issue_id ) ) {
			\WP_CLI::error( 'Issue ID required' );
		}

		$service = new Audit_Service();
		$result  = $service->auto_fix( $issue_id );

		if ( $result['fixed'] ) {
			\WP_CLI::success( $result['message'] );
		} else {
			\WP_CLI::warning( $result['message'] );
		}
	}
}
