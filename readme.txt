=== GPLlib Connector ===
Contributors: gpllib
Tags: updates, auto-update, gpllib
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bind this site to your GPLlib account and enable automatic updates for themes/plugins you're entitled to that GPLlib marks as auto-update-enabled.

== Description ==

GPLlib Connector lets you automatically update themes/plugins obtained from GPLlib on your own WordPress site —
provided you're entitled to the resource (purchased individually, or an active member), and GPLlib marks that resource as auto-update-enabled.

How it works:
1. Copy the activation code from the "Auto Update" page in your GPLlib account center.
2. Paste the activation code on this plugin's settings page and activate (automatically binds this site's domain).
3. When a managed resource has a new version, update it from the WordPress "Updates" screen as usual.

This plugin is open source (GPL-2.0), but requires a valid GPLlib activation code and server-side verification to function.

== Privacy ==

The plugin only reports to GPLlib: this site's domain, and the slugs and versions of installed plugins/themes, for version comparison and entitlement checks.
No other site data is reported. The site token is stored server-side only and is never sent to the browser.

== Installation ==

1. Upload to `/wp-content/plugins/gpllib-connector` (conventional path).
2. Activate the plugin, then go to the "GPLlib Auto Update" menu to activate your license.

== Changelog ==

= 1.2.0 =
* Settings page redesign: split into "Overview", "Update Overview", and "Settings" tabs; Overview shows binding status on the left and a getting-started guide on the right, and jumps to "Update Overview" automatically after checking for updates.
* Custom API base URL moved to the "Settings" tab, no longer hidden in a collapsed panel under the activation card.
* "Update Overview" redesign: clearer counts at the top, paginated list of pending updates, long changelogs are now collapsible/expandable.
* Fixed input fields and buttons on the settings page being misaligned due to WordPress admin default styles.
* Fixed "check complete" and similar notices being hidden behind the admin top bar.

= 1.1.0 =
* Security: update packages are now verified (SHA-256) after download; if verification fails, the temp file is deleted immediately and the update is aborted, preventing installation of a corrupted or tampered package.
* "Update Overview" now lists pending updates in detail: resource name, target version, and that version's changelog; visible as soon as the page opens, from the last check.
* More resilient over slow network paths: read timeouts are tiered per endpoint, and idempotent requests ("check updates", "binding status") are retried once automatically on failure (activation, unbind, and fetching the download URL are not retried).
* When rate-limited, the notice shows an approximate wait time if the server provides one.
* Distinguishes "server error" from "incorrect API base URL" when the server response is malformed.
* Full bilingual support: the settings page, notices, and error messages follow the WordPress admin language (set under "Users → Profile"). Simplified Chinese and English translations are bundled.
* Server-returned error messages are now localized to the site's language, with actionable guidance per case (e.g. quota, expired membership, site limit reached).
* Fixed: quota exhausted, rate limited, and expired membership were previously misreported as "token invalid, please reactivate", which led users to the wrong action and wasted a site binding slot. Reactivation is now only prompted when the credential is actually invalid.
* "Check for updates now" failures now show the specific reason instead of always showing "check complete".

= 1.0.5 =
* Deactivating the plugin no longer unbinds this site; reactivating restores it. Explicit unbinding is still done via the "Unbind this site" button on the settings page.
* Deleting the plugin now automatically unbinds this site (freeing the binding slot) and clears all data written by the plugin, including the site token.
* Fixed the "view details" link in the update list pointing to an invalid address.

= 1.0.4 =
* Fixed an inflated "managed resources" count on the overview page; now only counts resources from the GPLlib library.

= 1.0.3 =
* Fixed activation failing in local/self-signed certificate environments (cURL error 60).

= 1.0.2 =
* Fixed admin settings page styles not loading; added a getting-started guide to the settings page.

= 1.0.1 =
* Security: force the API base URL to https, preventing the site token from being sent over a plaintext channel.

= 1.0.0 =
* Initial release.
