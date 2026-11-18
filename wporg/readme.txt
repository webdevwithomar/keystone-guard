=== Keystone Guard ===
Contributors: omar967
Tags: security, login, hardening, malware, htaccess
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Login limits, username privacy, basic hardening and daily checks for common backdoor signs, with a simple status page.

== Description ==

Keystone Guard is a small security plugin with no account, no paid tier and no settings maze. Everything is on one page at **Tools > Keystone Guard**.

**On when the plugin is active**

* Limits failed logins. Five failures in 15 minutes, or a failed login with a common decoy name such as `admin`, locks that visitor out for 30 minutes.
* Steps aside for login limits if Limit Login Attempts Reloaded or Wordfence is active.
* Shows one general message for failed logins, so attackers cannot tell which usernames exist.
* Hides user lists from logged-out visitors in the REST API, removes the users sitemap and redirects author scans.
* Turns off XML-RPC and the theme and plugin file editor, and removes the WordPress version tag.
* Sends basic security headers.

**Daily checks**

* New administrator accounts, including accounts hidden from the normal user list.
* New or changed must-use plugins.
* New PHP files directly inside `wp-content`.
* PHP code inside the uploads folder.
* Changed or extra WordPress core files.

Warnings appear on the status page. Email alerts are sent to the site admin address only if you turn them on.

**Optional, only when you choose them**

* Server rules for Apache and LiteSpeed, added to `.htaccess`. They block XML-RPC, author scans, sensitive files, WordPress internals and PHP in uploads.
* Closing comments and pings by default on new posts.

Keystone Guard lowers common risks. It does not remove malware and cannot promise that a site will never be hacked. Keep backups and keep WordPress, themes and plugins up to date.

== Installation ==

1. Install the plugin from **Plugins > Add New** and activate it.
2. Open **Tools > Keystone Guard**.
3. Choose whether you want email alerts.
4. Back up your site, then turn on server rules if your site runs on Apache or LiteSpeed.

== Frequently Asked Questions ==

= Will it change my .htaccess file when I activate it? =

No. It changes `.htaccess` only after you press **Turn server rules on**. If your homepage then returns a server error, the plugin restores the earlier file. Turning the rules off or deactivating the plugin removes them.

= Does it work on Nginx? =

Yes, except for the server rules, which need Apache or LiteSpeed.

= Does it work with a hidden login address plugin? =

Yes. It works alongside WPS Hide Login and shows on the status page whether that plugin is active.

= Will XML-RPC being off affect anything? =

Services that sign in through XML-RPC will stop working. The WordPress mobile app and Jetpack features that depend on it may be affected.

= The status page shows a warning. Am I hacked? =

Not always. A warning means something needs a look. If you recognize the item, press **Mark current accounts and files as safe**. That button covers administrators, must-use plugins and files directly in `wp-content`.

= Does it send my data anywhere? =

The plugin has no service of its own and sends no site data to its author. See External services below.

== External services ==

The daily check uses the WordPress function `get_core_checksums()`, which requests the official list of core file checksums from WordPress.org (`api.wordpress.org`). The request includes your WordPress version and locale. It is used to detect changed core files, and it runs once a day and when you press **Run check now**.

WordPress.org [Terms of Service](https://wordpress.org/about/terms-of-service/) and [Privacy Policy](https://wordpress.org/about/privacy/).

== Changelog ==

= 1.0.0 =
* First public release.

== Upgrade Notice ==

= 1.0.0 =
First public release.
