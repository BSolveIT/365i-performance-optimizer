<?php
/**
 * Front-end hooks.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I365_PO_Frontend {
	/**
	 * Wire all hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$settings = I365_PO_Plugin::get_settings();

		if ( $settings['remove_emoji'] ) {
			add_action( 'init', array( __CLASS__, 'disable_emoji' ) );
		}

		if ( $settings['disable_embeds'] ) {
			add_action( 'wp_footer', array( __CLASS__, 'disable_embeds' ), 1 );
		}

		if ( $settings['defer_scripts'] ) {
			add_filter( 'script_loader_tag', array( __CLASS__, 'defer_scripts' ), 10, 3 );
		}

		if ( $settings['enable_speculation'] ) {
			add_filter( 'wp_speculation_rules_configuration', array( __CLASS__, 'speculation_rules' ) );
		}

		if ( $settings['enable_preload'] ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_stylesheet_preload' ), 5 );
			add_action( 'wp_head', array( __CLASS__, 'output_preloads' ), 1 );
		}

		if ( $settings['disable_xmlrpc'] ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
		}

		if ( $settings['remove_rest_link'] || $settings['remove_oembed_link'] ) {
			add_action( 'init', array( __CLASS__, 'clean_head_links' ) );
		}

		if ( $settings['lcp_priority'] ) {
			add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'lcp_priority' ), 10, 3 );
		}

		if ( $settings['disable_front_lazy'] ) {
			add_filter( 'wp_lazy_loading_enabled', array( __CLASS__, 'lazy_loading_sanity' ), 10, 2 );
		}

		// JavaScript delay until interaction.
		if ( ! empty( $settings['delay_js_enabled'] ) ) {
			add_filter( 'script_loader_tag', array( __CLASS__, 'delay_scripts' ), 20, 3 );
			add_action( 'wp_head', array( __CLASS__, 'output_delay_loader' ), 2 );
		}

		// Heartbeat control.
		if ( 'default' !== $settings['heartbeat_behavior'] ) {
			add_action( 'init', array( __CLASS__, 'control_heartbeat' ) );
			if ( 'disable_frontend' === $settings['heartbeat_behavior'] || 'disable_everywhere' === $settings['heartbeat_behavior'] ) {
				add_action( 'wp_enqueue_scripts', array( __CLASS__, 'deregister_heartbeat' ), 99 );
			}
			if ( 'disable_everywhere' === $settings['heartbeat_behavior'] ) {
				add_action( 'admin_enqueue_scripts', array( __CLASS__, 'deregister_heartbeat' ), 99 );
			}
			if ( 'reduce' === $settings['heartbeat_behavior'] ) {
				add_filter( 'heartbeat_settings', array( __CLASS__, 'modify_heartbeat_settings' ) );
			}
		}

		// Query string removal.
		if ( ! empty( $settings['remove_query_strings'] ) ) {
			add_filter( 'script_loader_src', array( __CLASS__, 'remove_query_strings' ), 15, 2 );
			add_filter( 'style_loader_src', array( __CLASS__, 'remove_query_strings' ), 15, 2 );
		}
	}

	/**
	 * Remove emoji scripts/styles.
	 *
	 * @return void
	 */
	public static function disable_emoji() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

	/**
	 * Remove wp-embed script on front end.
	 *
	 * @return void
	 */
	public static function disable_embeds() {
		if ( I365_PO_Plugin::is_frontend_context() ) {
			wp_deregister_script( 'wp-embed' );
		}
	}

	/**
	 * Defer scripts except for Elementor, wp-* and jQuery or user exclusions.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @param string $src    Script URL.
	 *
	 * @return string
	 */
	public static function defer_scripts( $tag, $handle, $src ) {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return $tag;
		}

		// Check per-page override.
		if ( I365_PO_Meta_Box::is_override_active( 'disable_defer' ) ) {
			return $tag;
		}

		$settings = I365_PO_Plugin::get_settings();
		$exclude  = $settings['excluded_defer_handles'];

		// Merge per-page excluded scripts.
		$page_excluded = I365_PO_Meta_Box::get_excluded_scripts();
		if ( ! empty( $page_excluded ) ) {
			$exclude = array_merge( $exclude, $page_excluded );
		}

		if ( self::is_excluded_handle( $handle, $exclude ) ) {
			return $tag;
		}

		if ( false !== stripos( $tag, ' defer' ) || false !== stripos( $tag, ' async' ) || false !== stripos( $tag, ' type="module"' ) ) {
			return $tag;
		}

		return preg_replace( '/<script\s+/i', '<script defer ', $tag, 1 );
	}

	/**
	 * Determine if a handle should be skipped.
	 *
	 * @param string $handle  Current handle.
	 * @param array  $exclude Exclusion list.
	 *
	 * @return bool
	 */
	private static function is_excluded_handle( $handle, $exclude ) {
		if ( I365_PO_Plugin::is_elementor_editor() ) {
			return true;
		}

		if ( strpos( $handle, 'elementor' ) !== false || strpos( $handle, 'jquery' ) !== false || strpos( $handle, 'wp-' ) === 0 ) {
			return true;
		}

		foreach ( $exclude as $skip ) {
			if ( $skip === $handle || strpos( $handle, $skip ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Speculative loading configuration.
	 *
	 * @param array $config Existing config.
	 *
	 * @return array
	 */
	public static function speculation_rules( $config ) {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return $config;
		}

		// Check per-page override.
		if ( I365_PO_Meta_Box::is_override_active( 'disable_speculation' ) ) {
			return $config;
		}

		$settings = I365_PO_Plugin::get_settings();

		if ( I365_PO_Plugin::is_speculation_excluded( $settings['speculation_exclusions'] ) ) {
			return $config;
		}

		$config['eagerness'] = $settings['speculation_eagerness'];

		if ( ! empty( $settings['speculation_exclusions'] ) ) {
			$config['excludes'] = array();
			foreach ( $settings['speculation_exclusions'] as $path ) {
				$config['excludes'][] = array(
					'path' => $path,
				);
			}
		}

		return $config;
	}

	/**
	 * Output preconnect and preload hints.
	 *
	 * @return void
	 */
	public static function output_preloads() {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return;
		}

		$settings = I365_PO_Plugin::get_settings();

		foreach ( $settings['preconnect_hosts'] as $host ) {
			$is_cross = ( wp_parse_url( $host, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) );
			$cross    = $is_cross ? ' crossorigin' : '';
			printf( '<link rel="preconnect" href="%1$s"%2$s>%3$s', esc_url( $host ), $cross, "\n" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( ! empty( $settings['preload_font'] ) ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>%s',
				esc_url( $settings['preload_font'] ),
				"\n"
			);
		}

		if ( ! empty( $settings['preload_hero'] ) ) {
			if ( ! $settings['hero_preload_on_front'] || is_front_page() ) {
				printf(
					'<link rel="preload" as="image" fetchpriority="high" href="%s">%s',
					esc_url( $settings['preload_hero'] ),
					"\n"
				);
			}
		}
	}

	/**
	 * Clean REST and oEmbed link tags on the front end.
	 *
	 * @return void
	 */
	public static function clean_head_links() {
		if ( is_admin() ) {
			return;
		}

		$settings = I365_PO_Plugin::get_settings();

		if ( $settings['remove_rest_link'] ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head' );
		}

		if ( $settings['remove_oembed_link'] ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}
	}

	/**
	 * Mark LCP images as high priority when applicable.
	 *
	 * @param array        $attr       Attributes.
	 * @param WP_Post      $attachment Attachment.
	 * @param string|array $size       Size.
	 *
	 * @return array
	 */
	public static function lcp_priority( $attr, $attachment, $size ) {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return $attr;
		}

		if ( empty( $attr['loading'] ) && empty( $attr['fetchpriority'] ) ) {
			$attr['fetchpriority'] = 'high';
		}

		return $attr;
	}

	/**
	 * Disable lazy-loading on front page images for sanity.
	 *
	 * @param bool   $default Default value.
	 * @param string $tag     Tag name.
	 *
	 * @return bool
	 */
	public static function lazy_loading_sanity( $default, $tag ) {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return $default;
		}

		// Check per-page override to force lazy-loading.
		if ( I365_PO_Meta_Box::is_override_active( 'force_lazy_load' ) ) {
			return $default; // Keep default (lazy-loading enabled).
		}

		if ( 'img' === $tag && is_front_page() ) {
			return false;
		}

		return $default;
	}

	/**
	 * Enqueue stylesheet with preload metadata to satisfy plugin checks.
	 *
	 * @return void
	 */
	public static function enqueue_stylesheet_preload() {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return;
		}

		$settings = I365_PO_Plugin::get_settings();

		if ( empty( $settings['preload_stylesheet'] ) ) {
			return;
		}

		$url    = esc_url_raw( $settings['preload_stylesheet'] );
		$handle = 'i365-po-preload-style';

		wp_enqueue_style( $handle, $url, array(), I365_PO_VERSION );
		// Inform WordPress this should be preloaded.
		wp_style_add_data( $handle, 'preload', true );
	}

	/*
	|--------------------------------------------------------------------------
	| JavaScript Delay Until Interaction
	|--------------------------------------------------------------------------
	*/

	/**
	 * Delay non-critical scripts until user interaction.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @param string $src    Script URL.
	 *
	 * @return string
	 */
	public static function delay_scripts( $tag, $handle, $src ) {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return $tag;
		}

		// Check per-page override.
		if ( I365_PO_Meta_Box::is_override_active( 'disable_delay_js' ) ) {
			return $tag;
		}

		$settings = I365_PO_Plugin::get_settings();
		$exclude  = $settings['delay_js_exclude'];

		// Merge per-page excluded scripts.
		$page_excluded = I365_PO_Meta_Box::get_excluded_scripts();
		if ( ! empty( $page_excluded ) ) {
			$exclude = array_merge( $exclude, $page_excluded );
		}

		// Check if this script should be excluded from delay.
		if ( self::is_delay_excluded( $handle, $exclude ) ) {
			return $tag;
		}

		// Don't delay scripts that are already deferred/async or modules.
		if ( false !== stripos( $tag, ' async' ) || false !== stripos( $tag, ' type="module"' ) ) {
			return $tag;
		}

		// Don't delay inline scripts (no src).
		if ( empty( $src ) ) {
			return $tag;
		}

		// Transform the script tag to delay loading.
		// Change src to data-i365-src and add data-i365-delay marker.
		$tag = preg_replace(
			'/(<script[^>]*)\ssrc=["\']([^"\']+)["\']([^>]*>)/i',
			'$1 data-i365-src="$2" data-i365-delay$3',
			$tag
		);

		// Add type="text/plain" to prevent execution.
		$tag = preg_replace(
			'/(<script)(\s)/i',
			'$1 type="text/plain"$2',
			$tag,
			1
		);

		return $tag;
	}

	/**
	 * Check if a handle should be excluded from delay.
	 *
	 * @param string $handle  Current handle.
	 * @param array  $exclude Exclusion list.
	 *
	 * @return bool
	 */
	private static function is_delay_excluded( $handle, $exclude ) {
		// Always exclude critical scripts.
		$always_exclude = array( 'jquery', 'jquery-core', 'jquery-migrate', 'elementor' );

		foreach ( $always_exclude as $skip ) {
			if ( $skip === $handle || strpos( $handle, $skip ) !== false ) {
				return true;
			}
		}

		// Check wp-* prefix.
		if ( strpos( $handle, 'wp-' ) === 0 ) {
			return true;
		}

		// Check user exclusions.
		foreach ( $exclude as $skip ) {
			$skip = trim( $skip );
			if ( empty( $skip ) ) {
				continue;
			}
			if ( $skip === $handle || strpos( $handle, $skip ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Output the delay loader script inline in head.
	 *
	 * @return void
	 */
	public static function output_delay_loader() {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return;
		}

		$settings = I365_PO_Plugin::get_settings();
		$timeout  = absint( $settings['delay_js_timeout'] );

		// Read and output the loader script.
		$loader_file = I365_PO_PLUGIN_DIR . 'assets/js/delay-interaction.js';

		if ( ! file_exists( $loader_file ) ) {
			return;
		}

		$script = file_get_contents( $loader_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( empty( $script ) ) {
			return;
		}

		// Output timeout variable and inline script.
		// The script content is from a trusted plugin file, not user input.
		echo '<script id="i365-delay-loader">window.i365DelayTimeout=' . absint( $timeout ) . ';';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted plugin JS file content.
		echo $script;
		echo "</script>\n";
	}

	/*
	|--------------------------------------------------------------------------
	| Heartbeat API Control
	|--------------------------------------------------------------------------
	*/

	/**
	 * Control WordPress Heartbeat API behavior.
	 *
	 * @return void
	 */
	public static function control_heartbeat() {
		// This method is a placeholder for initialization.
		// Actual work is done by deregister_heartbeat and modify_heartbeat_settings.
	}

	/**
	 * Deregister the heartbeat script.
	 *
	 * @return void
	 */
	public static function deregister_heartbeat() {
		wp_deregister_script( 'heartbeat' );
	}

	/**
	 * Modify heartbeat settings to reduce frequency.
	 *
	 * @param array $settings Heartbeat settings.
	 *
	 * @return array
	 */
	public static function modify_heartbeat_settings( $settings ) {
		$opts = I365_PO_Plugin::get_settings();

		$settings['interval'] = absint( $opts['heartbeat_interval'] );

		return $settings;
	}

	/*
	|--------------------------------------------------------------------------
	| Query String Removal
	|--------------------------------------------------------------------------
	*/

	/**
	 * Remove version query strings from static resources.
	 *
	 * @param string $src    Resource URL.
	 * @param string $handle Resource handle.
	 *
	 * @return string
	 */
	public static function remove_query_strings( $src, $handle ) {
		// Only process if there's a version query string.
		if ( strpos( $src, '?ver=' ) !== false || strpos( $src, '&ver=' ) !== false ) {
			$src = remove_query_arg( 'ver', $src );
		}

		return $src;
	}
}
