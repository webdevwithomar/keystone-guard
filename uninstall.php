<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$kg_options = array( 'kg_version', 'kg_htaccess_status', 'kg_htaccess_backup', 'kg_defaults_applied', 'kg_lockouts', 'kg_baseline', 'kg_issues', 'kg_core_checked', 'kg_last_check', 'kg_alerts', 'kg_alert_sent', 'kg_rules_enabled' );
foreach ( $kg_options as $kg_option ) {
	delete_option( $kg_option );
}
delete_site_transient( 'kg_update_manifest' );
