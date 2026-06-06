<?php
/**
 * Site audit service.
 *
 * @package AEO
 */

namespace AEO\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Full-site AEO audit with auto-fix engine.
 */
class Audit_Service {

	/**
	 * Run full site audit.
	 *
	 * @return array<string, mixed>
	 */
	public function run_site_audit() {
		$issues = array();
		$posts  = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			$issues = array_merge( $issues, $this->audit_post( $post_id ) );
		}

		$issues = array_merge( $issues, $this->audit_global() );

		$summary = array(
			'critical'    => 0,
			'warning'     => 0,
			'opportunity' => 0,
			'total'       => count( $issues ),
		);

		foreach ( $issues as $issue ) {
			$summary[ $issue['severity'] ]++;
		}

		$results = array(
			'scanned_at' => current_time( 'mysql' ),
			'summary'    => $summary,
			'issues'     => $issues,
		);

		update_option( 'aeo_optimizer_audit_results', $results );
		$this->persist_issues( $issues );

		return $results;
	}

	/**
	 * Get cached audit results.
	 *
	 * @return array<string, mixed>
	 */
	public function get_results() {
		$results = get_option( 'aeo_optimizer_audit_results', array() );
		return is_array( $results ) ? $results : array( 'issues' => array(), 'summary' => array() );
	}

	/**
	 * Auto-fix a specific issue.
	 *
	 * @param string $issue_id Issue identifier.
	 * @return array<string, mixed>
	 */
	public function auto_fix( $issue_id ) {
		$results = $this->get_results();
		$fixed   = false;
		$message = __( 'Issue could not be auto-fixed.', 'answer-engine-optimization' );

		foreach ( $results['issues'] as &$issue ) {
			if ( $issue['id'] !== $issue_id ) {
				continue;
			}

			switch ( $issue['category'] ) {
				case 'missing_schema':
					if ( ! empty( $issue['post_id'] ) ) {
						$schema = array(
							'@context' => 'https://schema.org',
							'@type'    => 'Article',
							'headline' => get_the_title( $issue['post_id'] ),
						);
						update_post_meta( $issue['post_id'], '_aeo_schema', wp_json_encode( $schema ) );
						$fixed   = true;
						$message = __( 'Article schema applied.', 'answer-engine-optimization' );
					}
					break;

				case 'missing_faq':
					if ( ! empty( $issue['post_id'] ) ) {
						$post = get_post( $issue['post_id'] );
						$faq  = array(
							array(
								'question' => sprintf(
									/* translators: %s: post title */
									__( 'What is %s?', 'answer-engine-optimization' ),
									get_the_title( $post )
								),
								'answer'   => wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
							),
						);
						update_post_meta( $issue['post_id'], '_aeo_faq_items', $faq );
						$fixed   = true;
						$message = __( 'Default FAQ added.', 'answer-engine-optimization' );
					}
					break;

				case 'missing_og':
					update_option(
						'aeo_optimizer_settings',
						array_merge(
							\AEO\Core\Settings::get_all(),
							array( 'aeo_enable_schema' => 1 )
						)
					);
					$fixed   = true;
					$message = __( 'Open Graph module enabled.', 'answer-engine-optimization' );
					break;

				case 'thin_content':
					$message = __( 'Thin content requires manual expansion.', 'answer-engine-optimization' );
					break;
			}

			if ( $fixed ) {
				$issue['status'] = 'fixed';
			}
		}

		update_option( 'aeo_optimizer_audit_results', $results );

		return array(
			'fixed'   => $fixed,
			'message' => $message,
		);
	}

	/**
	 * Audit a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_post( $post_id ) {
		$issues = array();
		$post   = get_post( $post_id );
		$text   = wp_strip_all_tags( $post->post_content );

		if ( ! get_post_meta( $post_id, '_aeo_schema', true ) ) {
			$issues[] = $this->make_issue(
				"missing_schema_{$post_id}",
				$post_id,
				'warning',
				'missing_schema',
				sprintf(
					/* translators: %s: post title */
					__( 'Missing custom schema on "%s"', 'answer-engine-optimization' ),
					get_the_title( $post_id )
				),
				true
			);
		}

		$faqs = get_post_meta( $post_id, '_aeo_faq_items', true );
		if ( empty( $faqs ) ) {
			$issues[] = $this->make_issue(
				"missing_faq_{$post_id}",
				$post_id,
				'opportunity',
				'missing_faq',
				sprintf(
					/* translators: %s: post title */
					__( 'No FAQs on "%s" — high-impact AEO opportunity', 'answer-engine-optimization' ),
					get_the_title( $post_id )
				),
				true
			);
		}

		if ( str_word_count( $text ) < 300 ) {
			$issues[] = $this->make_issue(
				"thin_content_{$post_id}",
				$post_id,
				'warning',
				'thin_content',
				sprintf(
					/* translators: %s: post title */
					__( 'Thin content on "%s" (under 300 words)', 'answer-engine-optimization' ),
					get_the_title( $post_id )
				),
				false
			);
		}

		if ( ! get_post_meta( $post_id, '_aeo_direct_answer', true ) ) {
			$issues[] = $this->make_issue(
				"missing_answer_{$post_id}",
				$post_id,
				'opportunity',
				'missing_direct_answer',
				sprintf(
					/* translators: %s: post title */
					__( 'No direct answer set for "%s"', 'answer-engine-optimization' ),
					get_the_title( $post_id )
				),
				false
			);
		}

		return $issues;
	}

	/**
	 * Audit global site settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function audit_global() {
		$issues   = array();
		$settings = \AEO\Core\Settings::get_all();

		if ( empty( $settings['business_info']['name'] ) ) {
			$issues[] = $this->make_issue(
				'missing_org_name',
				0,
				'critical',
				'missing_organization',
				__( 'Organization name not configured', 'answer-engine-optimization' ),
				true
			);
		}

		if ( empty( $settings['onboarding_complete'] ) ) {
			$issues[] = $this->make_issue(
				'onboarding_incomplete',
				0,
				'warning',
				'onboarding',
				__( 'Setup wizard not completed', 'answer-engine-optimization' ),
				true
			);
		}

		return $issues;
	}

	/**
	 * Create issue array.
	 *
	 * @param string $id        Issue ID.
	 * @param int    $post_id   Post ID.
	 * @param string $severity  Severity level.
	 * @param string $category  Issue category.
	 * @param string $message   Issue message.
	 * @param bool   $autofix   Can auto-fix.
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
	 * Persist issues to audit log table.
	 *
	 * @param array<int, array<string, mixed>> $issues Issues list.
	 * @return void
	 */
	private function persist_issues( array $issues ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aeo_audit_log';

		foreach ( $issues as $issue ) {
			$wpdb->replace(
				$table,
				array(
					'issue_id' => $issue['id'],
					'post_id'  => $issue['post_id'],
					'severity' => $issue['severity'],
					'category' => $issue['category'],
					'message'  => $issue['message'],
					'status'   => $issue['status'],
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}
}
