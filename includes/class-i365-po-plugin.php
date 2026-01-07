<?php
/**
 * Core plugin helpers.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I365_PO_Plugin {
	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Hook everything.
	 *
	 * @return void
	 */
	public static function init() {
		register_activation_hook( I365_PO_PLUGIN_FILE, array( __CLASS__, 'activate' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'bootstrap' ) );
	}

	/**
	 * Seed defaults on activation without overwriting existing settings.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! get_option( I365_PO_OPTION_KEY ) ) {
			add_option( I365_PO_OPTION_KEY, self::defaults() );
		}
	}

	/**
	 * Load dependencies and kick off features.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		require_once I365_PO_PLUGIN_DIR . 'includes/class-i365-po-settings.php';
		require_once I365_PO_PLUGIN_DIR . 'includes/class-i365-po-frontend.php';

		I365_PO_Settings::init();
		I365_PO_Frontend::init();
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enable_speculation'     => true,
			'speculation_eagerness'  => 'eager',
			'speculation_exclusions' => array(),
			'enable_preload'         => true,
			'preconnect_hosts'       => array_values(
				array_filter(
					array_unique(
						array(
							home_url(),
							'https://fonts.gstatic.com',
						)
					)
				)
			),
			'preload_stylesheet'     => get_stylesheet_uri(),
			'preload_font'           => '',
			'preload_hero'           => '',
			'hero_preload_on_front'  => true,
			'remove_emoji'           => true,
			'disable_embeds'         => true,
			'defer_scripts'          => true,
			'excluded_defer_handles' => array( 'elementor', 'jquery', 'wp-' ),
			'disable_xmlrpc'         => true,
			'remove_rest_link'       => true,
			'remove_oembed_link'     => true,
			'lcp_priority'           => true,
			'disable_front_lazy'     => true,
			'enable_detect_log'      => false,
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( null === self::$settings ) {
			// Migrate old option key if present.
			$stored = get_option( I365_PO_OPTION_KEY, array() );
			$legacy = get_option( 'wppo_settings', null );
			if ( empty( $stored ) && is_array( $legacy ) ) {
				update_option( I365_PO_OPTION_KEY, $legacy );
				$stored = $legacy;
			}

			self::$settings = wp_parse_args( $stored, self::defaults() );
		}

		return self::$settings;
	}

	/**
	 * Persist new settings in memory to avoid stale reads.
	 *
	 * @param array $settings Clean settings array.
	 *
	 * @return void
	 */
	public static function update_settings_cache( $settings ) {
		self::$settings = $settings;
	}

	/**
	 * Sanitize settings coming from the form.
	 *
	 * @param array $input Raw input.
	 *
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$output   = array();

		$nonce    = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$is_reset = ! empty( $_POST['i365_po_reset_defaults'] );

		// Always validate the nonce to satisfy plugin-check expectations.
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'i365_po_settings_group-options' ) ) {
			add_settings_error( 'i365_po_messages', 'i365_po_nonce', __( 'Security check failed.', '365i-performance-optimizer' ), 'error' );
			return get_option( I365_PO_OPTION_KEY, $defaults );
		}

		if ( $is_reset ) {
			add_settings_error( 'i365_po_messages', 'i365_po_reset', __( 'Settings restored to defaults.', '365i-performance-optimizer' ), 'updated' );
			self::update_settings_cache( $defaults );
			return $defaults;
		}

		$output['enable_speculation']    = ! empty( $input['enable_speculation'] );
		$speculation_eagerness = sanitize_text_field( $input['speculation_eagerness'] ?? '' );
		$output['speculation_eagerness'] = in_array( $speculation_eagerness, array( 'eager', 'moderate', 'conservative' ), true ) ? $speculation_eagerness : $defaults['speculation_eagerness'];
		$output['speculation_exclusions'] = array();
		if ( ! empty( $input['speculation_exclusions'] ) ) {
			$exclusions = preg_split( '/[\r\n,]+/', $input['speculation_exclusions'], -1, PREG_SPLIT_NO_EMPTY );
			foreach ( (array) $exclusions as $path ) {
				$path = sanitize_text_field( $path );
				$path = '/' . ltrim( trim( $path ), '/' );
				$output['speculation_exclusions'][] = $path;
			}
		}

		$output['enable_preload'] = ! empty( $input['enable_preload'] );

		$hosts = array_filter(
			array_map(
				'trim',
				preg_split( '/[\r\n,]+/', $input['preconnect_hosts'] ?? '', -1, PREG_SPLIT_NO_EMPTY )
			)
		);
		$output['preconnect_hosts'] = array();
		foreach ( $hosts as $host ) {
			$sanitized = esc_url_raw( $host );
			if ( ! empty( $sanitized ) ) {
				$output['preconnect_hosts'][] = $sanitized;
			}
		}

		$output['preload_stylesheet'] = ! empty( $input['preload_stylesheet'] ) ? esc_url_raw( $input['preload_stylesheet'] ) : '';
		$output['preload_font']       = ! empty( $input['preload_font'] ) ? esc_url_raw( $input['preload_font'] ) : '';
		$output['preload_hero']       = ! empty( $input['preload_hero'] ) ? esc_url_raw( $input['preload_hero'] ) : '';
		$output['hero_preload_on_front'] = ! empty( $input['hero_preload_on_front'] );

		$output['remove_emoji']   = ! empty( $input['remove_emoji'] );
		$output['disable_embeds'] = ! empty( $input['disable_embeds'] );

		$output['defer_scripts'] = ! empty( $input['defer_scripts'] );

		$handles_raw  = preg_split( '/[\r\n,]+/', $input['excluded_defer_handles'] ?? '', -1, PREG_SPLIT_NO_EMPTY );
		$handles      = array();
		if ( is_array( $handles_raw ) ) {
			foreach ( $handles_raw as $handle ) {
				$handle = sanitize_text_field( $handle );
				if ( '' !== $handle ) {
					$handles[] = $handle;
				}
			}
		}
		$output['excluded_defer_handles'] = $handles;

		$output['disable_xmlrpc'] = ! empty( $input['disable_xmlrpc'] );
		$output['remove_rest_link'] = ! empty( $input['remove_rest_link'] );
		$output['remove_oembed_link'] = ! empty( $input['remove_oembed_link'] );

		$output['lcp_priority']       = ! empty( $input['lcp_priority'] );
		$output['disable_front_lazy'] = ! empty( $input['disable_front_lazy'] );
		$output['enable_detect_log']  = ! empty( $input['enable_detect_log'] );

		// Ensure we always save an array for hosts.
		if ( empty( $output['preconnect_hosts'] ) && ! empty( $defaults['preconnect_hosts'] ) ) {
			$output['preconnect_hosts'] = $defaults['preconnect_hosts'];
		}

		self::validate_urls( $output );

		self::update_settings_cache( $output );

		return $output;
	}

	/**
	 * Check if Elementor editor/preview is active.
	 *
	 * @return bool
	 */
	public static function is_elementor_editor() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return false;
		}

		try {
			$plugin = \Elementor\Plugin::$instance;

			return (
				! empty( $plugin->editor ) && $plugin->editor->is_edit_mode()
			) || (
				! empty( $plugin->preview ) && $plugin->preview->is_preview_mode()
			);
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Should front-end-only features run?
	 *
	 * @return bool
	 */
	public static function is_frontend_context() {
		return ! is_admin() && ! self::is_elementor_editor();
	}

	/**
	 * Validate URLs and surface admin warnings.
	 *
	 * @param array $settings Settings.
	 *
	 * @return void
	 */
	private static function validate_urls( $settings ) {
		static $ran = false;
		if ( $ran ) {
			return;
		}
		$ran = true;

		$urls = array(
			'preload_stylesheet' => __( 'Stylesheet preload URL', '365i-performance-optimizer' ),
			'preload_font'       => __( 'Font preload URL', '365i-performance-optimizer' ),
			'preload_hero'       => __( 'Hero image preload URL', '365i-performance-optimizer' ),
		);

		$warnings = array();

		foreach ( $urls as $key => $label ) {
			if ( empty( $settings[ $key ] ) ) {
				continue;
			}

			$scheme = wp_parse_url( $settings[ $key ], PHP_URL_SCHEME );
			if ( $scheme && 'https' !== strtolower( $scheme ) ) {
				$warnings[] = sprintf(
					/* translators: 1: field label */
					esc_html__( '%1$s is not using HTTPS. Consider using HTTPS to avoid mixed-content issues.', '365i-performance-optimizer' ),
					$label
				);
			}

			$host_current = wp_parse_url( home_url(), PHP_URL_HOST );
			$host_target  = wp_parse_url( $settings[ $key ], PHP_URL_HOST );
			if ( $host_current && $host_target && $host_current === $host_target ) {
				$response = wp_remote_head(
					$settings[ $key ],
					array(
						'timeout' => 3,
						'redirection' => 1,
					)
				);

				if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) >= 400 ) {
					$warnings[] = sprintf(
						/* translators: 1: field label */
						esc_html__( '%1$s could not be reached. Please verify the URL.', '365i-performance-optimizer' ),
						$label
					);
				}
			}
		}

		if ( ! empty( $warnings ) ) {
			add_settings_error(
				'i365_po_messages',
				'i365_po_url_warnings',
				'<ul><li>' . implode( '</li><li>', array_map( 'esc_html', array_unique( $warnings ) ) ) . '</li></ul>',
				'warning'
			);
		}
	}

	/**
	 * Check if current path is excluded from speculation.
	 *
	 * @param array $exclusions Exclusions.
	 *
	 * @return bool
	 */
	public static function is_speculation_excluded( $exclusions ) {
		if ( empty( $exclusions ) ) {
			return false;
		}

		$current = wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH );
		foreach ( $exclusions as $path ) {
			if ( $current === $path || ( $path !== '/' && strpos( $current, trailingslashit( $path ) ) === 0 ) ) {
				return true;
			}
		}

		return false;
	}
}
