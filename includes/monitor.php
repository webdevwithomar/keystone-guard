<?php
/**
 * Daily checks for the signs found in real compromises: hidden admins,
 * must-use backdoors, PHP in uploads and altered core files.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'kg_daily_check', 'kg_monitor_run' );

// Some servers turn off WP-Cron, so an admin visit also starts an overdue check.
add_action(
	'admin_init',
	function () {
		if ( ! wp_doing_ajax() && current_user_can( 'manage_options' ) && time() - (int) get_option( 'kg_last_check', 0 ) > 2 * DAY_IN_SECONDS ) {
			kg_monitor_run();
		}
	}
);

// Reads the table directly because backdoors hide users from WP_User_Query.
function kg_admin_accounts() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT u.ID, u.user_login FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID
			WHERE m.meta_key = %s AND m.meta_value LIKE %s",
			$wpdb->prefix . 'capabilities',
			'%"administrator"%'
		)
	);
	$admins = array();
	foreach ( $rows as $row ) {
		$admins[ (int) $row->ID ] = $row->user_login;
	}
	return $admins;
}

function kg_hash_php_files( $dir ) {
	$hashes = array();
	foreach ( glob( trailingslashit( $dir ) . '*.php' ) ?: array() as $file ) {
		$hashes[ basename( $file ) ] = md5_file( $file );
	}
	return $hashes;
}

function kg_monitor_set_baseline() {
	update_option(
		'kg_baseline',
		array(
			'admins' => kg_admin_accounts(),
			'mu'     => kg_hash_php_files( WPMU_PLUGIN_DIR ),
			'root'   => array_keys( kg_hash_php_files( WP_CONTENT_DIR ) ),
		),
		false
	);
}

function kg_scan_uploads() {
	$found   = array();
	$uploads = wp_get_upload_dir();
	$base    = $uploads['basedir'];
	if ( ! is_dir( $base ) ) {
		return $found;
	}

	$started = microtime( true );
	$recent  = time() - 8 * DAY_IN_SECONDS;
	$files   = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $files as $file ) {
		if ( microtime( true ) - $started > 20 || count( $found ) >= 25 ) {
			break;
		}
		if ( ! $file->isFile() ) {
			continue;
		}
		$name     = $file->getFilename();
		$relative = ltrim( str_replace( $base, '', $file->getPathname() ), '/\\' );
		if ( preg_match( '/\.(?:php[0-9]?|pht|phtml?|phps|phar)(?:\.|$)/i', $name ) ) {
			if ( 'index.php' !== $name || $file->getSize() > 100 ) {
				$found[] = $relative;
			}
		} elseif ( $file->getMTime() > $recent && $file->getSize() < 2 * MB_IN_BYTES ) {
			$head = (string) file_get_contents( $file->getPathname(), false, null, 0, 8192 );
			if ( preg_match( '/<\?php\s/i', $head ) ) {
				$found[] = $relative;
			}
		}
	}
	return $found;
}

function kg_scan_core() {
	global $wp_version;
	require_once ABSPATH . 'wp-admin/includes/update.php';

	$checksums = get_core_checksums( $wp_version, get_locale() );
	if ( ! $checksums ) {
		$checksums = get_core_checksums( $wp_version, 'en_US' );
	}
	if ( ! $checksums ) {
		return null;
	}

	$changed = array();
	foreach ( $checksums as $file => $hash ) {
		// Some sites blank or rename these on purpose, so only altered code counts.
		if ( 0 === strpos( $file, 'wp-content/' ) || in_array( $file, array( 'xmlrpc.php', 'readme.html', 'license.txt', 'wp-config-sample.php' ), true ) ) {
			continue;
		}
		$path = ABSPATH . $file;
		if ( file_exists( $path ) && md5_file( $path ) !== $hash ) {
			$changed[] = $file;
		}
	}

	foreach ( array( 'wp-admin', WPINC ) as $folder ) {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( ABSPATH . $folder, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $files as $file ) {
			if ( 'php' === strtolower( $file->getExtension() ) ) {
				$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( ABSPATH ) ) );
				if ( ! isset( $checksums[ $relative ] ) ) {
					$changed[] = $relative;
				}
			}
		}
	}
	return $changed;
}

function kg_issue_list( $title, $items ) {
	return array(
		'title' => $title,
		'items' => array_values( array_slice( $items, 0, 25 ) ),
	);
}

function kg_monitor_run() {
	$baseline = (array) get_option( 'kg_baseline', array() );
	if ( ! $baseline ) {
		kg_monitor_set_baseline();
		$baseline = (array) get_option( 'kg_baseline', array() );
	}
	$issues = array();

	$new_admins = array_diff_key( kg_admin_accounts(), (array) $baseline['admins'] );
	if ( $new_admins ) {
		$issues[] = kg_issue_list( __( 'New administrator accounts', 'keystone-guard' ), $new_admins );
	}

	$mu_now  = kg_hash_php_files( WPMU_PLUGIN_DIR );
	$mu_diff = array_keys( array_diff_assoc( $mu_now, (array) $baseline['mu'] ) );
	if ( $mu_diff ) {
		$issues[] = kg_issue_list( __( 'New or changed must-use plugins', 'keystone-guard' ), $mu_diff );
	}

	$root_diff = array_diff( array_keys( kg_hash_php_files( WP_CONTENT_DIR ) ), (array) $baseline['root'] );
	if ( $root_diff ) {
		$issues[] = kg_issue_list( __( 'New PHP files directly in wp-content', 'keystone-guard' ), $root_diff );
	}

	$uploads = kg_scan_uploads();
	if ( $uploads ) {
		$issues[] = kg_issue_list( __( 'PHP code in uploads', 'keystone-guard' ), $uploads );
	}

	$core = kg_scan_core();
	if ( $core ) {
		$issues[] = kg_issue_list( __( 'Changed or extra WordPress core files', 'keystone-guard' ), $core );
	}

	update_option( 'kg_issues', $issues, false );
	update_option( 'kg_core_checked', null !== $core, false );
	update_option( 'kg_last_check', time(), false );
	kg_maybe_send_alert( $issues );
	return $issues;
}

function kg_maybe_send_alert( $issues ) {
	$fingerprint = md5( wp_json_encode( $issues ) );
	if ( ! $issues ) {
		delete_option( 'kg_alert_sent' );
		return;
	}
	if ( 'yes' !== get_option( 'kg_alerts' ) || get_option( 'kg_alert_sent' ) === $fingerprint ) {
		return;
	}

	$lines = array( sprintf( 'Keystone Guard found items to review on %s:', home_url( '/' ) ), '' );
	foreach ( $issues as $issue ) {
		$lines[] = $issue['title'] . ':';
		foreach ( $issue['items'] as $item ) {
			$lines[] = '  - ' . $item;
		}
		$lines[] = '';
	}
	$lines[] = 'Review them at ' . admin_url( 'tools.php?page=keystone-guard' );

	$subject = sprintf( '[%s] Security check needs review', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	if ( wp_mail( get_option( 'admin_email' ), $subject, implode( "\n", $lines ) ) ) {
		update_option( 'kg_alert_sent', $fingerprint, false );
	}
}
