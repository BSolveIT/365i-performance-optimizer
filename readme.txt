=== 365i Performance Optimizer ===
Contributors: bsolveit
Tags: performance, preload, defer, elementor, optimization
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Elementor-safe performance tweaks for WordPress 6.9: speculation rules, preconnect/preload, defer scripts, emoji cleanup, and smarter images.

== Description ==

This plugin replaces a common set of manual functions.php tweaks with a friendly Settings page under **Settings -> Performance Optimizer**.

Features:
* Speculative loading rules with configurable eagerness (front end only).
* Preconnect and preload hints for styles, fonts, and a hero image.
* One-click auto-detect to prefill preload URLs from your homepage (no external proxy).
* Removes emoji scripts/styles and the embeds script (front end only).
* Defers eligible scripts while skipping Elementor, jQuery, and core handles.
* Optional REST/oEmbed link removal and XML-RPC disable.
* Smarter image handling: high fetch priority for LCP candidates and optional lazy-load skip on the homepage.
* Automatically detects Elementor edit/preview mode and backs off to avoid conflicts.

Options you can control:
* Speculative loading on/off and eagerness level.
* Preconnect host list, stylesheet preload URL, font preload URL, hero image preload URL, and whether hero preload is front-page only.
* Toggle emoji removal, embeds script removal, XML-RPC disable, REST link removal, and oEmbed link removal.
* Defer scripts toggle with custom exclusion handles.
* Image tweaks: set fetchpriority=high when missing, and disable lazy-load on the homepage.

Built for WordPress 6.9 using the Settings API, proper escaping/sanitization, and uninstall cleanup to meet WordPress.org guidelines.
Settings are registered with a sanitize callback and defaults to ensure inputs are cleaned on save.

== Installation ==

1. Upload the `365i-performance-optimizer` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" screen.
3. Visit **Settings -> Performance Optimizer** to toggle or customize the optimizations.

== Frequently Asked Questions ==

= Will this affect Elementor editing? =
No. The plugin detects Elementor edit/preview contexts and keeps all optimizations disabled there.

= Can I turn off individual tweaks? =
Yes. Every optimization has its own checkbox, plus text fields for preload URLs and exclusions.

= Does this plugin send any data off-site? =
No. It does not collect or transmit any personal data or usage data.

= How does auto-detect work? =
When you click Auto-detect, the plugin fetches your own homepage (via `wp_remote_get`), inspects `<link>` and `<img>` tags, and suggests safe preload targets. It never calls third-party services or proxies.

== Screenshots ==

1. Settings page showing performance options, tooltips, and toggles.

== Changelog ==

= 1.1.0 =
* Redesigned admin settings page with modern corporate light theme.
* New card-based layout with color-coded sections.
* Improved toggle switches using W3Schools best practices.
* Better tooltip positioning and visibility.
* Inter font for improved readability.
* Enhanced responsive design for mobile devices.

= 1.0.0 =
* Initial release.
