<?php
/**
 * Plugin Name: 365i Performance Optimizer
 * Plugin URI: https://www.365i.co.uk/plugins/365i-performance-optimizer
 * Description: Elementor-safe performance tweaks for WordPress 6.9: speculation rules, preconnect/preload, defer scripts, emoji cleanup, and smarter images.
 * Version: 1.1.0
 * Author: Mark McNeece (365i)
 * Author URI: https://www.365i.co.uk/author/mark-mcneece/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 365i-performance-optimizer
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'I365_PO_VERSION', '1.1.0' );
define( 'I365_PO_PLUGIN_FILE', __FILE__ );
define( 'I365_PO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'I365_PO_OPTION_KEY', 'i365_po_settings' );

require_once I365_PO_PLUGIN_DIR . 'includes/class-i365-po-plugin.php';

I365_PO_Plugin::init();
