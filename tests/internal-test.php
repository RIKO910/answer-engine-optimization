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
$faqs = $ai->generate_faqs( 'WordPress is a content management system used for building websites and blogs.', 3 );
if ( count( $faqs ) >= 1 ) {
	echo "[PASS] Local AI FAQ generation: " . count( $faqs ) . " FAQs\n";
	$passed++;
} else {
	echo "[FAIL] Local AI FAQ generation\n";
	$failed++;
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
