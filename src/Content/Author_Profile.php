<?php
/**
 * Author E-E-A-T fields (profile screen) and the optional author box under posts.
 *
 * @package AEO
 */

namespace AEO\Content;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Author profile.
 */
class Author_Profile {

	const FIELDS = array(
		'aeo_job_title'   => 'Job title',
		'aeo_credentials' => 'Credentials (e.g. "MBA", "Certified Travel Agent")',
		'aeo_works_for'   => 'Works for (leave empty for this site\'s organization)',
		'aeo_knows_about' => 'Expertise (comma separated)',
		'aeo_same_as'     => 'Profiles elsewhere (LinkedIn, X, etc.; one URL per line)',
	);

	/**
	 * Register.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'show_user_profile', array( __CLASS__, 'fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save' ) );
		add_filter( 'the_content', array( __CLASS__, 'author_box' ), 30 );
	}

	/**
	 * Profile fields.
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	public static function fields( $user ) {
		wp_nonce_field( 'aeo_author_profile', 'aeo_author_nonce' );
		echo '<h2>' . esc_html__( 'Author details for AI search (E-E-A-T)', 'answer-engine-optimization' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Shown in the author box and in Person structured data so AI assistants can see who wrote the content and why they are qualified.', 'answer-engine-optimization' ) . '</p>';
		echo '<table class="form-table" role="presentation">';
		foreach ( self::FIELDS as $key => $label ) {
			$value = (string) get_user_meta( $user->ID, $key, true );
			echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( 'aeo_same_as' === $key ) {
				echo '<textarea name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" rows="3" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
			} else {
				echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * Save.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['aeo_author_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aeo_author_nonce'] ) ), 'aeo_author_profile' ) ) {
			return;
		}
		foreach ( array_keys( self::FIELDS ) as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw   = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$value = 'aeo_same_as' === $key
				? implode( "\n", array_filter( array_map( 'esc_url_raw', array_map( 'trim', preg_split( '/[\r\n]+/', (string) $raw ) ) ) ) )
				: sanitize_text_field( (string) $raw );
			update_user_meta( $user_id, $key, $value );
		}
	}

	/**
	 * Author box after single posts.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function author_box( $content ) {
		if ( ! Settings::get( 'author_box', 0 ) || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$user_id = (int) get_the_author_meta( 'ID' );
		if ( ! $user_id ) {
			return $content;
		}
		$name  = get_the_author_meta( 'display_name', $user_id );
		$bio   = get_the_author_meta( 'description', $user_id );
		$job   = (string) get_user_meta( $user_id, 'aeo_job_title', true );
		$creds = (string) get_user_meta( $user_id, 'aeo_credentials', true );
		$links = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', (string) get_user_meta( $user_id, 'aeo_same_as', true ) ) ) );

		$html  = '<aside class="aeo-author-box" aria-label="' . esc_attr__( 'About the author', 'answer-engine-optimization' ) . '">';
		$html .= get_avatar( $user_id, 64, '', $name );
		$html .= '<div><p><strong><a href="' . esc_url( get_author_posts_url( $user_id ) ) . '" rel="author">' . esc_html( $name ) . '</a></strong>';
		if ( $creds ) {
			$html .= ', ' . esc_html( $creds );
		}
		if ( $job ) {
			$html .= '<br><span>' . esc_html( $job ) . '</span>';
		}
		$html .= '</p>';
		if ( $bio ) {
			$html .= '<p>' . esc_html( $bio ) . '</p>';
		}
		if ( $links ) {
			$html .= '<p class="aeo-author-links">';
			foreach ( $links as $url ) {
				$host  = preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
				$html .= '<a href="' . esc_url( $url ) . '" rel="me noopener" target="_blank">' . esc_html( $host ) . '</a>';
			}
			$html .= '</p>';
		}
		$html .= '</div></aside>';
		return $content . $html;
	}
}
