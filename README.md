# Keystone Guard

WordPress security for sites on OpenLiteSpeed: server rules, login protection, hardening, daily backdoor checks and a status page at **Tools > Keystone Guard**.

## Install by hand

1. Sign in to GitHub and open the [latest release](https://github.com/webdevwithomar/keystone-guard/releases/latest).
2. Download `keystone-guard.zip`. Do not use GitHub's "Source code" download.
3. In WordPress, go to **Plugins > Add New > Upload Plugin**, choose the zip and activate it.

## Release an update

1. Change the version in both places in `keystone-guard.php`.
2. Commit the change.
3. Run `bin/release.sh 1.0.1`.

This repository is private, so sites cannot download updates from it on their own. New versions must be copied to the sites after each release. If the repository becomes public, the built-in updater works without changes.

Deactivating the plugin removes its `.htaccess` rules.
