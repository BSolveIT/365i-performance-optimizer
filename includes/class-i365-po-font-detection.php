<?php
/**
 * Font detection strategies for Google Fonts discovery.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I365_PO_Font_Detection {

	/**
	 * Detect Google Fonts URL using multi-strategy approach.
	 *
	 * Tries 6 strategies in order of increasing cost, stopping at first success.
	 *
	 * @return array {
	 *     @type string|false $url      Google Fonts CSS URL or false.
	 *     @type array        $log      Detection log entries.
	 *     @type string       $strategy Name of the strategy that succeeded.
	 * }
	 */
	public static function detect_fonts_url() {
		$log  = array();
		$html = self::fetch_homepage_html( $log );

		if ( false !== $html ) {
			// Strategy 1: Direct <link href="fonts.googleapis.com..."> match.
			$url = self::strategy_direct_link( $html, $log );
			if ( $url ) {
				return array( 'url' => $url, 'log' => $log, 'strategy' => 'direct_link' );
			}

			// Strategy 2: @import url(fonts.googleapis.com...) match.
			$url = self::strategy_import_match( $html, $log );
			if ( $url ) {
				return array( 'url' => $url, 'log' => $log, 'strategy' => 'import' );
			}

			// Strategy 3: Broad URL match anywhere in HTML.
			$url = self::strategy_broad_url( $html, $log );
			if ( $url ) {
				return array( 'url' => $url, 'log' => $log, 'strategy' => 'broad_url' );
			}

			// Strategy 4: CSS font-family detection + URL construction.
			$url = self::strategy_css_family( $html, $log );
			if ( $url ) {
				return array( 'url' => $url, 'log' => $log, 'strategy' => 'css_family' );
			}
		}

		// Strategy 5: theme.json scanning (no homepage HTML needed).
		$url = self::strategy_theme_json( $log );
		if ( $url ) {
			return array( 'url' => $url, 'log' => $log, 'strategy' => 'theme_json' );
		}

		// Strategy 6: Theme file scanning.
		$url = self::strategy_theme_files( $log );
		if ( $url ) {
			return array( 'url' => $url, 'log' => $log, 'strategy' => 'theme_files' );
		}

		$log[] = 'All 6 strategies exhausted. No Google Fonts detected.';
		return array( 'url' => false, 'log' => $log, 'strategy' => '' );
	}

	/**
	 * Fetch homepage HTML for font detection.
	 *
	 * @param array $log Detection log (passed by reference).
	 * @return string|false HTML body or false on failure.
	 */
	private static function fetch_homepage_html( &$log ) {
		$log[] = 'Fetching homepage: ' . home_url();

		$response = wp_remote_get(
			home_url(),
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) ) {
			$log[] = 'ERROR: Homepage fetch failed: ' . $response->get_error_message();
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			$log[] = 'ERROR: Homepage returned HTTP ' . $code;
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			$log[] = 'ERROR: Homepage returned empty body.';
			return false;
		}

		$log[] = 'Homepage fetched OK (' . size_format( strlen( $body ) ) . ').';
		return $body;
	}

	/**
	 * Strategy 1: Match <link href="fonts.googleapis.com/css..."> directly.
	 *
	 * @param string $html Homepage HTML.
	 * @param array  $log  Detection log (by reference).
	 * @return string|false Google Fonts URL or false.
	 */
	private static function strategy_direct_link( $html, &$log ) {
		$log[] = '[Strategy 1: Direct Link] Scanning for <link> with Google Fonts href...';

		if ( preg_match( '/href=["\']([^"\']*fonts\.googleapis\.com\/css2?\?[^"\']+)["\']/i', $html, $matches ) ) {
			$url = html_entity_decode( $matches[1] );
			$log[] = '[Strategy 1] FOUND: ' . $url;
			return $url;
		}

		$log[] = '[Strategy 1] No <link> tag with Google Fonts found.';
		return false;
	}

	/**
	 * Strategy 2: Match @import url(fonts.googleapis.com/css...) in HTML.
	 *
	 * @param string $html Homepage HTML.
	 * @param array  $log  Detection log (by reference).
	 * @return string|false Google Fonts URL or false.
	 */
	private static function strategy_import_match( $html, &$log ) {
		$log[] = '[Strategy 2: @import] Scanning for @import with Google Fonts...';

		if ( preg_match( '/@import\s+(?:url\s*\(\s*)?["\']?(https?:\/\/fonts\.googleapis\.com\/css2?\?[^"\')\s]+)["\']?\s*\)?/i', $html, $matches ) ) {
			$url = html_entity_decode( $matches[1] );
			$log[] = '[Strategy 2] FOUND: ' . $url;
			return $url;
		}

		// Protocol-relative.
		if ( preg_match( '/@import\s+(?:url\s*\(\s*)?["\']?(\/\/fonts\.googleapis\.com\/css2?\?[^"\')\s]+)["\']?\s*\)?/i', $html, $matches ) ) {
			$url = 'https:' . html_entity_decode( $matches[1] );
			$log[] = '[Strategy 2] FOUND (protocol-relative): ' . $url;
			return $url;
		}

		$log[] = '[Strategy 2] No @import rule found.';
		return false;
	}

	/**
	 * Strategy 3: Find any fonts.googleapis.com/css URL anywhere in HTML.
	 *
	 * Catches URLs in comments, data attributes, JavaScript variables, etc.
	 *
	 * @param string $html Homepage HTML.
	 * @param array  $log  Detection log (by reference).
	 * @return string|false Google Fonts URL or false.
	 */
	private static function strategy_broad_url( $html, &$log ) {
		$log[] = '[Strategy 3: Broad URL] Scanning entire HTML for any Google Fonts CSS URL...';

		if ( preg_match_all( '/(https?:\/\/fonts\.googleapis\.com\/css2?\?[^\s"\'<>)\]]+)/i', $html, $matches ) ) {
			$urls = array_unique( $matches[1] );
			foreach ( $urls as $url ) {
				$url = html_entity_decode( $url );
				if ( false !== strpos( $url, 'family=' ) ) {
					$log[] = '[Strategy 3] FOUND: ' . $url;
					return $url;
				}
			}
		}

		$log[] = '[Strategy 3] No Google Fonts URL found anywhere in HTML.';
		return false;
	}

	/**
	 * Strategy 4: Parse CSS font-family declarations, construct Google Fonts URL, validate.
	 *
	 * This is the key strategy for sites where other plugins have removed/replaced
	 * Google Fonts URLs. The CSS still declares font-family names, which we can use
	 * to construct a fresh Google Fonts URL.
	 *
	 * @param string $html Homepage HTML.
	 * @param array  $log  Detection log (by reference).
	 * @return string|false Google Fonts URL or false.
	 */
	private static function strategy_css_family( $html, &$log ) {
		$log[] = '[Strategy 4: CSS Family] Parsing page CSS for font-family declarations...';

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->loadHTML( $html );
		libxml_clear_errors();

		$families = self::extract_font_families_from_dom( $dom, $log );

		if ( empty( $families ) ) {
			$log[] = '[Strategy 4] No candidate font families found in CSS.';
			return false;
		}

		$log[] = '[Strategy 4] Candidates: ' . implode( ', ', $families );

		$url = self::construct_google_fonts_url( $families, $log );

		if ( $url ) {
			$log[] = '[Strategy 4] FOUND: ' . $url;
			return $url;
		}

		$log[] = '[Strategy 4] No candidates validated as Google Fonts.';
		return false;
	}

	/**
	 * Strategy 5: Scan theme.json for font families that might be Google Fonts.
	 *
	 * @param array $log Detection log (by reference).
	 * @return string|false Google Fonts URL or false.
	 */
	private static function strategy_theme_json( &$log ) {
		$log[] = '[Strategy 5: theme.json] Checking active theme...';

		$paths = array_unique( array(
			get_stylesheet_directory() . '/theme.json',
			get_template_directory() . '/theme.json',
		) );

		$generic  = self::get_generic_families();
		$families = array();

		foreach ( $paths as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$log[] = '[Strategy 5] Reading: ' . wp_basename( dirname( $path ) ) . '/theme.json';

			$data = wp_json_file_decode( $path, array( 'associative' => true ) );
			if ( empty( $data ) ) {
				$log[] = '[Strategy 5] Invalid JSON.';
				continue;
			}

			// Collect font entries from settings.typography.fontFamilies.
			$font_entries = array();
			if ( ! empty( $data['settings']['typography']['fontFamilies'] ) ) {
				$ff = $data['settings']['typography']['fontFamilies'];
				if ( isset( $ff[0] ) ) {
					$font_entries = $ff;
				} else {
					foreach ( $ff as $entries ) {
						if ( is_array( $entries ) ) {
							$font_entries = array_merge( $font_entries, $entries );
						}
					}
				}
			}

			foreach ( $font_entries as $entry ) {
				// Check for direct Google Fonts src references.
				if ( ! empty( $entry['fontFace'] ) ) {
					foreach ( $entry['fontFace'] as $face ) {
						if ( ! empty( $face['src'] ) ) {
							$src = is_array( $face['src'] ) ? $face['src'][0] : $face['src'];
							if ( false !== strpos( $src, 'fonts.googleapis.com' ) ) {
								$log[] = '[Strategy 5] FOUND direct Google Fonts URL in theme.json';
								return $src;
							}
						}
					}
				}

				// Extract the family name.
				$font_family = isset( $entry['fontFamily'] ) ? $entry['fontFamily'] : ( isset( $entry['name'] ) ? $entry['name'] : '' );
				if ( empty( $font_family ) ) {
					continue;
				}
				$parts  = explode( ',', $font_family );
				$family = trim( $parts[0], "\"' \t\r\n" );
				if ( empty( $family ) || in_array( strtolower( $family ), $generic, true ) ) {
					continue;
				}
				if ( ! in_array( $family, $families, true ) ) {
					$families[] = $family;
					$log[]      = '[Strategy 5] Candidate: "' . $family . '"';
				}
			}
		}

		if ( empty( $families ) ) {
			$log[] = '[Strategy 5] No font families found in theme.json.';
			return false;
		}

		$url = self::construct_google_fonts_url( $families, $log );
		if ( $url ) {
			$log[] = '[Strategy 5] FOUND: ' . $url;
			return $url;
		}

		$log[] = '[Strategy 5] No families validated as Google Fonts.';
		return false;
	}

	/**
	 * Strategy 6: Search theme style.css and functions.php for Google Fonts URLs.
	 *
	 * @param array $log Detection log (by reference).
	 * @return string|false Google Fonts URL or false.
	 */
	private static function strategy_theme_files( &$log ) {
		$log[] = '[Strategy 6: Theme Files] Scanning theme source files...';

		$files = array_unique( array(
			get_stylesheet_directory() . '/style.css',
			get_stylesheet_directory() . '/functions.php',
			get_template_directory() . '/style.css',
			get_template_directory() . '/functions.php',
		) );

		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				continue;
			}

			$fp = fopen( $file, 'r' );
			if ( ! $fp ) {
				continue;
			}
			$contents = fread( $fp, filesize( $file ) );
			fclose( $fp );

			if ( empty( $contents ) ) {
				continue;
			}

			if ( preg_match( '/(https?:\/\/fonts\.googleapis\.com\/css2?\?[^\s"\'<>)\]]+)/i', $contents, $matches ) ) {
				$url = $matches[1];
				if ( false !== strpos( $url, 'family=' ) ) {
					$log[] = '[Strategy 6] FOUND in ' . wp_basename( $file ) . ': ' . $url;
					return $url;
				}
			}
		}

		$log[] = '[Strategy 6] No Google Fonts URLs found in theme files.';
		return false;
	}

	/**
	 * Get list of generic CSS font families to exclude from detection.
	 *
	 * @return array Lowercase family names.
	 */
	private static function get_generic_families() {
		return array(
			'sans-serif', 'serif', 'monospace', 'cursive', 'fantasy',
			'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'ui-rounded',
			'math', 'emoji', 'fangsong',
			'-apple-system', 'blinkmacsystemfont',
			'inherit', 'initial', 'unset', 'revert', 'revert-layer',
		);
	}

	/**
	 * Extract font family names from DOM style blocks and linked stylesheets.
	 *
	 * @param \DOMDocument $dom DOM document.
	 * @param array        $log Detection log (by reference).
	 * @return array Candidate family names.
	 */
	private static function extract_font_families_from_dom( \DOMDocument $dom, &$log ) {
		$styles  = $dom->getElementsByTagName( 'style' );
		$links   = $dom->getElementsByTagName( 'link' );
		$generic = self::get_generic_families();
		$found   = array();

		$priority_selectors = array(
			'body', 'html', ':root', 'h1', 'h2', 'h3', 'p',
			'.entry-title', '.site-title', '.elementor-heading-title',
			'.wp-block-heading', '.has-global-padding',
		);

		// Scan inline <style> blocks for font-family declarations.
		foreach ( $styles as $style ) {
			$css = $style->textContent;
			foreach ( $priority_selectors as $selector ) {
				$pattern = '/' . preg_quote( $selector, '/' ) . '\s*\{[^}]*font-family\s*:\s*([^;}]+)[;}]/i';
				if ( preg_match( $pattern, $css, $m ) ) {
					$parts = explode( ',', $m[1] );
					foreach ( $parts as $part ) {
						$family = trim( $part, "\"' \t\r\n" );
						if ( empty( $family ) || in_array( strtolower( $family ), $generic, true ) ) {
							continue;
						}
						if ( ! in_array( $family, $found, true ) ) {
							$found[] = $family;
							$log[]   = '[Strategy 4] Found "' . $family . '" on selector "' . $selector . '"';
						}
					}
				}
			}
		}

		// Scan @font-face declarations for family names.
		foreach ( $styles as $style ) {
			$css = $style->textContent;
			if ( preg_match_all( '/@font-face\s*\{[^}]*font-family\s*:\s*["\']?([^"\';}]+)["\']?/i', $css, $matches ) ) {
				foreach ( $matches[1] as $family ) {
					$family = trim( $family );
					if ( ! empty( $family ) && ! in_array( strtolower( $family ), $generic, true ) && ! in_array( $family, $found, true ) ) {
						$found[] = $family;
						$log[]   = '[Strategy 4] Found "' . $family . '" in @font-face';
					}
				}
			}
		}

		// Check <link> tags for local font plugin CSS (OMGF, Elementor google-fonts).
		foreach ( $links as $link ) {
			$href = $link->getAttribute( 'href' );
			if ( empty( $href ) ) {
				continue;
			}
			if ( false !== strpos( $href, 'google-fonts' ) || false !== strpos( $href, 'omgf' ) ) {
				$log[] = '[Strategy 4] Detected local fonts CSS: ' . $href;
				$ext_families = self::extract_families_from_external_css( $href, $log );

				// Fallback: extract font name from URL pattern (e.g., /google-fonts/css/poppins.css).
				if ( empty( $ext_families ) && preg_match( '/google-fonts\/css\/([a-z0-9_-]+)\.css/i', $href, $url_match ) ) {
					$family = str_replace( array( '-', '_' ), ' ', $url_match[1] );
					$family = ucwords( $family );
					$ext_families = array( $family );
					$log[] = '[Strategy 4] Extracted "' . $family . '" from URL pattern';
				}

				// Fallback: extract font name from OMGF URL pattern (e.g., /omgf/fonts/open-sans/).
				if ( empty( $ext_families ) && preg_match( '/omgf\/[^\/]*\/([a-z0-9_-]+)/i', $href, $url_match ) ) {
					$family = str_replace( array( '-', '_' ), ' ', $url_match[1] );
					$family = ucwords( $family );
					$ext_families = array( $family );
					$log[] = '[Strategy 4] Extracted "' . $family . '" from OMGF URL pattern';
				}

				foreach ( $ext_families as $family ) {
					if ( ! in_array( $family, $found, true ) ) {
						$found[] = $family;
					}
				}
			}
		}

		return $found;
	}

	/**
	 * Fetch an external stylesheet and extract @font-face family names.
	 *
	 * @param string $url CSS URL.
	 * @param array  $log Detection log (by reference).
	 * @return array Family names found.
	 */
	private static function extract_families_from_external_css( $url, &$log ) {
		// Make relative URLs absolute so wp_remote_get() can fetch them.
		if ( 0 !== strpos( $url, 'http' ) ) {
			$url = home_url( '/' ) . ltrim( $url, '/' );
			$log[] = '[Strategy 4] Resolved to absolute URL: ' . $url;
		}

		$response = wp_remote_get( $url, array( 'timeout' => 8 ) );
		if ( is_wp_error( $response ) ) {
			$log[] = '[Strategy 4] Failed to fetch CSS: ' . $response->get_error_message();
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$log[] = '[Strategy 4] CSS fetch returned HTTP ' . $code;
			return array();
		}

		$css = wp_remote_retrieve_body( $response );
		if ( empty( $css ) ) {
			$log[] = '[Strategy 4] CSS file was empty.';
			return array();
		}

		$families = array();
		$generic  = self::get_generic_families();

		if ( preg_match_all( '/@font-face\s*\{[^}]*font-family\s*:\s*["\']?([^"\';}]+)["\']?/i', $css, $matches ) ) {
			foreach ( $matches[1] as $family ) {
				$family = trim( $family );
				if ( ! empty( $family ) && ! in_array( strtolower( $family ), $generic, true ) && ! in_array( $family, $families, true ) ) {
					$families[] = $family;
					$log[]      = '[Strategy 4] Found "' . $family . '" in external CSS';
				}
			}
		}

		return $families;
	}

	/**
	 * Construct a Google Fonts CSS2 URL from family names and validate.
	 *
	 * Tries all families combined first (1 request). If that fails, tests each
	 * family individually to find which ones are valid Google Fonts.
	 *
	 * @param array $families Candidate family names.
	 * @param array $log      Detection log (by reference).
	 * @return string|false Valid Google Fonts URL or false.
	 */
	private static function construct_google_fonts_url( $families, &$log ) {
		// Try all families combined first.
		$url = self::build_css2_url( $families );
		$log[] = 'Validating combined URL with weights (' . count( $families ) . ' families)...';
		if ( self::validate_google_fonts_url( $url, $log ) ) {
			return $url;
		}

		// Try without weight range (simpler URL, wider compatibility).
		$url_simple = self::build_css2_url( $families, false );
		$log[]      = 'Trying without weight range...';
		if ( self::validate_google_fonts_url( $url_simple, $log ) ) {
			return $url_simple;
		}

		$log[] = 'Combined URLs failed. Testing families individually...';

		// Test each family individually (with weights first, then without).
		$valid = array();
		$use_weights = true;
		foreach ( $families as $family ) {
			$single_url = self::build_css2_url( array( $family ) );
			if ( self::validate_google_fonts_url( $single_url, $log ) ) {
				$valid[] = $family;
				$log[]   = '"' . $family . '" is a valid Google Font.';
				continue;
			}
			// Try without weight range.
			$single_simple = self::build_css2_url( array( $family ), false );
			if ( self::validate_google_fonts_url( $single_simple, $log ) ) {
				$valid[]     = $family;
				$use_weights = false;
				$log[]       = '"' . $family . '" is valid (without weight range).';
			} else {
				$log[] = '"' . $family . '" is NOT on Google Fonts (skipped).';
			}
		}

		if ( empty( $valid ) ) {
			return false;
		}

		return self::build_css2_url( $valid, $use_weights );
	}

	/**
	 * Build a Google Fonts CSS2 URL from family names.
	 *
	 * @param array $families     Family names.
	 * @param bool  $with_weights Whether to include weight ranges.
	 * @return string URL.
	 */
	private static function build_css2_url( $families, $with_weights = true ) {
		$params = array();
		foreach ( $families as $family ) {
			$encoded  = rawurlencode( $family );
			$params[] = $with_weights
				? 'family=' . $encoded . ':wght@100;200;300;400;500;600;700;800;900'
				: 'family=' . $encoded;
		}
		$params[] = 'display=swap';
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $params );
	}

	/**
	 * Validate a Google Fonts URL by sending a GET request.
	 *
	 * @param string $url URL to validate.
	 * @param array  $log Detection log (by reference).
	 * @return bool True if valid (HTTP 200).
	 */
	private static function validate_google_fonts_url( $url, &$log = null ) {
		// Use GET instead of HEAD — Google Fonts CSS2 may not support HEAD requests.
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( null !== $log ) {
				$log[] = 'Validation error: ' . $response->get_error_message();
			}
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( null !== $log && 200 !== $code ) {
			$log[] = 'Validation returned HTTP ' . $code . ' for: ' . $url;
		}

		return 200 === $code;
	}
}
