<?php
/**
 * Login limits, decoy usernames and username privacy.
 */

defined( 'ABSPATH' ) || exit;

const KG_MAX_FAILURES   = 5;
const KG_FAILURE_WINDOW = 15 * MINUTE_IN_SECONDS;
const KG_LOCK_TIME      = 30 * MINUTE_IN_SECONDS;
const KG_DECOY_NAMES    = array( 'admin', 'administrator', 'root', 'test', 'wpadmin', 'wp-admin' );

function kg_login_limits_enabled() {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	return ! is_plugin_active( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' )
		&& ! is_plugin_active( 'wordfence/wordfence.php' );
}

function kg_client_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	return md5( $ip );
}

function kg_lock_client() {
	set_transient( 'kg_lock_' . kg_client_key(), 1, KG_LOCK_TIME );
	delete_transient( 'kg_fail_' . kg_client_key() );
	update_option( 'kg_lockouts', (int) get_option( 'kg_lockouts', 0 ) + 1, false );
}

function kg_is_decoy_login( $username ) {
	if ( ! in_array( strtolower( trim( $username ) ), KG_DECOY_NAMES, true ) ) {
		return false;
	}
	$user = get_user_by( 'login', $username );
	return ! $user || ! user_can( $user, 'edit_posts' );
}

add_filter(
	'authenticate',
	function ( $user ) {
		if ( kg_login_limits_enabled() && get_transient( 'kg_lock_' . kg_client_key() ) ) {
			return new WP_Error( 'kg_locked', __( 'Too many failed logins. Please try again later.', 'keystone-guard' ) );
		}
		return $user;
	},
	1
);

add_action(
	'wp_login_failed',
	function ( $username ) {
		if ( ! kg_login_limits_enabled() ) {
			return;
		}
		if ( kg_is_decoy_login( $username ) ) {
			kg_lock_client();
			return;
		}
		$key      = 'kg_fail_' . kg_client_key();
		$failures = (int) get_transient( $key ) + 1;
		if ( $failures >= KG_MAX_FAILURES ) {
			kg_lock_client();
		} else {
			set_transient( $key, $failures, KG_FAILURE_WINDOW );
		}
	}
);

add_filter(
	'login_errors',
	function ( $message ) {
		global $errors;
		if ( $errors instanceof WP_Error && array_intersect( $errors->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
			return __( 'The login details are incorrect.', 'keystone-guard' );
		}
		return $message;
	}
);

// Runs after REST authentication, so the block editor keeps its author lists.
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( null === $result && ! is_user_logged_in() && 0 === strpos( $request->get_route(), '/wp/v2/users' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'keystone-guard' ), array( 'status' => 401 ) );
		}
		return $result;
	},
	10,
	3
);

add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	10,
	2
);

add_filter(
	'oembed_response_data',
	function ( $data ) {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}
);

add_action(
	'template_redirect',
	function () {
		if ( isset( $_GET['author'] ) && ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);
