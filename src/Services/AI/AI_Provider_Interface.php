<?php
/**
 * AI provider interface.
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for AI provider implementations.
 */
interface AI_Provider_Interface {

	/**
	 * Generate FAQs from content.
	 *
	 * @param string $content Post content.
	 * @param int    $count   Number of FAQs.
	 * @return array<int, array<string, string>>
	 */
	public function generate_faqs( $content, $count = 5 );

	/**
	 * Rewrite content for AEO.
	 *
	 * @param string $content Original content.
	 * @return string
	 */
	public function rewrite_content( $content );

	/**
	 * Generate content brief.
	 *
	 * @param string $topic Topic keyword.
	 * @return array<string, mixed>
	 */
	public function generate_brief( $topic );

	/**
	 * Check if provider is configured.
	 *
	 * @return bool
	 */
	public function is_configured();
}
