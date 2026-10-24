<?php
/**
 * License handler for Answer Engine Optimization Pro.
 *
 * Talks to the QueueCommerce license API (https://www.queuecommerce.com/wp-json/qc/v1/license/…):
 *   activate   POST license_key, domain, product
 *   verify     POST license_key, product
 *   status     POST license_key, domain
 *   deactivate POST license_key, domain
 *
 * Lives in the FREE plugin so the License page is always reachable, even before
 * the Pro add-on is installed. Pro features need BOTH the Pro plugin and an active
 * license (see Pro::licensed()).
 *
 * @package AEO
 */

namespace AEO\Core;

use AEO\Services\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * License.
 */
class License {

	const SERVER  = 'https://www.queuecommerce.com';
	const PRODUCT = 'Answer Engine Optimization Pro';
	const CRON    = 'aeo_daily_license_check';

	const OPT_KEY        = 'aeo_license_key';
	const OPT_STATUS     = 'aeo_license_status';
	const OPT_DATA       = 'aeo_license_data';
	const OPT_EXPIRY     = 'aeo_license_expiry';
	const OPT_LAST_CHECK = 'aeo_license_last_check';
	const OPT_ERROR      = 'aeo_license_last_error';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( self::CRON, array( __CLASS__, 'verify' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * Daily re-check while a key is stored.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( self::key() && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * License server base URL (filterable for staging servers and tests).
	 *
	 * @return string
	 */
	public static function server() {
		return untrailingslashit( (string) apply_filters( 'aeo_license_server', self::SERVER ) );
	}

	/**
	 * Stored license key (decrypted).
	 *
	 * @return string
	 */
	public static function key() {
		$stored = (string) get_option( self::OPT_KEY, '' );
		if ( '' === $stored ) {
			return '';
		}
		$plain = Encryption::decrypt( $stored );
		return is_string( $plain ) && '' !== $plain ? $plain : $stored;
	}

	/**
	 * Key with the middle hidden, for display.
	 *
	 * @return string
	 */
	public static function masked_key() {
		$key = self::key();
		if ( strlen( $key ) <= 8 ) {
			return $key ? str_repeat( '•', strlen( $key ) ) : '';
		}
		return substr( $key, 0, 4 ) . str_repeat( '•', max( 4, strlen( $key ) - 8 ) ) . substr( $key, -4 );
	}

	/**
	 * Domain sent to the server.
	 *
	 * @return string
	 */
	public static function domain() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * POST to the license server.
	 *
	 * @param string               $endpoint activate|status|deactivate|update-check.
	 * @param array<string, mixed> $body     Body.
	 * @return array<string, mixed>|\WP_Error Decoded JSON.
	 */
	public static function request( $endpoint, array $body ) {
		// LiteSpeed's bot check sometimes answers with a "please wait" page while
		// the server is busy. Try again once, then through ?rest_route=, which
		// WordPress serves the same API from.
		$urls = array(
			self::server() . '/wp-json/qc/v1/license/' . $endpoint,
			self::server() . '/wp-json/qc/v1/license/' . $endpoint,
			add_query_arg( 'rest_route', '/qc/v1/license/' . $endpoint, self::server() . '/' ),
		);
		foreach ( $urls as $i => $url ) {
			if ( 1 === $i ) {
				sleep( 2 );
			}
			$response = wp_remote_post(
				$url,
				array(
					'body'    => $body,
					'timeout' => 15,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);
			if ( is_wp_error( $response ) || ! self::is_bot_challenge( (string) wp_remote_retrieve_body( $response ) ) ) {
				break;
			}
		}
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'aeo_license_connect',
				/* translators: %s: error message */
				sprintf( __( 'Could not connect to the license server: %s', 'answer-engine-optimization' ), $response->get_error_message() )
			);
		}
		$body = (string) wp_remote_retrieve_body( $response );
		$data = self::parse_json( $body );
		if ( ! is_array( $data ) && self::is_bot_challenge( $body ) ) {
			update_option( 'aeo_license_last_response', array( 'code' => (int) wp_remote_retrieve_response_code( $response ), 'body' => substr( $body, 0, 2000 ), 'time' => time() ), false );
			return new \WP_Error(
				'aeo_license_blocked',
				__( 'The license server\'s bot protection blocked this request (it sent a "please wait, verifying" page instead of an answer). Nothing is wrong with your key. Please try again in a few minutes; if it keeps happening, contact support so they can allow license requests from your site.', 'answer-engine-optimization' )
			);
		}
		if ( ! is_array( $data ) ) {
			$code    = (int) wp_remote_retrieve_response_code( $response );
			$preview = self::preview( $body );
			update_option( 'aeo_license_last_response', array( 'code' => $code, 'body' => substr( $body, 0, 2000 ), 'time' => time() ), false );
			return new \WP_Error(
				'aeo_license_response',
				'' === $preview
					/* translators: %d: HTTP status */
					? sprintf( __( 'The license server returned an empty response (HTTP %d).', 'answer-engine-optimization' ), $code )
					/* translators: 1: HTTP status, 2: start of the response */
					: sprintf( __( 'The license server returned an unexpected response (HTTP %1$d): %2$s', 'answer-engine-optimization' ), $code, $preview )
			);
		}
		// Normalise "success": the server may send true, 1 or "true".
		if ( isset( $data['success'] ) ) {
			$data['success'] = filter_var( $data['success'], FILTER_VALIDATE_BOOLEAN );
		}
		return $data;
	}

	/**
	 * Decode the server's JSON even when something was printed around it: a UTF-8
	 * byte-order mark, blank lines, or a PHP notice/warning from the server
	 * (e.g. "Deprecated: …" before the JSON on a successful activation).
	 *
	 * @param string $body Raw body.
	 * @return array<string, mixed>|null
	 */
	public static function parse_json( $body ) {
		$body = trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $body ) );
		if ( '' === $body ) {
			return null;
		}
		$data = json_decode( $body, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		// Look for the response object itself, preferably the one with "success".
		// Capped, so a large HTML error page cannot make this slow.
		$tries = 0;
		foreach ( array( '{"success"', '{ "success"', '{' ) as $needle ) {
			$start = strpos( $body, $needle );
			while ( false !== $start ) {
				$end = strrpos( $body, '}' );
				while ( false !== $end && $end > $start && ++$tries < 60 ) {
					$data = json_decode( substr( $body, $start, $end - $start + 1 ), true );
					if ( is_array( $data ) && ( isset( $data['success'] ) || '{' !== $needle ) ) {
						return $data;
					}
					$end = strrpos( substr( $body, 0, $end ), '}' );
				}
				$start = strpos( $body, $needle, $start + 1 );
			}
		}
		return null;
	}

	/**
	 * Is this a web-server bot check (LiteSpeed reCAPTCHA, Cloudflare, etc.) instead
	 * of an API answer? A plugin cannot and must not pass such a check; the server
	 * has to exempt the license API.
	 *
	 * @param string $body Body.
	 * @return bool
	 */
	private static function is_bot_challenge( $body ) {
		$body = strtolower( substr( (string) $body, 0, 20000 ) );
		foreach ( array( 'request is being verified', 'lsrecaptcha', 'one moment, please', 'checking your browser', 'cf-chl', 'challenge-platform', 'just a moment...' ) as $marker ) {
			if ( false !== strpos( $body, $marker ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Short, tag-free start of a response for error messages.
	 *
	 * @param string $body Body.
	 * @return string
	 */
	private static function preview( $body ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $body ) ) );
		return strlen( $text ) > 160 ? substr( $text, 0, 160 ) . '…' : $text;
	}

	/**
	 * Activate a key on this domain.
	 *
	 * @param string $license_key Key.
	 * @return array{success: bool, message: string}
	 */
	public static function activate( $license_key ) {
		$license_key = trim( (string) $license_key );
		if ( '' === $license_key ) {
			return array(
				'success' => false,
				'message' => __( 'Enter a license key.', 'answer-engine-optimization' ),
			);
		}

		$data = self::request(
			'activate',
			array(
				'license_key' => $license_key,
				'domain'      => self::domain(),
				'product'     => self::PRODUCT,
			)
		);
		if ( is_wp_error( $data ) ) {
			update_option( self::OPT_ERROR, $data->get_error_message(), false );
			return array(
				'success' => false,
				'message' => $data->get_error_message(),
			);
		}

		if ( ! empty( $data['success'] ) ) {
			$info = is_array( $data['data'] ?? null ) ? $data['data'] : array();
			update_option( self::OPT_KEY, Encryption::encrypt( $license_key ), false );
			update_option( self::OPT_STATUS, 'active', false );
			update_option( self::OPT_DATA, $info, false );
			update_option( self::OPT_EXPIRY, (string) ( $info['expiry_date'] ?? '' ), false );
			update_option( self::OPT_LAST_CHECK, time(), false );
			delete_option( self::OPT_ERROR );
			delete_transient( 'aeo_pro_update_info' );
			self::schedule();
			return array(
				'success' => true,
				'message' => (string) ( $data['message'] ?? __( 'License activated.', 'answer-engine-optimization' ) ),
			);
		}

		$message = (string) ( $data['message'] ?? __( 'The license could not be activated.', 'answer-engine-optimization' ) );
		update_option( self::OPT_ERROR, $message, false );
		return array(
			'success' => false,
			'message' => $message,
		);
	}

	/**
	 * Re-check the stored key (daily cron, after updates, on demand).
	 *
	 * A failed connection keeps the current status, so a server outage never
	 * switches Pro off on a paying site.
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function verify( $retried = false ) {
		$key = self::key();
		if ( '' === $key ) {
			return array(
				'success' => false,
				'message' => __( 'No license key saved.', 'answer-engine-optimization' ),
			);
		}

		$data = self::request(
			'status',
			array(
				'license_key' => $key,
				'domain'      => self::domain(),
				'product'     => self::PRODUCT,
			)
		);
		if ( is_wp_error( $data ) ) {
			update_option( self::OPT_ERROR, $data->get_error_message(), false );
			return array(
				'success' => false,
				'message' => $data->get_error_message(),
			);
		}

		update_option( self::OPT_LAST_CHECK, time(), false );

		if ( ! empty( $data['success'] ) ) {
			$info = is_array( $data['data'] ?? null ) ? $data['data'] : array();

			// The site moved to a new domain (staging → live): claim a slot for it once.
			if ( empty( $info['is_activated_on_domain'] ) && empty( $info['is_expired'] ) && empty( $info['problem'] ) && 'active' === ( $info['status'] ?? 'active' ) && ! $retried ) {
				$again = self::activate( $key );
				if ( $again['success'] ) {
					return self::verify( true );
				}
			}

			update_option( self::OPT_DATA, $info, false );
			if ( array_key_exists( 'expiry_date', $info ) ) {
				update_option( self::OPT_EXPIRY, (string) $info['expiry_date'], false );
			}
			// QC License System 2.x sends is_valid; 1.x only the separate flags.
			$ok = isset( $info['is_valid'] )
				? (bool) $info['is_valid']
				: ( empty( $info['is_expired'] ) && ! empty( $info['is_activated_on_domain'] ) && 'suspended' !== ( $info['status'] ?? '' ) );
			update_option( self::OPT_STATUS, $ok ? 'active' : 'inactive', false );
			$message = $ok ? __( 'License is active.', 'answer-engine-optimization' ) : self::problem_message( $info, (string) ( $data['message'] ?? '' ) );
			if ( $ok ) {
				delete_option( self::OPT_ERROR );
			} else {
				update_option( self::OPT_ERROR, $message, false );
			}
			return array(
				'success' => $ok,
				'message' => $message,
			);
		}

		// The server answered but rejected the key (revoked, refunded, unknown).
		$message = (string) ( $data['message'] ?? __( 'License is not valid.', 'answer-engine-optimization' ) );
		update_option( self::OPT_STATUS, 'inactive', false );
		update_option( self::OPT_ERROR, $message, false );
		return array(
			'success' => false,
			'message' => $message,
		);
	}

	/**
	 * Explain why a license the server knows about is not usable here.
	 *
	 * @param array<string, mixed> $info     Status data.
	 * @param string               $fallback Server message.
	 * @return string
	 */
	private static function problem_message( array $info, $fallback ) {
		$problem = (string) ( $info['problem'] ?? '' );
		if ( 'suspended' === $problem || 'suspended' === ( $info['status'] ?? '' ) ) {
			return __( 'This license has been suspended. Contact support or check your order.', 'answer-engine-optimization' );
		}
		if ( 'expired' === $problem || ! empty( $info['is_expired'] ) ) {
			return __( 'License has expired. Renew it to keep Pro features and updates.', 'answer-engine-optimization' );
		}
		if ( 'wrong_product' === $problem ) {
			return __( 'This key belongs to a different product.', 'answer-engine-optimization' );
		}
		if ( empty( $info['is_activated_on_domain'] ) ) {
			return __( 'License is not activated on this domain. Activate it again, or free a slot on another site.', 'answer-engine-optimization' );
		}
		return '' !== $fallback ? $fallback : __( 'License is not valid.', 'answer-engine-optimization' );
	}

	/**
	 * Remove the key from this site and free the activation slot on the server
	 * (best effort: the site is always cleared locally).
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function deactivate() {
		$key = self::key();
		if ( '' !== $key ) {
			self::request(
				'deactivate',
				array(
					'license_key' => $key,
					'domain'      => self::domain(),
				)
			);
		}
		foreach ( array( self::OPT_KEY, self::OPT_STATUS, self::OPT_DATA, self::OPT_EXPIRY, self::OPT_LAST_CHECK, self::OPT_ERROR ) as $opt ) {
			delete_option( $opt );
		}
		delete_transient( 'aeo_pro_update_info' );
		wp_clear_scheduled_hook( self::CRON );
		return array(
			'success' => true,
			'message' => __( 'License removed from this site.', 'answer-engine-optimization' ),
		);
	}

	/**
	 * Is the stored license active and not past its expiry date?
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( 'active' !== get_option( self::OPT_STATUS, '' ) ) {
			return false;
		}
		$expiry = (string) get_option( self::OPT_EXPIRY, '' );
		if ( '' !== $expiry && strtotime( $expiry ) && strtotime( $expiry ) < time() ) {
			update_option( self::OPT_STATUS, 'inactive', false );
			return false;
		}
		return true;
	}

	/**
	 * License details for the admin.
	 *
	 * @return array<string, mixed>
	 */
	public static function info() {
		$data   = get_option( self::OPT_DATA, array() );
		$data   = is_array( $data ) ? $data : array();
		$expiry = (string) get_option( self::OPT_EXPIRY, '' );
		$active = self::is_active();
		return array(
			'has_key'      => '' !== self::key(),
			'key'          => self::masked_key(),
			'status'       => (string) get_option( self::OPT_STATUS, '' ),
			'active'       => $active,
			'expiry'       => $expiry,
			'lifetime'     => $active && '' === $expiry,
			'activations'  => isset( $data['active_sites'], $data['total_sites'] ) ? array(
				'active' => (int) $data['active_sites'],
				'total'  => (int) $data['total_sites'],
			) : null,
			'last_check'   => (int) get_option( self::OPT_LAST_CHECK, 0 ),
			'last_error'   => (string) get_option( self::OPT_ERROR, '' ),
			'domain'       => self::domain(),
			'product'      => self::PRODUCT,
		);
	}

	/**
	 * REST routes (the admin is a React app, so no admin-ajax here).
	 *
	 * @return void
	 */
	public static function routes() {
		$perm = static function () {
			return current_user_can( 'manage_options' );
		};
		$respond = static function ( array $result ) {
			return new \WP_REST_Response(
				$result + array(
					'license' => self::info(),
					'pro'     => Pro::data(),
				),
				200 // The admin reads `success`; a rejected key is an answer, not an HTTP error.
			);
		};

		register_rest_route(
			'aeo-genius/v1',
			'/license',
			array(
				'methods'             => 'GET',
				'callback'            => static function () use ( $respond ) {
					return $respond( array( 'success' => true ) );
				},
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			'aeo-genius/v1',
			'/license/activate',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( \WP_REST_Request $r ) use ( $respond ) {
					$p = (array) $r->get_json_params();
					return $respond( self::activate( sanitize_text_field( (string) ( $p['license_key'] ?? '' ) ) ) );
				},
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			'aeo-genius/v1',
			'/license/check',
			array(
				'methods'             => 'POST',
				'callback'            => static function () use ( $respond ) {
					return $respond( self::verify() );
				},
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			'aeo-genius/v1',
			'/license/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => static function () use ( $respond ) {
					return $respond( self::deactivate() );
				},
				'permission_callback' => $perm,
			)
		);
	}

	/**
	 * Notices on the Plugins screen only (the AEO admin shows its own banner).
	 *
	 * @return void
	 */
	public static function notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'manage_options' ) || ! Pro::plugin_active() ) {
			return;
		}
		$url = admin_url( 'admin.php?page=aeo-license' );
		if ( '' === self::key() ) {
			$msg = __( 'Activate your license to switch on the Pro features.', 'answer-engine-optimization' );
			$cls = 'notice-warning';
		} elseif ( ! self::is_active() ) {
			$expiry = (string) get_option( self::OPT_EXPIRY, '' );
			$msg    = '' !== $expiry && strtotime( $expiry ) < time()
				? __( 'Your license has expired. Renew it to keep the Pro features.', 'answer-engine-optimization' )
				: __( 'Your license is not active. Pro features are switched off.', 'answer-engine-optimization' );
			$cls    = 'notice-error';
		} else {
			return;
		}
		printf(
			'<div class="notice %1$s"><p><strong>%2$s</strong> %3$s <a href="%4$s">%5$s</a></p></div>',
			esc_attr( $cls ),
			esc_html__( 'Answer Engine Optimization Pro:', 'answer-engine-optimization' ),
			esc_html( $msg ),
			esc_url( $url ),
			esc_html__( 'Manage license', 'answer-engine-optimization' )
		);
	}
}
