<?php
/**
 * Local Google Fonts Hosting.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I365_PO_Local_Fonts {
	/**
	 * Upload directory for fonts.
	 *
	 * @var string
	 */
	const FONTS_DIR = 'i365-fonts';

	/**
	 * Transient key for cached fonts data.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'i365_po_local_fonts_cache';

	/**
	 * Cache expiration in seconds (30 days).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 2592000;

	/**
	 * Initialize local fonts hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// AJAX handlers must always be registered so admin can download fonts.
		add_action( 'wp_ajax_i365_po_download_fonts', array( __CLASS__, 'ajax_download_fonts' ) );
		add_action( 'wp_ajax_i365_po_clear_fonts', array( __CLASS__, 'ajax_clear_fonts' ) );

		$settings = I365_PO_Plugin::get_settings();

		// Frontend hooks only run if enabled.
		if ( empty( $settings['local_fonts_enabled'] ) ) {
			return;
		}

		// Replace Google Fonts with local versions.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_local_fonts' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'output_font_preloads' ), 1 );
		add_filter( 'style_loader_src', array( __CLASS__, 'intercept_google_fonts' ), 10, 2 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_google_fonts' ), 100 );
	}

	/**
	 * Get the fonts upload directory path.
	 *
	 * @return string
	 */
	public static function get_fonts_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . self::FONTS_DIR;
	}

	/**
	 * Get the fonts upload directory URL.
	 *
	 * @return string
	 */
	public static function get_fonts_url() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['baseurl'] ) . self::FONTS_DIR;
	}

	/**
	 * Get cached fonts data.
	 *
	 * @return array|false
	 */
	public static function get_cached_fonts() {
		return get_transient( self::CACHE_KEY );
	}

	/**
	 * Set cached fonts data.
	 *
	 * @param array $data Fonts data.
	 *
	 * @return bool
	 */
	public static function set_cached_fonts( $data ) {
		return set_transient( self::CACHE_KEY, $data, self::CACHE_EXPIRATION );
	}

	/**
	 * Clear cached fonts data.
	 *
	 * @return bool
	 */
	public static function clear_cached_fonts() {
		return delete_transient( self::CACHE_KEY );
	}

	/**
	 * Detect Google Fonts URLs in the page.
	 *
	 * @return array Array of Google Fonts URLs.
	 */
	public static function detect_google_fonts() {
		global $wp_styles;

		$google_fonts = array();

		if ( ! isset( $wp_styles ) || ! is_object( $wp_styles ) ) {
			return $google_fonts;
		}

		foreach ( $wp_styles->queue as $handle ) {
			if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
				continue;
			}

			$src = $wp_styles->registered[ $handle ]->src;

			if ( self::is_google_fonts_url( $src ) ) {
				$google_fonts[ $handle ] = $src;
			}
		}

		return $google_fonts;
	}

	/**
	 * Check if a URL is a Google Fonts URL.
	 *
	 * @param string $url URL to check.
	 *
	 * @return bool
	 */
	public static function is_google_fonts_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		return in_array( $host, array( 'fonts.googleapis.com', 'fonts.gstatic.com' ), true );
	}

	/**
	 * Download Google Fonts and save locally.
	 *
	 * @param string $google_fonts_url Google Fonts CSS URL.
	 *
	 * @return array|WP_Error Font data or error.
	 */
	public static function download_fonts( $google_fonts_url ) {
		// Ensure directory exists.
		$fonts_dir = self::get_fonts_dir();
		if ( ! self::ensure_fonts_directory() ) {
			return new \WP_Error(
				'directory_error',
				__( 'Could not create fonts directory.', '365i-performance-optimizer' )
			);
		}

		// Fetch Google Fonts CSS with modern user agent for woff2.
		$response = wp_remote_get(
			$google_fonts_url,
			array(
				'timeout'    => 30,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$css = wp_remote_retrieve_body( $response );

		if ( empty( $css ) ) {
			return new \WP_Error(
				'empty_response',
				__( 'Google Fonts returned empty response.', '365i-performance-optimizer' )
			);
		}

		// Parse CSS to find font URLs.
		$font_urls = self::extract_font_urls( $css );

		if ( empty( $font_urls ) ) {
			return new \WP_Error(
				'no_fonts_found',
				__( 'No font files found in CSS.', '365i-performance-optimizer' )
			);
		}

		// Download each font file.
		$downloaded_fonts = array();
		foreach ( $font_urls as $font_url ) {
			$result = self::download_font_file( $font_url );
			if ( ! is_wp_error( $result ) ) {
				$downloaded_fonts[ $font_url ] = $result;
			}
		}

		if ( empty( $downloaded_fonts ) ) {
			return new \WP_Error(
				'download_failed',
				__( 'Failed to download any font files.', '365i-performance-optimizer' )
			);
		}

		// Rewrite CSS with local URLs.
		$local_css = self::rewrite_css( $css, $downloaded_fonts );

		// Save local CSS.
		$css_file = $fonts_dir . '/fonts.css';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$saved = file_put_contents( $css_file, $local_css );

		if ( false === $saved ) {
			return new \WP_Error(
				'css_save_failed',
				__( 'Failed to save local CSS file.', '365i-performance-optimizer' )
			);
		}

		$font_data = array(
			'source_url'  => $google_fonts_url,
			'fonts'       => $downloaded_fonts,
			'css_file'    => $css_file,
			'css_url'     => self::get_fonts_url() . '/fonts.css',
			'downloaded'  => time(),
			'font_count'  => count( $downloaded_fonts ),
		);

		// Cache the font data.
		self::set_cached_fonts( $font_data );

		return $font_data;
	}

	/**
	 * Ensure fonts directory exists.
	 *
	 * @return bool
	 */
	private static function ensure_fonts_directory() {
		$fonts_dir = self::get_fonts_dir();

		if ( ! file_exists( $fonts_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			return mkdir( $fonts_dir, 0755, true );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		return is_writable( $fonts_dir );
	}

	/**
	 * Extract font URLs from CSS.
	 *
	 * @param string $css Google Fonts CSS.
	 *
	 * @return array Array of font URLs.
	 */
	private static function extract_font_urls( $css ) {
		$urls = array();

		// Match url() in CSS.
		if ( preg_match_all( '/url\s*\(\s*["\']?(https:\/\/fonts\.gstatic\.com[^"\')\s]+)["\']?\s*\)/i', $css, $matches ) ) {
			$urls = array_unique( $matches[1] );
		}

		return $urls;
	}

	/**
	 * Download a single font file.
	 *
	 * @param string $url Font file URL.
	 *
	 * @return string|WP_Error Local file path or error.
	 */
	private static function download_font_file( $url ) {
		$fonts_dir = self::get_fonts_dir();

		// Generate filename from URL.
		$filename = self::generate_font_filename( $url );
		$filepath = $fonts_dir . '/' . $filename;

		// Skip if already exists.
		if ( file_exists( $filepath ) ) {
			return $filename;
		}

		// Download the font file.
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return new \WP_Error(
				'empty_font',
				sprintf(
					/* translators: %s: Font URL */
					__( 'Empty response for font: %s', '365i-performance-optimizer' ),
					$url
				)
			);
		}

		// Save the font file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$saved = file_put_contents( $filepath, $body );

		if ( false === $saved ) {
			return new \WP_Error(
				'save_failed',
				sprintf(
					/* translators: %s: Font filename */
					__( 'Failed to save font: %s', '365i-performance-optimizer' ),
					$filename
				)
			);
		}

		return $filename;
	}

	/**
	 * Generate a filename from a font URL.
	 *
	 * @param string $url Font URL.
	 *
	 * @return string Filename.
	 */
	private static function generate_font_filename( $url ) {
		// Extract filename from URL or generate hash-based name.
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$ext  = pathinfo( $path, PATHINFO_EXTENSION );

		if ( empty( $ext ) ) {
			$ext = 'woff2';
		}

		// Use MD5 hash of URL for unique filename.
		$hash = md5( $url );

		return $hash . '.' . $ext;
	}

	/**
	 * Rewrite CSS to use local font URLs.
	 *
	 * @param string $css         Original CSS.
	 * @param array  $font_map    Map of remote URLs to local filenames.
	 *
	 * @return string Modified CSS.
	 */
	private static function rewrite_css( $css, $font_map ) {
		$settings   = I365_PO_Plugin::get_settings();
		$fonts_url  = self::get_fonts_url();

		// Replace each remote URL with local URL.
		foreach ( $font_map as $remote_url => $local_filename ) {
			$local_url = $fonts_url . '/' . $local_filename;
			$css       = str_replace( $remote_url, $local_url, $css );
		}

		// Update font-display property if set.
		$font_display = $settings['local_fonts_display'];
		if ( 'swap' !== $font_display ) {
			$css = preg_replace( '/font-display\s*:\s*[^;]+;/i', 'font-display: ' . $font_display . ';', $css );
		}

		// Add font-display if not present.
		$css = preg_replace( '/(@font-face\s*\{)(?![^}]*font-display)/i', '$1 font-display: ' . $font_display . ';', $css );

		return $css;
	}

	/**
	 * Enqueue local fonts CSS.
	 *
	 * @return void
	 */
	public static function enqueue_local_fonts() {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return;
		}

		// Check per-page override.
		if ( I365_PO_Meta_Box::is_override_active( 'disable_local_fonts' ) ) {
			return;
		}

		$cached = self::get_cached_fonts();

		if ( empty( $cached ) || empty( $cached['css_url'] ) ) {
			return;
		}

		// Enqueue the local CSS using wp_enqueue_style for proper WordPress integration.
		wp_enqueue_style( 'i365-local-fonts', $cached['css_url'], array(), I365_PO_VERSION, 'all' );
	}

	/**
	 * Output font preload hints in head.
	 *
	 * @return void
	 */
	public static function output_font_preloads() {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return;
		}

		// Check per-page override.
		if ( I365_PO_Meta_Box::is_override_active( 'disable_local_fonts' ) ) {
			return;
		}

		$cached = self::get_cached_fonts();

		if ( empty( $cached ) || empty( $cached['fonts'] ) ) {
			return;
		}

		$settings = I365_PO_Plugin::get_settings();

		// Output preload hints if enabled.
		if ( ! empty( $settings['local_fonts_preload'] ) ) {
			// Preload the first few font files (most important).
			$preload_count = 0;
			foreach ( $cached['fonts'] as $filename ) {
				if ( $preload_count >= 3 ) {
					break;
				}
				$font_url = self::get_fonts_url() . '/' . $filename;
				printf(
					'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>%s',
					esc_url( $font_url ),
					"\n"
				);
				$preload_count++;
			}
		}
	}

	/**
	 * Intercept Google Fonts URLs and return empty to prevent loading.
	 *
	 * @param string $src    Style source URL.
	 * @param string $handle Style handle.
	 *
	 * @return string
	 */
	public static function intercept_google_fonts( $src, $handle ) {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return $src;
		}

		// Check per-page override.
		if ( I365_PO_Meta_Box::is_override_active( 'disable_local_fonts' ) ) {
			return $src;
		}

		// Check if we have cached local fonts.
		$cached = self::get_cached_fonts();
		if ( empty( $cached ) ) {
			return $src;
		}

		// If this is a Google Fonts URL, return false to prevent loading.
		if ( self::is_google_fonts_url( $src ) ) {
			return false;
		}

		return $src;
	}

	/**
	 * Dequeue Google Fonts styles when local fonts are active.
	 *
	 * @return void
	 */
	public static function dequeue_google_fonts() {
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return;
		}

		// Check per-page override.
		if ( I365_PO_Meta_Box::is_override_active( 'disable_local_fonts' ) ) {
			return;
		}

		$cached = self::get_cached_fonts();
		if ( empty( $cached ) ) {
			return;
		}

		global $wp_styles;

		if ( ! isset( $wp_styles ) || ! is_object( $wp_styles ) ) {
			return;
		}

		foreach ( $wp_styles->queue as $handle ) {
			if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
				continue;
			}

			$src = $wp_styles->registered[ $handle ]->src;

			if ( self::is_google_fonts_url( $src ) ) {
				wp_dequeue_style( $handle );
			}
		}
	}

	/**
	 * Get information about downloaded fonts.
	 *
	 * @return array
	 */
	public static function get_fonts_info() {
		$cached = self::get_cached_fonts();
		$fonts_dir = self::get_fonts_dir();

		$info = array(
			'enabled'      => ! empty( I365_PO_Plugin::get_settings()['local_fonts_enabled'] ),
			'has_fonts'    => ! empty( $cached ),
			'font_count'   => 0,
			'downloaded'   => null,
			'source_url'   => '',
			'disk_usage'   => 0,
			'css_exists'   => false,
		);

		if ( ! empty( $cached ) ) {
			$info['font_count']  = $cached['font_count'] ?? count( $cached['fonts'] ?? array() );
			$info['downloaded']  = $cached['downloaded'] ?? null;
			$info['source_url']  = $cached['source_url'] ?? '';
			$info['css_exists']  = ! empty( $cached['css_file'] ) && file_exists( $cached['css_file'] );
		}

		// Calculate disk usage.
		if ( file_exists( $fonts_dir ) ) {
			$info['disk_usage'] = self::get_directory_size( $fonts_dir );
		}

		return $info;
	}

	/**
	 * Get directory size in bytes.
	 *
	 * @param string $dir Directory path.
	 *
	 * @return int Size in bytes.
	 */
	private static function get_directory_size( $dir ) {
		$size = 0;

		if ( ! is_dir( $dir ) ) {
			return $size;
		}

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $file ) {
			$size += $file->getSize();
		}

		return $size;
	}

	/**
	 * Clear all downloaded fonts.
	 *
	 * @return bool
	 */
	public static function clear_fonts() {
		$fonts_dir = self::get_fonts_dir();

		// Delete all files in the directory.
		if ( is_dir( $fonts_dir ) ) {
			$files = glob( $fonts_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink( $file );
				}
			}
		}

		// Clear cache.
		return self::clear_cached_fonts();
	}

	/**
	 * AJAX: Download fonts from detected Google Fonts URL.
	 *
	 * @return void
	 */
	public static function ajax_download_fonts() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( empty( $url ) ) {
			// Try to detect from current settings or scan homepage.
			$detected_url = self::detect_fonts_url_from_homepage();
			if ( empty( $detected_url ) ) {
				wp_send_json_error( array( 'message' => __( 'No Google Fonts URL provided or detected.', '365i-performance-optimizer' ) ) );
			}
			$url = $detected_url;
		}

		if ( ! self::is_google_fonts_url( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Google Fonts URL.', '365i-performance-optimizer' ) ) );
		}

		$result = self::download_fonts( $url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => sprintf(
				/* translators: %d: number of fonts downloaded */
				__( 'Successfully downloaded %d font files.', '365i-performance-optimizer' ),
				$result['font_count']
			),
			'fonts_info' => self::get_fonts_info(),
		) );
	}

	/**
	 * Detect Google Fonts URL from homepage.
	 *
	 * @return string|false URL or false if not found.
	 */
	private static function detect_fonts_url_from_homepage() {
		$response = wp_remote_get(
			home_url(),
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		// Look for Google Fonts CSS links.
		if ( preg_match( '/href=["\']([^"\']*fonts\.googleapis\.com\/css2?\?[^"\']+)["\']/i', $body, $matches ) ) {
			return html_entity_decode( $matches[1] );
		}

		return false;
	}

	/**
	 * AJAX: Clear downloaded fonts.
	 *
	 * @return void
	 */
	public static function ajax_clear_fonts() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$result = self::clear_fonts();

		if ( $result ) {
			wp_send_json_success( array(
				'message'    => __( 'Local fonts cleared successfully.', '365i-performance-optimizer' ),
				'fonts_info' => self::get_fonts_info(),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to clear local fonts.', '365i-performance-optimizer' ) ) );
		}
	}
}
