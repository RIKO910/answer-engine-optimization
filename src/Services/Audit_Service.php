<?php
/**
 * Site audit and auto-fix.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;
use AEO\Services\AI\AI_Manager;
use AEO\Services\AI\AI_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Audit service.
 */
class Audit_Service {

	/**
	 * Run a full-site audit.
	 *
	 * @return array<string, mixed>
	 */
	public function run_site_audit() {
		$issues = array();
		$posts  = get_posts(
			array(
				'post_type'      => Settings::get_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => (int) apply_filters( 'aeo_audit_post_limit', 500 ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'has_password'   => false,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			if ( Seo_Compat::is_noindex( $post_id ) ) {
				continue;
			}
			$issues = array_merge( $issues, $this->audit_post( $post_id ) );
		}

		$issues = array_merge( $this->audit_global(), $issues );

		$results = array(
			'scanned_at' => current_time( 'mysql' ),
			'scanned'    => count( $posts ),
			'summary'    => $this->summarize( $issues ),
			'issues'     => $issues,
		);

		update_option( 'aeo_optimizer_audit_results', $results, false );
		$this->persist_issues( $issues );

		return $results;
	}

	/**
	 * Last audit results.
	 *
	 * @return array<string, mixed>
	 */
	public function get_results() {
		$results = get_option( 'aeo_optimizer_audit_results', array() );
		if ( ! is_array( $results ) || ! isset( $results['issues'] ) ) {
			return array(
				'issues'  => array(),
				'summary' => $this->summarize( array() ),
			);
		}
		return $results;
	}

	/**
	 * Count open issues by severity.
	 *
	 * @param array<int, array<string, mixed>> $issues Issues.
	 * @return array<string, int>
	 */
	private function summarize( array $issues ) {
		$summary = array(
			'critical'    => 0,
			'warning'     => 0,
			'opportunity' => 0,
			'total'       => 0,
		);
		foreach ( $issues as $issue ) {
			if ( 'open' !== ( $issue['status'] ?? 'open' ) || ! isset( $summary[ $issue['severity'] ] ) ) {
				continue;
			}
			$summary[ $issue['severity'] ]++;
			$summary['total']++;
		}
		return $summary;
	}

	/**
	 * Fix one issue automatically where possible.
	 *
	 * @param string $issue_id Issue ID.
	 * @return array{fixed: bool, message: string}
	 */
	public function auto_fix( $issue_id ) {
		$results = $this->get_results();
		$fixed   = false;
		$message = __( 'Issue not found. Run the audit again.', 'answer-engine-optimization' );

		foreach ( $results['issues'] as $index => $issue ) {
			if ( $issue['id'] !== $issue_id ) {
				continue;
			}

			$post_id = (int) ( $issue['post_id'] ?? 0 );
			if ( $post_id && ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'edit_post', $post_id ) ) {
				$message = __( 'You are not allowed to edit this post.', 'answer-engine-optimization' );
				break;
			}

			$message = __( 'This issue needs a manual fix.', 'answer-engine-optimization' );

			switch ( $issue['category'] ) {
				case 'missing_faq':
					$post = get_post( $post_id );
					if ( $post ) {
						$faqs = AI_Response::normalize_faqs( ( new AI_Manager() )->get_provider()->generate_faqs( $post->post_content, 4 ) );
						$faqs = array_values(
							array_filter(
								$faqs,
								static function ( $f ) {
									return '' !== $f['answer'];
								}
							)
						);
						if ( $faqs ) {
							update_post_meta( $post_id, '_aeo_faq_items', wp_slash( $faqs ) );
							$fixed = true;
							/* translators: %d: number of FAQs */
							$message = sprintf( _n( 'Added %d FAQ from the post content. Review it in the editor.', 'Added %d FAQs from the post content. Review them in the editor.', count( $faqs ), 'answer-engine-optimization' ), count( $faqs ) );
						} else {
							$message = __( 'No FAQs could be generated from this content. Add them in the post editor, or add an AI API key in Settings.', 'answer-engine-optimization' );
						}
					}
					break;

				case 'missing_og':
					$modules              = Settings::get( 'modules', array() );
					$modules['social_og'] = true;
					Settings::update( array( 'modules' => $modules ) );
					$fixed   = true;
					$message = __( 'Open Graph tags turned on.', 'answer-engine-optimization' );
					break;

				case 'schema_disabled':
					Settings::update( array( 'aeo_enable_schema' => 1 ) );
					$fixed   = true;
					$message = __( 'Structured data output turned on.', 'answer-engine-optimization' );
					break;

				case 'missing_organization':
					Settings::update(
						array(
							'business_info' => array_merge(
								(array) Settings::get( 'business_info', array() ),
								array( 'name' => get_bloginfo( 'name' ) )
							),
						)
					);
					$fixed   = '' !== get_bloginfo( 'name' );
					$message = $fixed ? __( 'Organization name set to the site title.', 'answer-engine-optimization' ) : __( 'Set a site title first.', 'answer-engine-optimization' );
					break;
			}

			if ( $fixed ) {
				$results['issues'][ $index ]['status'] = 'fixed';
				$this->mark_fixed( $issue_id );
				if ( $post_id ) {
					( new Score_Engine() )->calculate( $post_id );
				}
			}
			break;
		}

		$results['summary'] = $this->summarize( $results['issues'] );
		update_option( 'aeo_optimizer_audit_results', $results, false );

		return array(
			'fixed'   => $fixed,
			'message' => $message,
		);
	}

	/**
	 * Per-post checks.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_post( $post_id ) {
		$issues = array();
		$post   = get_post( $post_id );
		$title  = wp_strip_all_tags( get_the_title( $post_id ) );
		$words  = Score_Engine::word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );

		if ( $words < 300 ) {
			$issues[] = $this->make_issue(
				"thin_content_{$post_id}",
				$post_id,
				'warning',
				'thin_content',
				/* translators: 1: post title, 2: word count */
				sprintf( __( 'Thin content on "%1$s" (%2$d words, aim for 300+)', 'answer-engine-optimization' ), $title, $words ),
				false
			);
		}

		if ( ! has_post_thumbnail( $post_id ) && 'product' !== $post->post_type ) {
			$issues[] = $this->make_issue(
				"missing_image_{$post_id}",
				$post_id,
				'warning',
				'missing_image',
				/* translators: %s: post title */
				sprintf( __( 'No featured image on "%s" (used in schema and link previews)', 'answer-engine-optimization' ), $title ),
				false
			);
		}

		if ( Settings::is_module_enabled( 'faq_automation' ) && ! ( new Schema_Service() )->build_faq_node( $post_id ) && $words >= 150 ) {
			$issues[] = $this->make_issue(
				"missing_faq_{$post_id}",
				$post_id,
				'opportunity',
				'missing_faq',
				/* translators: %s: post title */
				sprintf( __( 'No FAQs on "%s"', 'answer-engine-optimization' ), $title ),
				true
			);
		}

		if ( '' === trim( (string) get_post_meta( $post_id, '_aeo_direct_answer', true ) ) ) {
			$issues[] = $this->make_issue(
				"missing_answer_{$post_id}",
				$post_id,
				'opportunity',
				'missing_direct_answer',
				/* translators: %s: post title */
				sprintf( __( 'No direct answer set for "%s"', 'answer-engine-optimization' ), $title ),
				false
			);
		}

		return $issues;
	}

	/**
	 * Site-wide checks.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_global() {
		$issues   = array();
		$settings = Settings::get_all();

		if ( ! Settings::get( 'aeo_enable_schema', 1 ) ) {
			$issues[] = $this->make_issue( 'schema_disabled', 0, 'critical', 'schema_disabled', __( 'Structured data output is turned off', 'answer-engine-optimization' ), true );
		}

		if ( empty( $settings['business_info']['name'] ) ) {
			$issues[] = $this->make_issue( 'missing_org_name', 0, 'critical', 'missing_organization', __( 'Organization name not configured', 'answer-engine-optimization' ), true );
		}

		if ( ! Settings::is_module_enabled( 'social_og' ) && '' === Seo_Compat::active_plugin_name() ) {
			$issues[] = $this->make_issue( 'missing_og', 0, 'warning', 'missing_og', __( 'No Open Graph tags (no SEO plugin found and the Open Graph module is off)', 'answer-engine-optimization' ), true );
		}

		if ( ! get_option( 'blog_public' ) ) {
			$issues[] = $this->make_issue( 'site_not_public', 0, 'critical', 'visibility', __( 'Search engines are discouraged from indexing this site (Settings → Reading)', 'answer-engine-optimization' ), false );
		}

		if ( empty( $settings['onboarding_complete'] ) ) {
			$issues[] = $this->make_issue( 'onboarding_incomplete', 0, 'warning', 'onboarding', __( 'Setup wizard not completed', 'answer-engine-optimization' ), false );
		}

		return $issues;
	}

	/**
	 * Build an issue array.
	 *
	 * @param string $id       ID.
	 * @param int    $post_id  Post ID.
	 * @param string $severity critical|warning|opportunity.
	 * @param string $category Category.
	 * @param string $message  Message.
	 * @param bool   $autofix  Auto-fixable.
	 * @return array<string, mixed>
	 */
	private function make_issue( $id, $post_id, $severity, $category, $message, $autofix ) {
		return array(
			'id'       => $id,
			'post_id'  => $post_id,
			'severity' => $severity,
			'category' => $category,
			'message'  => $message,
			'autofix'  => $autofix,
			'status'   => 'open',
		);
	}

	/**
	 * Replace the audit log with the latest run.
	 *
	 * @param array<int, array<string, mixed>> $issues Issues.
	 * @return void
	 */
	private function persist_issues( array $issues ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aeo_audit_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$table} WHERE status = 'open'" );

		foreach ( $issues as $issue ) {
			$wpdb->insert(
				$table,
				array(
					'issue_id' => $issue['id'],
					'post_id'  => (int) $issue['post_id'],
					'severity' => $issue['severity'],
					'category' => $issue['category'],
					'message'  => $issue['message'],
					'status'   => $issue['status'],
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Mark an issue fixed in the log.
	 *
	 * @param string $issue_id Issue ID.
	 * @return void
	 */
	private function mark_fixed( $issue_id ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'aeo_audit_log',
			array(
				'status'   => 'fixed',
				'fixed_at' => current_time( 'mysql' ),
			),
			array(
				'issue_id' => $issue_id,
				'status'   => 'open',
			),
			array( '%s', '%s' ),
			array( '%s', '%s' )
		);
	}
}
