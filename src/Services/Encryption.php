<?php
/**
 * API key encryption service.
 *
 * @package AEO
 */

namespace AEO\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts and decrypts sensitive data using WordPress salts.
 */
class Encryption {

	/**
	 * Encrypt a value.
	 *
	 * @param string $value Plain text value.
	 * @return string
	 */
	public static function encrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$key    = self::get_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );

		// Base64-encode the IV separately so its raw bytes can never collide with
		// the "::" delimiter (the OpenSSL ciphertext is already base64, no colons).
		return base64_encode( $iv ) . '::' . $cipher; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $encrypted Encrypted value.
	 * @return string
	 */
	public static function decrypt( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		if ( false === strpos( $encrypted, '::' ) ) {
			return '';
		}

		list( $iv_b64, $cipher ) = explode( '::', $encrypted, 2 );
		$iv  = base64_decode( $iv_b64 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$key = self::get_key();

		if ( strlen( $iv ) !== 16 ) {
			return '';
		}

		return openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv ) ?: '';
	}

	/**
	 * Derive encryption key from WordPress salts.
	 *
	 * @return string
	 */
	private static function get_key() {
		$auth_key        = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'aeo-test-auth-key';
		$secure_auth_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'aeo-test-secure-key';
		$logged_in_key   = defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : 'aeo-test-logged-key';
		return hash( 'sha256', $auth_key . $secure_auth_key . $logged_in_key );
	}
}
