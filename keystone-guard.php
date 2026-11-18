<?php
/**
 * Plugin Name:       Keystone Guard
 * Description:       Server rules, login protection, hardening and daily backdoor checks.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Omar Faruque
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/webdevwithomar/keystone-guard
 * Text Domain:       keystone-guard
 */

defined( 'ABSPATH' ) || exit;

define( 'KG_VERSION', '1.0.0' );
define( 'KG_FILE', __FILE__ );
define( 'KG_BASENAME', plugin_basename( __FILE__ ) );
define( 'KG_REPO', 'webdevwithomar/keystone-guard' );

require_once __DIR__ . '/includes/htaccess.php';
require_once __DIR__ . '/includes/login.php';
require_once __DIR__ . '/includes/hardening.php';
require_once __DIR__ . '/includes/monitor.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/updater.php';

register_activation_hook( __FILE__, 'kg_activate' );
register_deactivation_hook( __FILE__, 'kg_deactivate' );

function kg_activate() {
	kg_htaccess_sync();
	kg_apply_site_defaults();
	kg_monitor_set_baseline();
	kg_monitor_run();
	if ( ! wp_next_scheduled( 'kg_daily_check' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'kg_daily_check' );
	}
	update_option( 'kg_version', KG_VERSION, false );
}

function kg_deactivate() {
	kg_htaccess_remove();
	wp_clear_scheduled_hook( 'kg_daily_check' );
}

// Auto-updates do not fire the activation hook, so new rules are applied here.
add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'kg_version' ) !== KG_VERSION ) {
			kg_htaccess_sync();
			update_option( 'kg_version', KG_VERSION, false );
		}
	}
);
