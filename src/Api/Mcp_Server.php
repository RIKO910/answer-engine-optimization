<?php
/**
 * Minimal MCP (Model Context Protocol) server over the WordPress REST API.
 *
 * Endpoint: POST /wp-json/aeo-genius/v1/mcp  (Streamable HTTP transport, JSON responses).
 * Authentication: WordPress Application Passwords (HTTP Basic). Every tool call is
 * checked against the same capabilities as the admin, so an AI client can never do
 * more than the user whose password it uses.
 *
 * Tools are the plugin's abilities (see Integrations\Abilities), exposed as "aeo-{name}".
 *
 * @package AEO
 */

namespace AEO\Api;

use AEO\Integrations\Abilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * JSON-RPC 2.0 dispatcher.
 */
class Mcp_Server {

	const PROTOCOL = '2025-06-18';

	/**
	 * Register.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Route.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			'aeo-genius/v1',
			'/mcp',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'handle' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => static function () {
						// No server-initiated stream.
						return new WP_REST_Response( array( 'message' => 'Use POST.' ), 405 );
					},
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Is the MCP endpoint enabled and the user allowed?
	 *
	 * @return bool|\WP_Error
	 */
	public static function permission() {
		if ( ! \AEO\Core\Settings::get( 'mcp_enabled', false ) ) {
			return new \WP_Error( 'aeo_mcp_disabled', __( 'The MCP endpoint is turned off in AEO settings.', 'answer-engine-optimization' ), array( 'status' => 403 ) );
		}
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'aeo_mcp_auth', __( 'Authenticate with a WordPress Application Password.', 'answer-engine-optimization' ), array( 'status' => 401 ) );
		}
		return current_user_can( 'aeo_view_reports' );
	}

	/**
	 * Handle a JSON-RPC message or batch.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ) {
		$body = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( self::error( null, -32700, 'Parse error' ), 400 );
		}

		$is_batch = array_keys( $body ) === range( 0, count( $body ) - 1 ) && $body;
		$messages = $is_batch ? $body : array( $body );
		$out      = array();
		foreach ( $messages as $msg ) {
			$res = self::dispatch( is_array( $msg ) ? $msg : array() );
			if ( null !== $res ) {
				$out[] = $res;
			}
		}

		if ( ! $out ) {
			return new WP_REST_Response( null, 202 );
		}
		return new WP_REST_Response( $is_batch ? $out : $out[0], 200 );
	}

	/**
	 * Dispatch one message. Returns null for notifications.
	 *
	 * @param array<string, mixed> $msg Message.
	 * @return array<string, mixed>|null
	 */
	public static function dispatch( array $msg ) {
		$id     = $msg['id'] ?? null;
		$method = (string) ( $msg['method'] ?? '' );
		$params = is_array( $msg['params'] ?? null ) ? $msg['params'] : array();

		if ( ( $msg['jsonrpc'] ?? '' ) !== '2.0' || '' === $method ) {
			return self::error( $id, -32600, 'Invalid Request' );
		}
		// Notifications have no id and get no response.
		if ( ! array_key_exists( 'id', $msg ) ) {
			return null;
		}

		switch ( $method ) {
			case 'initialize':
				$requested = (string) ( $params['protocolVersion'] ?? self::PROTOCOL );
				return self::result(
					$id,
					array(
						'protocolVersion' => in_array( $requested, array( '2025-06-18', '2025-03-26', '2024-11-05' ), true ) ? $requested : self::PROTOCOL,
						'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
						'serverInfo'      => array(
							'name'    => 'aeo-genius',
							'title'   => get_bloginfo( 'name' ) . ' — Answer Engine Optimization',
							'version' => AEO_VERSION,
						),
						'instructions'    => __( 'Tools to inspect and improve how AI answer engines read this WordPress site: scores, schema, FAQs, llms.txt, crawler rules, AI visibility and AI traffic.', 'answer-engine-optimization' ),
					)
				);
			case 'ping':
				return self::result( $id, new \stdClass() );
			case 'tools/list':
				return self::result( $id, array( 'tools' => self::tools() ) );
			case 'tools/call':
				return self::call( $id, $params );
		}
		return self::error( $id, -32601, 'Method not found: ' . $method );
	}

	/**
	 * Tool list visible to the current user.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function tools() {
		$tools = array();
		foreach ( Abilities::definitions() as $name => $def ) {
			if ( ! current_user_can( $def['cap'] ) ) {
				continue;
			}
			$tools[] = array(
				'name'        => 'aeo-' . $name,
				'title'       => $def['label'],
				'description' => $def['description'],
				'inputSchema' => $def['input'],
				'annotations' => array(
					'readOnlyHint'    => (bool) $def['readonly'],
					'destructiveHint' => false,
					'idempotentHint'  => (bool) $def['readonly'],
					'openWorldHint'   => false,
				),
			);
		}
		return $tools;
	}

	/**
	 * tools/call.
	 *
	 * @param mixed                $id     Request id.
	 * @param array<string, mixed> $params Params.
	 * @return array<string, mixed>
	 */
	private static function call( $id, array $params ) {
		$name = (string) ( $params['name'] ?? '' );
		$args = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : array();
		$defs = Abilities::definitions();
		$key  = 0 === strpos( $name, 'aeo-' ) ? substr( $name, 4 ) : '';

		if ( '' === $key || ! isset( $defs[ $key ] ) ) {
			return self::error( $id, -32602, 'Unknown tool: ' . $name );
		}
		$def = $defs[ $key ];

		$invalid = self::validate( $args, $def['input'] );
		if ( $invalid ) {
			return self::tool_error( $id, $invalid );
		}
		if ( ! Abilities::allowed( $def, $args ) ) {
			return self::tool_error( $id, __( 'You do not have permission to use this tool on this item.', 'answer-engine-optimization' ) );
		}

		try {
			$value = call_user_func( $def['run'], $args );
		} catch ( \Throwable $e ) {
			return self::tool_error( $id, $e->getMessage() );
		}
		if ( is_wp_error( $value ) ) {
			return self::tool_error( $id, $value->get_error_message() );
		}

		$structured = is_array( $value ) ? $value : array( 'result' => $value );
		return self::result(
			$id,
			array(
				'content'           => array(
					array(
						'type' => 'text',
						'text' => is_string( $value ) ? $value : (string) wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
					),
				),
				'structuredContent' => (object) $structured,
				'isError'           => false,
			)
		);
	}

	/**
	 * Light validation against the input schema (required + basic types).
	 *
	 * @param array<string, mixed> $args   Arguments.
	 * @param array<string, mixed> $schema Schema.
	 * @return string Empty when valid.
	 */
	private static function validate( array $args, array $schema ) {
		foreach ( (array) ( $schema['required'] ?? array() ) as $req ) {
			if ( ! array_key_exists( $req, $args ) ) {
				/* translators: %s: argument name */
				return sprintf( __( 'Missing required argument: %s', 'answer-engine-optimization' ), $req );
			}
		}
		$props = is_array( $schema['properties'] ?? null ) ? $schema['properties'] : array();
		foreach ( $args as $k => $v ) {
			if ( ! isset( $props[ $k ]['type'] ) ) {
				continue;
			}
			$type = $props[ $k ]['type'];
			$ok   = true;
			switch ( $type ) {
				case 'integer':
					$ok = is_int( $v ) || ( is_string( $v ) && ctype_digit( $v ) );
					break;
				case 'string':
					$ok = is_string( $v );
					break;
				case 'array':
					$ok = is_array( $v );
					break;
				case 'boolean':
					$ok = is_bool( $v );
					break;
			}
			if ( ! $ok ) {
				/* translators: 1: argument, 2: type */
				return sprintf( __( 'Argument %1$s must be of type %2$s.', 'answer-engine-optimization' ), $k, $type );
			}
			if ( isset( $props[ $k ]['enum'] ) && ! in_array( $v, (array) $props[ $k ]['enum'], true ) ) {
				/* translators: %s: argument */
				return sprintf( __( 'Argument %s has an unsupported value.', 'answer-engine-optimization' ), $k );
			}
		}
		return '';
	}

	/**
	 * Result envelope.
	 *
	 * @param mixed $id     Id.
	 * @param mixed $result Result.
	 * @return array<string, mixed>
	 */
	private static function result( $id, $result ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Tool-level error (reported to the model, not a protocol error).
	 *
	 * @param mixed  $id      Id.
	 * @param string $message Message.
	 * @return array<string, mixed>
	 */
	private static function tool_error( $id, $message ) {
		return self::result(
			$id,
			array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => $message,
					),
				),
				'isError' => true,
			)
		);
	}

	/**
	 * Protocol error envelope.
	 *
	 * @param mixed  $id      Id.
	 * @param int    $code    Code.
	 * @param string $message Message.
	 * @return array<string, mixed>
	 */
	private static function error( $id, $code, $message ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}

	/**
	 * Connection info for the admin.
	 *
	 * @return array<string, mixed>
	 */
	public static function info() {
		$url = rest_url( 'aeo-genius/v1/mcp' );
		return array(
			'enabled'  => (bool) \AEO\Core\Settings::get( 'mcp_enabled', false ),
			'url'      => $url,
			'tools'    => count( Abilities::definitions() ),
			'abilities_api' => function_exists( 'wp_register_ability' ),
			'app_passwords' => function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available(),
			'profile_url'   => admin_url( 'profile.php#application-passwords-section' ),
			'config'   => array(
				'mcpServers' => array(
					'wordpress-aeo' => array(
						'command' => 'npx',
						'args'    => array( '-y', 'mcp-remote', $url, '--header', 'Authorization: Basic ${AUTH}' ),
						'env'     => array( 'AUTH' => 'base64(username:application-password)' ),
					),
				),
			),
		);
	}
}
