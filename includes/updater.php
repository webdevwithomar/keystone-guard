<?php
/**
 * Updates from GitHub releases. The package must match the published SHA-256.
 */

defined( 'ABSPATH' ) || exit;

function kg_update_manifest() {
	$manifest = get_site_transient( 'kg_update_manifest' );
	if ( is_array( $manifest ) ) {
		return $manifest;
	}

	$manifest = array();
	$response = wp_remote_get( 'https://github.com/' . KG_REPO . '/releases/latest/download/update.json', array( 'timeout' => 10 ) );
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $decoded ) && isset( $decoded['version'], $decoded['package'], $decoded['sha256'] )
			&& 0 === strpos( $decoded['package'], 'https://github.com/' . KG_REPO . '/releases/download/' ) ) {
			$manifest = $decoded;
		}
	}
	set_site_transient( 'kg_update_manifest', $manifest, 6 * HOUR_IN_SECONDS );
	return $manifest;
}

add_filter(
	'update_plugins_github.com',
	function ( $update, $plugin_data, $plugin_file ) {
		if ( KG_BASENAME !== $plugin_file ) {
			return $update;
		}
		$manifest = kg_update_manifest();
		if ( ! $manifest ) {
			return $update;
		}
		return array(
			'slug'    => 'keystone-guard',
			'version' => $manifest['version'],
			'url'     => 'https://github.com/' . KG_REPO,
			'package' => $manifest['package'],
		);
	},
	10,
	3
);

add_filter(
	'auto_update_plugin',
	function ( $update, $item ) {
		return isset( $item->plugin ) && KG_BASENAME === $item->plugin ? true : $update;
	},
	10,
	2
);

add_filter(
	'upgrader_pre_download',
	function ( $reply, $package ) {
		$manifest = kg_update_manifest();
		if ( false !== $reply || ! $manifest || $package !== $manifest['package'] ) {
			return $reply;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = download_url( $package, 60 );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! hash_equals( strtolower( $manifest['sha256'] ), hash_file( 'sha256', $file ) ) ) {
			wp_delete_file( $file );
			return new WP_Error( 'kg_bad_package', __( 'The update package failed its security check.', 'keystone-guard' ) );
		}
		return $file;
	},
	10,
	2
);
