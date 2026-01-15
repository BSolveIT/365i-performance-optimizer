=== 365i Performance Optimizer ===
Contributors: bsolveit
Tags: performance, speed, cache, elementor, woocommerce
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Speed up WordPress with speculation rules, script deferral, local Google Fonts, database cleanup, and WooCommerce optimization. Elementor-safe.

== Description ==

**365i Performance Optimizer** is a comprehensive performance plugin that combines essential speed optimizations into one easy-to-use settings page. Perfect for developers and site owners who want granular control without editing functions.php.

= Why Choose This Plugin? =

* **Elementor-Safe** - Automatically detects edit/preview mode and disables optimizations to prevent conflicts.
* **No External Services** - All optimizations run locally. No data sent to third parties. GDPR-friendly.
* **Granular Control** - Toggle each feature independently. Use profiles for quick setup or fine-tune every setting.
* **WordPress.org Compliant** - Follows all coding standards, proper sanitization, and clean uninstall.

= Core Performance Features =

* **Speculative Loading** - Uses WordPress 6.9+ Speculation Rules API to prefetch likely next pages.
* **Preconnect & Preload** - Resource hints for critical CSS, fonts, and hero images with one-click auto-detection.
* **Script Deferral** - Defer render-blocking scripts while protecting jQuery, Elementor, and core handles.
* **Cleanup** - Remove emoji scripts, embeds, REST links, oEmbed discovery, and disable XML-RPC.
* **Image Optimization** - Set fetchpriority="high" on LCP candidates and control lazy-loading behavior.

= Advanced Features (New in 2.0) =

* **JavaScript Delay** - Don't load non-critical scripts until user interacts (scroll, click, touch, keypress). Dramatically improves initial page load.
* **Local Google Fonts** - Download and serve Google Fonts from your server. Eliminates external requests and ensures GDPR compliance.
* **Heartbeat Control** - Reduce or disable WordPress Heartbeat API to save server resources.
* **WooCommerce Optimization** - Conditionally load cart fragments, styles, and block styles only on shop pages.
* **Database Cleanup** - Remove post revisions, auto-drafts, spam comments, orphaned data, and expired transients. Manual or scheduled.
* **Query String Removal** - Strip ?ver= parameters from static assets for better CDN caching.

= Safety & Management =

* **Settings Backups** - Automatic snapshots before every save. Keep up to 5 backups with one-click restore.
* **Configuration Profiles** - Safe Mode, Balanced, and Aggressive presets. Save custom profiles for different scenarios.
* **Import/Export** - Transfer settings between sites as JSON. Perfect for agencies and multi-site setups.
* **Per-Page Overrides** - Disable specific optimizations on problem pages via a simple meta box.
* **Dashboard Widget** - Quick overview of active optimizations without leaving the dashboard.

= Perfect For =

* Elementor users wanting safe performance gains
* WooCommerce stores needing faster non-shop pages
* GDPR-conscious sites requiring local font hosting
* Developers managing multiple WordPress sites
* Anyone wanting a comprehensive, well-documented performance solution

== Installation ==

1. Upload the `365i-performance-optimizer` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings -> Performance Optimizer** to configure.
4. (Optional) Click **Auto-detect** to automatically find preload candidates from your homepage.
5. (Optional) Apply a preset profile (Safe Mode, Balanced, or Aggressive) for quick setup.

== Frequently Asked Questions ==

= Will this break my Elementor site? =
No. The plugin automatically detects when Elementor is in edit or preview mode and disables all frontend optimizations. Your editing experience remains unaffected.

= Is this compatible with caching plugins? =
Yes. This plugin works alongside popular caching plugins like WP Rocket, LiteSpeed Cache, W3 Total Cache, and others. It handles frontend optimizations while your cache plugin handles page caching.

= Will the JavaScript delay feature break my site? =
The delay feature automatically excludes jQuery, Elementor scripts, and all wp-* core scripts. You can add additional exclusions if needed. A fallback timeout ensures scripts load even without user interaction.

= How does Local Google Fonts work? =
When enabled, the plugin scans your site for Google Fonts, downloads the CSS and font files to your server, and serves them locally. This eliminates external requests to Google and helps with GDPR compliance.

= Does this work with WooCommerce? =
Yes. The plugin includes specific WooCommerce optimizations that conditionally load cart fragments, styles, and block styles only on shop-related pages (shop, product, cart, checkout, account). This reduces bloat on your blog and other pages.

= What happens if something breaks? =
Settings are automatically backed up before every save. You can restore any of the last 5 backups with one click. There's also a "Safe Mode" profile that disables all optimizations for troubleshooting.

= Does this plugin collect any data? =
No. The plugin does not collect, track, or transmit any data. All features run entirely on your server. The auto-detect feature only fetches your own homepage - never external services.

= How does the database cleanup work? =
You can run manual cleanup or schedule automatic cleanup (daily, weekly, or monthly). The plugin removes excess post revisions (keeping a configurable number), old auto-drafts, trashed posts/comments, spam comments, orphaned metadata, and expired transients.

= Can I use different settings on different pages? =
Yes. The per-page overrides meta box (available on posts and pages) lets you disable specific optimizations like speculation rules, script deferral, JS delay, or local fonts on individual pages.

= Is this plugin multisite compatible? =
Yes. Each site in a multisite network has its own settings. Network activation is supported.

== Screenshots ==

1. Main settings page with Speculative Loading and Preconnect/Preload options.
2. Script Loading and JavaScript Optimization settings including delay and Heartbeat control.
3. WooCommerce conditional loading and Image optimization settings.
4. Local Google Fonts hosting with download management and Database Cleanup tools.
5. Utilities section with backups, profiles, and import/export functionality.
6. Dashboard widget showing active optimizations at a glance.

== Changelog ==

= 2.0.1 =
* FIX: Critical error when saving settings due to preg_split receiving array instead of string.
* FIX: Local Fonts download button now works even when feature is disabled.
* FIX: Documented pro-elements-handlers exclusion needed for Elementor Pro nav menus with JS delay.

= 2.0.0 =
* NEW: Settings backup system with 5 automatic snapshots and one-click restore.
* NEW: Named configuration profiles (Safe Mode, Balanced, Aggressive) with custom profile support.
* NEW: Import/Export settings as JSON for easy migration between sites.
* NEW: JavaScript delay until user interaction for dramatically faster initial page loads.
* NEW: Heartbeat API control - reduce frequency or disable entirely.
* NEW: Local Google Fonts hosting for GDPR compliance and faster loading.
* NEW: WooCommerce conditional asset loading (cart fragments, styles, block styles).
* NEW: Database cleanup tools with manual and scheduled options.
* NEW: Dashboard widget showing optimization status overview.
* NEW: Per-page overrides meta box for granular control on individual posts/pages.
* NEW: Query string removal from static assets for better CDN caching.
* IMPROVED: Expanded readme with comprehensive documentation.
* IMPROVED: Added plugin icon to settings page header.

= 1.1.0 =
* Redesigned admin settings page with modern corporate light theme.
* New card-based layout with color-coded sections.
* Improved toggle switches using W3Schools best practices.
* Better tooltip positioning and visibility.
* Inter font for improved readability.
* Enhanced responsive design for mobile devices.

= 1.0.0 =
* Initial release with speculative loading, preconnect/preload, script deferral, cleanup options, and image optimization.

== Upgrade Notice ==

= 2.0.0 =
Major feature release! Adds JavaScript delay, local Google Fonts, WooCommerce optimization, database cleanup, settings backups, profiles, and per-page overrides. All existing settings are preserved during upgrade.
