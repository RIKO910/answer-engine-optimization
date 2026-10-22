<?php
/**
 * Internal plugin structure and class loading test.
 * Run: php tests/internal-test.php
 *
 * @package AEO
 */

// Stub WordPress environment for standalone testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num_words = 55 ) {
		$words = explode( ' ', $text );
		return implode( ' ', array_slice( $words, 0, $num_words ) );
	}
}

foreach ( array(
	'strip_shortcodes'        => function ( $t ) { return preg_replace( '/\[[^\]]*\]/', '', $t ); },
	'sanitize_text_field'     => function ( $t ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $t ) ) ); },
	'sanitize_textarea_field' => function ( $t ) { return trim( strip_tags( (string) $t ) ); },
	'wp_html_excerpt'         => function ( $t, $n, $more = '' ) { return mb_strlen( $t ) > $n ? mb_substr( $t, 0, $n ) . $more : $t; },
	'esc_html__'              => function ( $t ) { return $t; },
	'apply_filters'           => function ( $tag, $value ) { return $value; },
	'wp_parse_url'            => function ( $u, $c = -1 ) { return parse_url( $u, $c ); },
) as $aeo_fn => $aeo_impl ) {
	if ( ! function_exists( $aeo_fn ) ) {
		$GLOBALS[ 'aeo_stub_' . $aeo_fn ] = $aeo_impl;
		eval( "function {$aeo_fn}() { return call_user_func_array( \$GLOBALS['aeo_stub_{$aeo_fn}'], func_get_args() ); }" ); // phpcs:ignore
	}
}

$plugin_dir = dirname( __DIR__ );

spl_autoload_register(
	function ( $class ) use ( $plugin_dir ) {
		$prefix   = 'AEO\\';
		$base_dir = $plugin_dir . '/src/';
		$len      = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}
		$relative = substr( $class, $len );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

$classes = array(
	'AEO\\Core\\Activator',
	'AEO\\Core\\Settings',
	'AEO\\Plugin',
	'AEO\\Api\\Rest_Api',
	'AEO\\Services\\Score_Engine',
	'AEO\\Services\\Audit_Service',
	'AEO\\Services\\Schema_Service',
	'AEO\\Services\\Analytics_Service',
	'AEO\\Services\\Citation_Service',
	'AEO\\Services\\Encryption',
	'AEO\\Services\\AI\\AI_Manager',
	'AEO\\Services\\AI\\Local_AI_Provider',
	'AEO\\Services\\AI\\OpenAI_Provider',
	'AEO\\Services\\AI\\Anthropic_Provider',
	'AEO\\Services\\AI\\Gemini_Provider',
	'AEO\\Services\\AI\\AI_Response',
	'AEO\\Services\\Seo_Compat',
	'AEO\\Services\\Brief_Service',
	'AEO\\Services\\Notification_Service',
	'AEO\\Core\\Upgrader',
	'AEO\\Admin\\Admin_Menu',
	'AEO\\Admin\\Assets',
	'AEO\\Frontend\\Frontend_Manager',
	'AEO\\Frontend\\WooCommerce_Integration',
	'AEO\\CLI\\Commands',
);

$passed = 0;
$failed = 0;

echo "Answer Engine Optimization — Internal Test\n";
echo str_repeat( '=', 50 ) . "\n";

foreach ( $classes as $class ) {
	if ( class_exists( $class ) ) {
		echo "[PASS] {$class}\n";
		$passed++;
	} else {
		echo "[FAIL] {$class} — not found\n";
		$failed++;
	}
}

echo "\nBuild Assets:\n";
$js_glob = glob( $plugin_dir . '/assets/build/assets/main-*.js' );
if ( ! empty( $js_glob ) ) {
	echo '[PASS] ' . basename( $js_glob[0] ) . " (JS bundle)\n";
	$passed++;
} else {
	echo "[FAIL] JS bundle not found\n";
	$failed++;
}

$css_glob = glob( $plugin_dir . '/assets/build/assets/main-*.css' );
if ( ! empty( $css_glob ) ) {
	echo '[PASS] ' . basename( $css_glob[0] ) . " (CSS bundle)\n";
	$passed++;
} else {
	echo "[FAIL] CSS bundle not found\n";
	$failed++;
}

if ( file_exists( $plugin_dir . '/assets/build/.vite/manifest.json' ) ) {
	echo "[PASS] Vite manifest\n";
	$passed++;
} else {
	echo "[FAIL] Vite manifest\n";
	$failed++;
}

$types = AEO\Services\Schema_Service::$types;
if ( count( $types ) >= 40 ) {
	echo "\n[PASS] Schema types: " . count( $types ) . " (>= 40 required)\n";
	$passed++;
} else {
	echo "\n[FAIL] Schema types: " . count( $types ) . " (< 40 required)\n";
	$failed++;
}

$ai   = new AEO\Services\AI\Local_AI_Provider();
$faqs = $ai->generate_faqs( '<p>Intro.</p><h2>How do I book?</h2><p>Call reception. It takes a minute.</p><h2>Parking</h2><p>Parking is free.</p>', 3 );
if ( 2 === count( $faqs ) && 'How do I book?' === $faqs[0]['question'] && 'What should I know about parking?' === $faqs[1]['question'] ) {
	echo "[PASS] Local AI FAQ generation: " . count( $faqs ) . " FAQs\n";
	$passed++;
} else {
	echo "[FAIL] Local AI FAQ generation\n";
	$failed++;
}

$checks = array(
	'JSON in code fence'      => array( 'a' => 1 ) === AEO\Services\AI\AI_Response::extract_json( "Sure!\n```json\n{\"a\":1}\n```" ),
	'FAQ normalize (wrapped)' => 1 === count( AEO\Services\AI\AI_Response::normalize_faqs( array( 'faqs' => array( array( 'question' => 'Q?', 'answer' => 'A' ), array( 'question' => 'q?', 'answer' => 'dup' ) ) ) ) ),
	'Schema types from HTML'  => array( 'Hotel', 'PostalAddress' ) === AEO\Services\Citation_Service::extract_schema_types( '<script type="application/ld+json">{"@graph":[{"@type":"Hotel","address":{"@type":"PostalAddress"}}]}</script>' ),
	'Perplexity source URLs'  => array( 'https://a.com/', 'https://b.com/' ) === AEO\Services\Citation_Service::extract_source_urls( array( 'citations' => array( 'https://a.com/', 'javascript:x' ), 'search_results' => array( array( 'url' => 'https://b.com/' ) ) ) ),
	'Validate required props' => false === ( new AEO\Services\Schema_Service() )->validate( array( '@type' => 'Hotel', 'name' => 'X' ) )['valid'],
	'Unicode word count'      => 3 === AEO\Services\Score_Engine::word_count( 'আমার সোনার বাংলা' ),
);
foreach ( $checks as $label => $ok ) {
	echo ( $ok ? '[PASS] ' : '[FAIL] ' ) . $label . "\n";
	$ok ? $passed++ : $failed++;
}

$encrypted = AEO\Services\Encryption::encrypt( 'test-key-123' );
$decrypted = AEO\Services\Encryption::decrypt( $encrypted );
if ( 'test-key-123' === $decrypted ) {
	echo "[PASS] Encryption round-trip\n";
	$passed++;
} else {
	echo "[FAIL] Encryption round-trip\n";
	$failed++;
}

echo "\n" . str_repeat( '=', 50 ) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";

exit( $failed > 0 ? 1 : 0 );
