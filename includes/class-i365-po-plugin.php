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
	 * Maximum number of settings backups to retain.
	 *
	 * @var int
	 */
	const MAX_BACKUPS = 5;

	/**
	 * Option key for settings backups.
	 *
	 * @var string
	 */
	const BACKUPS_OPTION_KEY = 'i365_po_settings_backups';

	/**
	 * Option key for database version tracking.
	 *
	 * @var string
	 */
	const DB_VERSION_KEY = 'i365_po_db_version';

	/**
	 * Option key for named profiles.
	 *
	 * @var string
	 */
	const PROFILES_OPTION_KEY = 'i365_po_profiles';

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
		require_once I365_PO_PLUGIN_DIR . 'includes/class-i365-po-dashboard-widget.php';
		require_once I365_PO_PLUGIN_DIR . 'includes/class-i365-po-woocommerce.php';
		require_once I365_PO_PLUGIN_DIR . 'includes/class-i365-po-database.php';
		require_once I365_PO_PLUGIN_DIR . 'includes/class-i365-po-local-fonts.php';
		require_once I365_PO_PLUGIN_DIR . 'includes/class-i365-po-meta-box.php';

		I365_PO_Settings::init();
		I365_PO_Frontend::init();
		I365_PO_Dashboard_Widget::init();
		I365_PO_WooCommerce::init();
		I365_PO_Database::init();
		I365_PO_Local_Fonts::init();
		I365_PO_Meta_Box::init();
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
			// JavaScript Delay settings.
			'delay_js_enabled'       => false,
			'delay_js_timeout'       => 5000,
			'delay_js_exclude'       => array( 'jquery', 'jquery-core', 'jquery-migrate', 'elementor', 'wp-' ),
			// Heartbeat control settings.
			'heartbeat_behavior'     => 'default',
			'heartbeat_interval'     => 60,
			// Query string removal.
			'remove_query_strings'   => false,
			// WooCommerce conditional loading.
			'wc_conditional_enabled'    => false,
			'wc_disable_cart_fragments' => true,
			'wc_disable_styles'         => true,
			'wc_disable_blocks_styles'  => true,
			// Database cleanup settings.
			'db_cleanup_enabled'        => false,
			'db_cleanup_schedule'       => 'weekly',
			'db_revisions_keep'         => 5,
			// Local Google Fonts settings.
			'local_fonts_enabled'       => false,
			'local_fonts_display'       => 'swap',
			'local_fonts_preload'       => true,
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

		// Create a backup of current settings before making changes.
		self::backup_current_settings();

		if ( $is_reset ) {
			add_settings_error( 'i365_po_messages', 'i365_po_reset', __( 'Settings restored to defaults. Previous settings backed up.', '365i-performance-optimizer' ), 'updated' );
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

		// JavaScript Delay settings.
		$output['delay_js_enabled'] = ! empty( $input['delay_js_enabled'] );
		$output['delay_js_timeout'] = isset( $input['delay_js_timeout'] ) ? absint( $input['delay_js_timeout'] ) : 5000;
		// Clamp timeout between 1000ms and 30000ms.
		$output['delay_js_timeout'] = max( 1000, min( 30000, $output['delay_js_timeout'] ) );

		$delay_exclude_raw = preg_split( '/[\r\n,]+/', $input['delay_js_exclude'] ?? '', -1, PREG_SPLIT_NO_EMPTY );
		$delay_exclude     = array();
		if ( is_array( $delay_exclude_raw ) ) {
			foreach ( $delay_exclude_raw as $handle ) {
				$handle = sanitize_text_field( trim( $handle ) );
				if ( '' !== $handle ) {
					$delay_exclude[] = $handle;
				}
			}
		}
		$output['delay_js_exclude'] = ! empty( $delay_exclude ) ? $delay_exclude : $defaults['delay_js_exclude'];

		// Heartbeat control settings.
		$heartbeat_behavior = sanitize_text_field( $input['heartbeat_behavior'] ?? 'default' );
		$output['heartbeat_behavior'] = in_array( $heartbeat_behavior, array( 'default', 'reduce', 'disable_frontend', 'disable_everywhere' ), true )
			? $heartbeat_behavior
			: 'default';
		$output['heartbeat_interval'] = isset( $input['heartbeat_interval'] ) ? absint( $input['heartbeat_interval'] ) : 60;
		// Clamp interval between 15 and 300 seconds.
		$output['heartbeat_interval'] = max( 15, min( 300, $output['heartbeat_interval'] ) );

		// Query string removal.
		$output['remove_query_strings'] = ! empty( $input['remove_query_strings'] );

		// WooCommerce conditional loading.
		$output['wc_conditional_enabled']    = ! empty( $input['wc_conditional_enabled'] );
		$output['wc_disable_cart_fragments'] = ! empty( $input['wc_disable_cart_fragments'] );
		$output['wc_disable_styles']         = ! empty( $input['wc_disable_styles'] );
		$output['wc_disable_blocks_styles']  = ! empty( $input['wc_disable_blocks_styles'] );

		// Database cleanup settings.
		$output['db_cleanup_enabled'] = ! empty( $input['db_cleanup_enabled'] );
		$db_schedule = sanitize_text_field( $input['db_cleanup_schedule'] ?? 'weekly' );
		$output['db_cleanup_schedule'] = in_array( $db_schedule, array( 'daily', 'weekly', 'monthly' ), true )
			? $db_schedule
			: 'weekly';
		$output['db_revisions_keep'] = isset( $input['db_revisions_keep'] ) ? absint( $input['db_revisions_keep'] ) : 5;
		// Clamp revisions between 0 and 50.
		$output['db_revisions_keep'] = min( 50, max( 0, $output['db_revisions_keep'] ) );

		// Local Google Fonts settings.
		$output['local_fonts_enabled'] = ! empty( $input['local_fonts_enabled'] );
		$font_display = sanitize_text_field( $input['local_fonts_display'] ?? 'swap' );
		$output['local_fonts_display'] = in_array( $font_display, array( 'auto', 'block', 'swap', 'fallback', 'optional' ), true )
			? $font_display
			: 'swap';
		$output['local_fonts_preload'] = ! empty( $input['local_fonts_preload'] );

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

	/*
	|--------------------------------------------------------------------------
	| Settings Backup System
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create a backup of current settings before changes.
	 *
	 * @return bool True on success, false if no settings to backup.
	 */
	public static function backup_current_settings() {
		$current = get_option( I365_PO_OPTION_KEY, array() );

		// Don't backup empty settings.
		if ( empty( $current ) ) {
			return false;
		}

		$backups = get_option( self::BACKUPS_OPTION_KEY, array() );

		// Add timestamped backup with metadata.
		$backups[ time() ] = array(
			'settings' => $current,
			'version'  => I365_PO_VERSION,
			'user_id'  => get_current_user_id(),
			'user'     => wp_get_current_user()->display_name,
		);

		// Keep only the most recent backups.
		if ( count( $backups ) > self::MAX_BACKUPS ) {
			$keys = array_keys( $backups );
			sort( $keys, SORT_NUMERIC );
			// Remove oldest entries.
			while ( count( $backups ) > self::MAX_BACKUPS ) {
				unset( $backups[ array_shift( $keys ) ] );
			}
		}

		return update_option( self::BACKUPS_OPTION_KEY, $backups );
	}

	/**
	 * Get all available backups.
	 *
	 * @return array Array of backups keyed by timestamp.
	 */
	public static function get_backups() {
		$backups = get_option( self::BACKUPS_OPTION_KEY, array() );

		// Sort by timestamp descending (newest first).
		if ( ! empty( $backups ) ) {
			krsort( $backups, SORT_NUMERIC );
		}

		return $backups;
	}

	/**
	 * Restore settings from a specific backup.
	 *
	 * @param int $timestamp The backup timestamp to restore.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function restore_backup( $timestamp ) {
		$backups = get_option( self::BACKUPS_OPTION_KEY, array() );

		if ( ! isset( $backups[ $timestamp ] ) ) {
			return new \WP_Error(
				'invalid_backup',
				__( 'Backup not found.', '365i-performance-optimizer' )
			);
		}

		$backup_data = $backups[ $timestamp ];

		if ( empty( $backup_data['settings'] ) || ! is_array( $backup_data['settings'] ) ) {
			return new \WP_Error(
				'invalid_backup_data',
				__( 'Backup data is corrupted.', '365i-performance-optimizer' )
			);
		}

		// Create a backup of current settings before restore (so restore is reversible).
		self::backup_current_settings();

		// Restore the settings.
		$result = update_option( I365_PO_OPTION_KEY, $backup_data['settings'] );

		if ( $result ) {
			// Update the in-memory cache.
			self::update_settings_cache( wp_parse_args( $backup_data['settings'], self::defaults() ) );
		}

		return $result;
	}

	/**
	 * Delete a specific backup.
	 *
	 * @param int $timestamp The backup timestamp to delete.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete_backup( $timestamp ) {
		$backups = get_option( self::BACKUPS_OPTION_KEY, array() );

		if ( ! isset( $backups[ $timestamp ] ) ) {
			return false;
		}

		unset( $backups[ $timestamp ] );

		return update_option( self::BACKUPS_OPTION_KEY, $backups );
	}

	/**
	 * Get the most recent backup timestamp.
	 *
	 * @return int|null Timestamp or null if no backups exist.
	 */
	public static function get_last_backup_time() {
		$backups = self::get_backups();

		if ( empty( $backups ) ) {
			return null;
		}

		// Already sorted newest first.
		return key( $backups );
	}

	/*
	|--------------------------------------------------------------------------
	| Configuration Profiles
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get built-in preset profiles.
	 *
	 * @return array
	 */
	public static function get_builtin_profiles() {
		$defaults = self::defaults();

		return array(
			'safe_mode'   => array(
				'name'        => __( 'Safe Mode', '365i-performance-optimizer' ),
				'description' => __( 'All optimizations disabled for troubleshooting.', '365i-performance-optimizer' ),
				'builtin'     => true,
				'settings'    => array(
					'enable_speculation'     => false,
					'speculation_eagerness'  => 'conservative',
					'speculation_exclusions' => array(),
					'enable_preload'         => false,
					'preconnect_hosts'       => $defaults['preconnect_hosts'],
					'preload_stylesheet'     => '',
					'preload_font'           => '',
					'preload_hero'           => '',
					'hero_preload_on_front'  => true,
					'remove_emoji'           => false,
					'disable_embeds'         => false,
					'defer_scripts'          => false,
					'excluded_defer_handles' => $defaults['excluded_defer_handles'],
					'disable_xmlrpc'         => false,
					'remove_rest_link'       => false,
					'remove_oembed_link'     => false,
					'lcp_priority'           => false,
					'disable_front_lazy'     => false,
					'enable_detect_log'      => false,
					'delay_js_enabled'       => false,
					'delay_js_timeout'       => 5000,
					'delay_js_exclude'       => $defaults['delay_js_exclude'],
					'heartbeat_behavior'     => 'default',
					'heartbeat_interval'     => 60,
					'remove_query_strings'   => false,
					'wc_conditional_enabled'    => false,
					'wc_disable_cart_fragments' => true,
					'wc_disable_styles'         => true,
					'wc_disable_blocks_styles'  => true,
					'local_fonts_enabled'       => false,
					'local_fonts_display'       => 'swap',
					'local_fonts_preload'       => true,
				),
			),
			'balanced'    => array(
				'name'        => __( 'Balanced', '365i-performance-optimizer' ),
				'description' => __( 'Recommended settings for most sites.', '365i-performance-optimizer' ),
				'builtin'     => true,
				'settings'    => $defaults,
			),
			'aggressive'  => array(
				'name'        => __( 'Maximum Performance', '365i-performance-optimizer' ),
				'description' => __( 'All optimizations enabled for maximum speed.', '365i-performance-optimizer' ),
				'builtin'     => true,
				'settings'    => array(
					'enable_speculation'     => true,
					'speculation_eagerness'  => 'eager',
					'speculation_exclusions' => array( '/cart', '/checkout', '/my-account' ),
					'enable_preload'         => true,
					'preconnect_hosts'       => $defaults['preconnect_hosts'],
					'preload_stylesheet'     => $defaults['preload_stylesheet'],
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
					'delay_js_enabled'       => true,
					'delay_js_timeout'       => 5000,
					'delay_js_exclude'       => array( 'jquery', 'jquery-core', 'jquery-migrate', 'elementor', 'wp-' ),
					'heartbeat_behavior'     => 'disable_frontend',
					'heartbeat_interval'     => 120,
					'remove_query_strings'   => true,
					'wc_conditional_enabled'    => true,
					'wc_disable_cart_fragments' => true,
					'wc_disable_styles'         => true,
					'wc_disable_blocks_styles'  => true,
					'local_fonts_enabled'       => true,
					'local_fonts_display'       => 'swap',
					'local_fonts_preload'       => true,
				),
			),
		);
	}

	/**
	 * Get all profiles (built-in + custom).
	 *
	 * @return array
	 */
	public static function get_all_profiles() {
		$builtin = self::get_builtin_profiles();
		$custom  = get_option( self::PROFILES_OPTION_KEY, array() );

		return array_merge( $builtin, $custom );
	}

	/**
	 * Save current settings as a custom profile.
	 *
	 * @param string $name        Profile name.
	 * @param string $description Profile description.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function save_profile( $name, $description = '' ) {
		$name = sanitize_text_field( $name );

		if ( empty( $name ) ) {
			return new \WP_Error(
				'empty_name',
				__( 'Profile name cannot be empty.', '365i-performance-optimizer' )
			);
		}

		// Generate a slug from the name.
		$slug = sanitize_title( $name );

		// Check if it conflicts with built-in profiles.
		$builtin = self::get_builtin_profiles();
		if ( isset( $builtin[ $slug ] ) ) {
			return new \WP_Error(
				'reserved_name',
				__( 'This name is reserved for a built-in profile.', '365i-performance-optimizer' )
			);
		}

		$custom = get_option( self::PROFILES_OPTION_KEY, array() );

		$custom[ $slug ] = array(
			'name'        => $name,
			'description' => sanitize_text_field( $description ),
			'builtin'     => false,
			'settings'    => self::get_settings(),
			'created'     => time(),
		);

		return update_option( self::PROFILES_OPTION_KEY, $custom );
	}

	/**
	 * Apply a profile's settings.
	 *
	 * @param string $slug Profile slug.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function apply_profile( $slug ) {
		$profiles = self::get_all_profiles();

		if ( ! isset( $profiles[ $slug ] ) ) {
			return new \WP_Error(
				'invalid_profile',
				__( 'Profile not found.', '365i-performance-optimizer' )
			);
		}

		$profile = $profiles[ $slug ];

		if ( empty( $profile['settings'] ) || ! is_array( $profile['settings'] ) ) {
			return new \WP_Error(
				'invalid_profile_data',
				__( 'Profile data is corrupted.', '365i-performance-optimizer' )
			);
		}

		// Backup current settings first.
		self::backup_current_settings();

		// Merge profile settings with defaults to ensure all keys exist.
		$settings = wp_parse_args( $profile['settings'], self::defaults() );

		$result = update_option( I365_PO_OPTION_KEY, $settings );

		if ( $result ) {
			self::update_settings_cache( $settings );
		}

		return $result;
	}

	/**
	 * Delete a custom profile.
	 *
	 * @param string $slug Profile slug.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_profile( $slug ) {
		$builtin = self::get_builtin_profiles();

		if ( isset( $builtin[ $slug ] ) ) {
			return new \WP_Error(
				'cannot_delete_builtin',
				__( 'Built-in profiles cannot be deleted.', '365i-performance-optimizer' )
			);
		}

		$custom = get_option( self::PROFILES_OPTION_KEY, array() );

		if ( ! isset( $custom[ $slug ] ) ) {
			return new \WP_Error(
				'profile_not_found',
				__( 'Profile not found.', '365i-performance-optimizer' )
			);
		}

		unset( $custom[ $slug ] );

		return update_option( self::PROFILES_OPTION_KEY, $custom );
	}

	/*
	|--------------------------------------------------------------------------
	| Import/Export Settings
	|--------------------------------------------------------------------------
	*/

	/**
	 * Export settings as JSON.
	 *
	 * @return string JSON string.
	 */
	public static function export_settings() {
		$export_data = array(
			'plugin'        => '365i-performance-optimizer',
			'version'       => I365_PO_VERSION,
			'exported'      => gmdate( 'c' ),
			'site'          => wp_parse_url( home_url(), PHP_URL_HOST ),
			'settings'      => self::get_settings(),
		);

		return wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Import settings from JSON.
	 *
	 * @param string $json JSON string.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function import_settings( $json ) {
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'invalid_json',
				__( 'Invalid JSON format.', '365i-performance-optimizer' )
			);
		}

		if ( empty( $data['plugin'] ) || '365i-performance-optimizer' !== $data['plugin'] ) {
			return new \WP_Error(
				'invalid_export',
				__( 'This file is not a valid Performance Optimizer export.', '365i-performance-optimizer' )
			);
		}

		if ( empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return new \WP_Error(
				'missing_settings',
				__( 'Export file does not contain settings.', '365i-performance-optimizer' )
			);
		}

		// Backup current settings first.
		self::backup_current_settings();

		// Sanitize imported settings through the existing sanitization process.
		// We simulate a POST to reuse the sanitization logic.
		$sanitized = array();
		$defaults  = self::defaults();

		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $data['settings'][ $key ] ) ) {
				$value = $data['settings'][ $key ];

				// Type-specific sanitization.
				if ( is_bool( $default_value ) ) {
					$sanitized[ $key ] = (bool) $value;
				} elseif ( is_array( $default_value ) ) {
					$sanitized[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : $default_value;
				} elseif ( in_array( $key, array( 'preload_stylesheet', 'preload_font', 'preload_hero' ), true ) ) {
					$sanitized[ $key ] = esc_url_raw( $value );
				} elseif ( 'speculation_eagerness' === $key ) {
					$sanitized[ $key ] = in_array( $value, array( 'eager', 'moderate', 'conservative' ), true ) ? $value : $default_value;
				} else {
					$sanitized[ $key ] = sanitize_text_field( $value );
				}
			} else {
				$sanitized[ $key ] = $default_value;
			}
		}

		// Handle preconnect_hosts array specially.
		if ( isset( $data['settings']['preconnect_hosts'] ) && is_array( $data['settings']['preconnect_hosts'] ) ) {
			$hosts = array();
			foreach ( $data['settings']['preconnect_hosts'] as $host ) {
				$clean = esc_url_raw( $host );
				if ( ! empty( $clean ) ) {
					$hosts[] = $clean;
				}
			}
			$sanitized['preconnect_hosts'] = $hosts;
		}

		$result = update_option( I365_PO_OPTION_KEY, $sanitized );

		if ( $result ) {
			self::update_settings_cache( $sanitized );
		}

		return $result;
	}
}
