<?php
/**
 * Writes the server rules. OpenLiteSpeed ignores FilesMatch and Require, and a
 * lower .htaccess with rewrite rules replaces the root rules for its folder.
 */

defined( 'ABSPATH' ) || exit;

const KG_PHP_EXTENSIONS = '(?:php[0-9]?|pht|phtml?|phps|phar)';

// Managed sites get everything on activation. The directory build asks first.
function kg_is_managed() {
	return defined( 'KG_MANAGED' ) && KG_MANAGED;
}

function kg_rules_allowed() {
	return kg_is_managed() || 'yes' === get_option( 'kg_rules_enabled' );
}

function kg_root_rules() {
	$php = KG_PHP_EXTENSIONS;
	return <<<HTACCESS
# ==============================================================================
# BEGIN CUSTOM WORDPRESS HARDENING
# Managed by Keystone Guard. Edits inside this block are replaced on update.
# ==============================================================================
Options -Indexes

<IfModule mod_rewrite.c>
    RewriteEngine On

    # XML-RPC
    RewriteRule ^xmlrpc\.php$ - [F,L]

    # Author scans on the public site only, so wp-admin filters and the REST API work
    RewriteCond %{REQUEST_URI} !^/(?:wp-admin|wp-json)(?:/|$) [NC]
    RewriteCond %{QUERY_STRING} !(?:^|&)rest_route= [NC]
    RewriteCond %{QUERY_STRING} (?:^|&)author=\d+(?:&|$) [NC]
    RewriteRule .* - [F,L]

    # Sensitive files
    RewriteRule ^(?:readme\.html|license\.txt|wp-config\.php|wp-config-sample\.php)$ - [NC,F,L]
    RewriteRule (?:^|/)\.(?:git|svn|env|htaccess|htpasswd|DS_Store)(?:/|$) - [NC,F,L]
    RewriteRule \.(?:sql|sql\.gz|bak|old|orig|save|swp)$ - [NC,F,L]
    RewriteRule ^wp-content/(?:debug\.log|mc_data(?:/|$)) - [NC,F,L]

    # WordPress internals
    RewriteRule ^wp-admin/install\.php$ - [F,L]
    RewriteRule ^wp-admin/includes/ - [F,L]
    RewriteRule ^wp-includes/[^/]+\.php$ - [F,L]
    RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]
    RewriteRule ^wp-includes/theme-compat/ - [F,L]

    # PHP in uploads
    RewriteRule ^wp-content/uploads/.*\.{$php}\.?$ - [NC,F,L]
</IfModule>
# ==============================================================================
# END CUSTOM WORDPRESS HARDENING
# ==============================================================================
HTACCESS;
}

function kg_nested_rules( $rules ) {
	return "# BEGIN CUSTOM WORDPRESS HARDENING\n<IfModule mod_rewrite.c>\nRewriteEngine On\n{$rules}\n</IfModule>\n# END CUSTOM WORDPRESS HARDENING";
}

/**
 * Nested files only get rules when they already use rewrites. Adding a rewrite
 * block elsewhere would switch off root rules such as ShortPixel's image rules.
 */
function kg_nested_targets() {
	$php = KG_PHP_EXTENSIONS;
	return array(
		WP_CONTENT_DIR . '/.htaccess'         => kg_nested_rules( "RewriteRule ^uploads/.*\\.{$php}\\.?$ - [NC,F,L]\nRewriteRule ^(?:debug\\.log|mc_data(?:/|$)) - [NC,F,L]" ),
		WP_CONTENT_DIR . '/uploads/.htaccess' => kg_nested_rules( "RewriteRule \\.{$php}\\.?$ - [NC,F,L]" ),
	);
}

function kg_strip_block( $contents ) {
	$pattern = '/(?:^#[ \t]*=+[ \t]*\R)?^#[ \t]*BEGIN (?:CUSTOM WORDPRESS|BRWEST) HARDENING.*?^#[ \t]*END (?:CUSTOM WORDPRESS|BRWEST) HARDENING[^\n]*\R?(?:^#[ \t]*=+[ \t]*\R?)?\s*/ms';
	return (string) preg_replace( $pattern, '', $contents );
}

function kg_uses_rewrites( $contents ) {
	return (bool) preg_match( '/^[ \t]*RewriteEngine[ \t]+on/mi', kg_strip_block( $contents ) );
}

function kg_write_file( $path, $contents ) {
	return false !== file_put_contents( $path, $contents, LOCK_EX );
}

function kg_htaccess_sync() {
	$root = ABSPATH . '.htaccess';
	if ( ! file_exists( $root ) || ! is_writable( $root ) ) {
		update_option( 'kg_htaccess_status', 'not-writable', false );
		return false;
	}

	$before = (string) file_get_contents( $root );
	$after  = kg_root_rules() . "\n\n" . ltrim( kg_strip_block( $before ) );
	if ( $after !== $before ) {
		update_option( 'kg_htaccess_backup', $before, false );
		kg_write_file( $root, $after );

		// A server error here means the rules do not suit this host.
		$check = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'   => 10,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
		if ( ! is_wp_error( $check ) && wp_remote_retrieve_response_code( $check ) >= 500 ) {
			kg_write_file( $root, $before );
			update_option( 'kg_htaccess_status', 'rolled-back', false );
			return false;
		}
	}

	foreach ( kg_nested_targets() as $path => $rules ) {
		if ( ! file_exists( $path ) || ! is_writable( $path ) ) {
			continue;
		}
		$current = (string) file_get_contents( $path );
		if ( kg_uses_rewrites( $current ) ) {
			$updated = $rules . "\n\n" . ltrim( kg_strip_block( $current ) );
			if ( $updated !== $current ) {
				kg_write_file( $path, $updated );
			}
		}
	}

	update_option( 'kg_htaccess_status', 'active', false );
	return true;
}

function kg_htaccess_remove() {
	$paths = array_merge( array( ABSPATH . '.htaccess' ), array_keys( kg_nested_targets() ) );
	foreach ( $paths as $path ) {
		if ( file_exists( $path ) && is_writable( $path ) ) {
			$current = (string) file_get_contents( $path );
			$cleaned = kg_strip_block( $current );
			if ( $cleaned !== $current ) {
				kg_write_file( $path, $cleaned );
			}
		}
	}
	delete_option( 'kg_htaccess_status' );
}

function kg_htaccess_is_active() {
	$root = ABSPATH . '.htaccess';
	return file_exists( $root ) && false !== strpos( (string) file_get_contents( $root ), 'Managed by Keystone Guard' );
}
