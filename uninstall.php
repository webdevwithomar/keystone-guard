<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$options = array( 'kg_version', 'kg_htaccess_status', 'kg_htaccess_backup', 'kg_defaults_applied', 'kg_lockouts', 'kg_baseline', 'kg_issues', 'kg_core_checked', 'kg_last_check', 'kg_alerts', 'kg_alert_sent', 'kg_rules_enabled' );
foreach ( $options as $option ) {
	delete_option( $option );
}
delete_site_transient( 'kg_update_manifest' );
