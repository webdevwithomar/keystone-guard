<?php
/**
 * WordPress hardening and one-time site defaults.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
add_filter( 'xmlrpc_enabled', '__return_false' );

add_filter(
	'wp_headers',
	function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

add_action(
	'send_headers',
	function () {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	}
);

/**
 * Runs once per site, so owners can turn comments back on later.
 */
function kg_apply_site_defaults() {
	global $wpdb;

	if ( get_option( 'kg_defaults_applied' ) ) {
		return;
	}

	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );
	update_option( 'default_pingback_flag', '0' );

	$post = get_page_by_path( 'hello-world', OBJECT, 'post' );
	if ( $post ) {
		// A direct update keeps the post's modified date unchanged.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->posts,
			array(
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			array( 'ID' => $post->ID )
		);
		clean_post_cache( $post->ID );
	}

	update_option( 'kg_defaults_applied', time(), false );
}
