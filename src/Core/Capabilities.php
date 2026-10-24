<?php
/**
 * Plugin capabilities and the role manager.
 *
 * Capabilities are granted dynamically from the "Roles" setting through the
 * user_has_cap filter, so nothing is written to the roles table and removing
 * the plugin leaves no traces. Administrators always have every capability.
 *
 * @package AEO
 */

namespace AEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Capabilities.
 */
class Capabilities {

	const CAPS = array(
		'aeo_manage_settings' => 'Change plugin settings, API keys and crawler rules',
		'aeo_view_reports'    => 'View dashboard, analytics and reports',
		'aeo_edit_schema'     => 'Edit schema, FAQs and templates',
		'aeo_use_ai'          => 'Use AI features (spends API credits)',
	);

	const DEFAULTS = array(
		'aeo_manage_settings' => array( 'administrator' ),
		'aeo_view_reports'    => array( 'administrator', 'editor' ),
		'aeo_edit_schema'     => array( 'administrator', 'editor' ),
		'aeo_use_ai'          => array( 'administrator', 'editor' ),
	);

	/**
	 * Register the filter.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'user_has_cap', array( __CLASS__, 'grant' ), 10, 4 );
	}

	/**
	 * Role map (cap => roles).
	 *
	 * @return array<string, string[]>
	 */
	public static function map() {
		// Custom role mapping is a Pro feature; free installs use the defaults.
		$saved = apply_filters( 'aeo_custom_role_caps', false ) ? Settings::get( 'role_caps', array() ) : array();
		$map   = array();
		foreach ( self::DEFAULTS as $cap => $roles ) {
			$map[ $cap ] = isset( $saved[ $cap ] ) && is_array( $saved[ $cap ] ) ? array_values( array_unique( array_merge( array( 'administrator' ), $saved[ $cap ] ) ) ) : $roles;
		}
		return $map;
	}

	/**
	 * user_has_cap filter.
	 *
	 * @param array<string, bool> $allcaps All caps.
	 * @param string[]            $caps    Requested primitive caps.
	 * @param array<int, mixed>   $args    Args.
	 * @param \WP_User            $user    User.
	 * @return array<string, bool>
	 */
	public static function grant( $allcaps, $caps, $args, $user ) {
		$wanted = array_intersect( (array) $caps, array_keys( self::CAPS ) );
		if ( ! $wanted || ! $user instanceof \WP_User ) {
			return $allcaps;
		}
		$map = self::map();
		foreach ( $wanted as $cap ) {
			if ( ! empty( $allcaps['manage_options'] ) || array_intersect( (array) $user->roles, $map[ $cap ] ) ) {
				$allcaps[ $cap ] = true;
			}
		}
		return $allcaps;
	}

	/**
	 * Roles for the UI.
	 *
	 * @return array<int, array{slug: string, name: string}>
	 */
	public static function roles() {
		$out = array();
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$out[] = array(
				'slug' => $slug,
				'name' => translate_user_role( $name ),
			);
		}
		return $out;
	}

	/**
	 * Sanitize a submitted map.
	 *
	 * @param mixed $input Input.
	 * @return array<string, string[]>
	 */
	public static function sanitize( $input ) {
		$valid = array_keys( wp_roles()->get_names() );
		$out   = array();
		foreach ( array_keys( self::CAPS ) as $cap ) {
			$roles       = isset( $input[ $cap ] ) && is_array( $input[ $cap ] ) ? array_map( 'sanitize_key', $input[ $cap ] ) : array();
			$out[ $cap ] = array_values( array_intersect( $roles, $valid ) );
		}
		return $out;
	}
}
