<?php
/**
 * Status page and the email alert choice.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Keystone Guard', 'Keystone Guard', 'manage_options', 'keystone-guard', 'kg_render_page' );
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) || false !== get_option( 'kg_alerts', false ) ) {
			return;
		}
		$email = get_option( 'admin_email' );
		echo '<div class="notice notice-info"><p>';
		printf(
			/* translators: %s: site admin email address */
			esc_html__( 'Keystone Guard: email security alerts to %s? If not, warnings stay on the status page.', 'keystone-guard' ),
			'<strong>' . esc_html( $email ) . '</strong>'
		);
		echo '</p><p>';
		kg_action_button( 'alerts_on', __( 'Yes, email me', 'keystone-guard' ), 'button-primary' );
		echo ' ';
		kg_action_button( 'alerts_off', __( 'No, status page only', 'keystone-guard' ) );
		echo '</p></div>';
	}
);

function kg_action_button( $action, $label, $class = 'button' ) {
	printf(
		'<form method="post" action="%s" style="display:inline"><input type="hidden" name="action" value="kg_action"><input type="hidden" name="kg_do" value="%s">%s<button class="button %s">%s</button></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		esc_attr( $action ),
		wp_nonce_field( 'kg_action', '_wpnonce', true, false ),
		esc_attr( $class ),
		esc_html( $label )
	);
}

add_action(
	'admin_post_kg_action',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'keystone-guard' ), 403 );
		}
		check_admin_referer( 'kg_action' );

		$action = isset( $_POST['kg_do'] ) ? sanitize_key( wp_unslash( $_POST['kg_do'] ) ) : '';
		switch ( $action ) {
			case 'alerts_on':
				update_option( 'kg_alerts', 'yes', false );
				break;
			case 'alerts_off':
				update_option( 'kg_alerts', 'no', false );
				break;
			case 'run_check':
				kg_monitor_run();
				break;
			case 'accept':
				kg_monitor_set_baseline();
				kg_monitor_run();
				break;
			case 'sync_rules':
				kg_htaccess_sync();
				break;
		}
		wp_safe_redirect( admin_url( 'tools.php?page=keystone-guard&updated=1' ) );
		exit;
	}
);

function kg_status_rows() {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	$limits = kg_login_limits_enabled();
	return array(
		array( __( 'Server rules', 'keystone-guard' ), kg_htaccess_is_active(), __( 'XML-RPC, sensitive files, author scans and PHP in uploads are blocked.', 'keystone-guard' ) ),
		array( __( 'Login limits', 'keystone-guard' ), true, $limits ? __( 'Five failures or a decoy username locks the visitor for 30 minutes.', 'keystone-guard' ) : __( 'Handled by your existing login security plugin.', 'keystone-guard' ) ),
		array( __( 'Username privacy', 'keystone-guard' ), true, __( 'Public user lists and author scans are blocked.', 'keystone-guard' ) ),
		array( __( 'Hidden login address', 'keystone-guard' ), is_plugin_active( 'wps-hide-login/wps-hide-login.php' ), __( 'Provided by WPS Hide Login.', 'keystone-guard' ) ),
		array( __( 'File editor', 'keystone-guard' ), defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT, __( 'Theme and plugin editing in wp-admin is off.', 'keystone-guard' ) ),
		array( __( 'Email alerts', 'keystone-guard' ), 'yes' === get_option( 'kg_alerts' ), get_option( 'admin_email' ) ),
	);
}

function kg_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$issues = (array) get_option( 'kg_issues', array() );
	$last   = (int) get_option( 'kg_last_check', 0 );
	?>
	<div class="wrap">
		<h1>Keystone Guard <small><?php echo esc_html( KG_VERSION ); ?></small></h1>

		<h2><?php esc_html_e( 'Protection', 'keystone-guard' ); ?></h2>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
			<?php foreach ( kg_status_rows() as $row ) : ?>
				<tr>
					<td style="width:190px"><strong><?php echo esc_html( $row[0] ); ?></strong></td>
					<td style="width:70px"><?php echo $row[1] ? esc_html__( 'On', 'keystone-guard' ) : esc_html__( 'Off', 'keystone-guard' ); ?></td>
					<td><?php echo esc_html( $row[2] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Security check', 'keystone-guard' ); ?></h2>
		<p>
			<?php
			echo $last
				? esc_html( sprintf( /* translators: %s: time since last check */ __( 'Last checked %s ago.', 'keystone-guard' ), human_time_diff( $last ) ) )
				: esc_html__( 'Not checked yet.', 'keystone-guard' );
			if ( ! get_option( 'kg_core_checked' ) ) {
				echo ' ' . esc_html__( 'WordPress core could not be compared in the last check.', 'keystone-guard' );
			}
			?>
		</p>

		<?php if ( ! $issues ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'No warnings.', 'keystone-guard' ); ?></p></div>
		<?php endif; ?>

		<?php foreach ( $issues as $issue ) : ?>
			<div class="notice notice-error inline">
				<p><strong><?php echo esc_html( $issue['title'] ); ?></strong></p>
				<ul style="list-style:disc;margin-left:20px">
					<?php foreach ( $issue['items'] as $item ) : ?>
						<li><code><?php echo esc_html( $item ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>

		<p>
			<?php
			kg_action_button( 'run_check', __( 'Run check now', 'keystone-guard' ), 'button-primary' );
			echo ' ';
			kg_action_button( 'accept', __( 'Mark current accounts and files as safe', 'keystone-guard' ) );
			echo ' ';
			kg_action_button( 'sync_rules', __( 'Rewrite server rules', 'keystone-guard' ) );
			echo ' ';
			kg_action_button( 'yes' === get_option( 'kg_alerts' ) ? 'alerts_off' : 'alerts_on', 'yes' === get_option( 'kg_alerts' ) ? __( 'Turn email alerts off', 'keystone-guard' ) : __( 'Turn email alerts on', 'keystone-guard' ) );
			?>
		</p>
	</div>
	<?php
}
