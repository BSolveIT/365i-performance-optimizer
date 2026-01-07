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

		$settings = I365_PO_Plugin::get_settings();
		$exclude  = $settings['excluded_defer_handles'];

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
}
