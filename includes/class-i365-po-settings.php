<?php
/**
 * Admin Settings Page.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I365_PO_Settings {
	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'load-settings_page_i365-po-settings', array( __CLASS__, 'add_help_tab' ) );
		add_action( 'wp_ajax_i365_po_detect', array( __CLASS__, 'ajax_detect' ) );
	}

	/**
	 * Add submenu under Settings.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_options_page(
			__( 'Performance Optimizer', '365i-performance-optimizer' ),
			__( 'Performance Optimizer', '365i-performance-optimizer' ),
			'manage_options',
			'i365-po-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			'i365_po_settings_group',
			I365_PO_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'I365_PO_Plugin', 'sanitize_settings' ),
				'default'           => I365_PO_Plugin::defaults(),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current page hook.
	 *
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_i365-po-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'i365-po-admin',
			plugin_dir_url( I365_PO_PLUGIN_FILE ) . 'assets/admin.css',
			array(),
			I365_PO_VERSION
		);

		wp_enqueue_script(
			'i365-po-admin',
			plugin_dir_url( I365_PO_PLUGIN_FILE ) . 'assets/admin.js',
			array(),
			I365_PO_VERSION,
			true
		);

		$hero_guess = '';
		$hero_path  = get_theme_file_path( 'assets/images/hero.webp' );
		if ( $hero_path && file_exists( $hero_path ) ) {
			$hero_guess = get_theme_file_uri( 'assets/images/hero.webp' );
		}

		$font_guess = self::guess_font_url();

		wp_localize_script(
			'i365-po-admin',
			'I365PODetect',
			array(
				'home'       => home_url(),
				'stylesheet' => get_stylesheet_uri(),
				'hero'       => $hero_guess,
				'font'       => $font_guess,
				'fontsCdn'   => 'https://fonts.gstatic.com',
				'nonce'      => wp_create_nonce( 'i365-po-detect' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'messages'   => array(
					'running'  => __( 'Detecting from homepage…', '365i-performance-optimizer' ),
					'failed'   => __( 'Detection failed. Check your homepage is reachable.', '365i-performance-optimizer' ),
					'completed'=> __( 'Detection updated fields.', '365i-performance-optimizer' ),
				),
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = I365_PO_Plugin::get_settings();
		?>
		<div class="wrap i365-po-wrap">
			<h1><?php esc_html_e( 'Performance Optimizer', '365i-performance-optimizer' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Tune WordPress 6.9 delivery with safe, toggleable tweaks. Elementor-safe and front-end aware.', '365i-performance-optimizer' ); ?></p>

			<form action="options.php" method="post" class="i365-po-form">
				<?php
					settings_fields( 'i365_po_settings_group' );
					do_settings_sections( 'i365_po_settings_group' );
				?>

				<div class="i365-po-grid">
					<section class="i365-po-card">
						<header>
							<h2><?php esc_html_e( 'Speculative Loading', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Uses WordPress Speculation Rules API to prefetch likely next pages. Skips admin and Elementor edit/preview. Recommended: keep on for faster navigation; choose Eager unless you see server strain, then try Moderate.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Boost navigation by hinting which pages to pre-load. Skips admin and Elementor contexts.', '365i-performance-optimizer' ); ?></p>
						</header>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[enable_speculation]" value="1" <?php checked( $settings['enable_speculation'] ); ?> />
							<span>
								<?php esc_html_e( 'Enable speculative loading', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Turns on speculation rules to prefetch navigation targets. Safe on most hosts; disable if your server is extremely resource-limited.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-field">
							<span>
								<?php esc_html_e( 'Eagerness', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Controls how aggressively to prefetch links. Eager: most aggressive. Moderate: balanced. Conservative: only obvious navigations.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
							<select name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[speculation_eagerness]">
								<?php
								$options = array(
									'eager'        => __( 'Eager (default)', '365i-performance-optimizer' ),
									'moderate'     => __( 'Moderate', '365i-performance-optimizer' ),
									'conservative' => __( 'Conservative', '365i-performance-optimizer' ),
								);
								foreach ( $options as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['speculation_eagerness'], $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php
								endforeach;
								?>
							</select>
							<div class="i365-po-note">
								<strong><?php esc_html_e( 'How it works:', '365i-performance-optimizer' ); ?></strong>
								<ul>
									<li><strong><?php esc_html_e( 'Eager:', '365i-performance-optimizer' ); ?></strong> <?php esc_html_e( 'Prefetches most likely links quickly. Best for fast hosting and snappy navigation.', '365i-performance-optimizer' ); ?></li>
									<li><strong><?php esc_html_e( 'Moderate:', '365i-performance-optimizer' ); ?></strong> <?php esc_html_e( 'Balanced prefetching for typical sites.', '365i-performance-optimizer' ); ?></li>
									<li><strong><?php esc_html_e( 'Conservative:', '365i-performance-optimizer' ); ?></strong> <?php esc_html_e( 'Prefetches only obvious next links. Use if your server is resource-limited.', '365i-performance-optimizer' ); ?></li>
								</ul>
							</div>
							<label class="i365-po-field">
								<span>
									<?php esc_html_e( 'Exclude paths from prefetching (one per line)', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Avoid prefetching sensitive flows like carts or checkout. Example: /cart, /checkout.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
								<textarea name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[speculation_exclusions]" rows="2" placeholder="/cart&#10;/checkout"><?php echo esc_textarea( implode( "\n", $settings['speculation_exclusions'] ) ); ?></textarea>
							</label>
						</label>
					</section>

					<section class="i365-po-card">
						<header>
							<h2><?php esc_html_e( 'Preconnect & Preload', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Adds preconnect for your domain and common font CDN, and preloads your main stylesheet, primary font, and an optional hero image. Recommended: keep on and point URLs to your active theme assets.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Prime DNS, fonts, and critical assets on the front end.', '365i-performance-optimizer' ); ?></p>
							<button type="button" class="button button-secondary i365-po-detect" id="i365-po-detect">
								<?php esc_html_e( 'Auto-detect from theme', '365i-performance-optimizer' ); ?>
							</button>
							<div id="i365-po-toast" class="i365-po-toast" role="status" aria-live="polite">
								<button type="button" class="i365-po-toast__close" aria-label="<?php esc_attr_e( 'Dismiss notice', '365i-performance-optimizer' ); ?>">x</button>
								<span class="i365-po-toast__text"></span>
							</div>
							<label class="i365-po-toggle">
								<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[enable_detect_log]" value="1" <?php checked( $settings['enable_detect_log'] ); ?> />
								<span>
									<?php esc_html_e( 'Show detection debug log', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'If enabled, each auto-detect run will display a log of what was filled.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
							</label>
							<div class="i365-po-detect-log" id="i365-po-detect-log" aria-live="polite"></div>
						</header>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[enable_preload]" value="1" <?php checked( $settings['enable_preload'] ); ?> />
							<span>
								<?php esc_html_e( 'Enable preconnect & preload', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Turns on all preconnect and preload hints. Disable if you encounter rare CDN/CORS issues.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>

						<label class="i365-po-field">
							<span>
								<?php esc_html_e( 'Preconnect hosts (one per line or comma separated)', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Domains to preconnect for faster TLS/DNS. Examples: your site URL and font CDN like https://fonts.gstatic.com. Keep it short to avoid bloat.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
							<textarea name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[preconnect_hosts]" rows="3" placeholder="https://fonts.gstatic.com"><?php echo esc_textarea( implode( "\n", $settings['preconnect_hosts'] ) ); ?></textarea>
						</label>

						<label class="i365-po-field">
							<span>
								<?php esc_html_e( 'Stylesheet preload URL', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Full URL to your main theme stylesheet. Preload improves render start. Example: https://yoursite.com/wp-content/themes/your-theme/style.css', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
							<input type="url" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[preload_stylesheet]" value="<?php echo esc_attr( $settings['preload_stylesheet'] ); ?>" placeholder="<?php echo esc_attr( get_stylesheet_directory_uri() . '/style.css' ); ?>" />
						</label>

						<label class="i365-po-field">
							<span>
								<?php esc_html_e( 'Font preload URL', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Primary font file (woff2) used above the fold. Example: https://yoursite.com/wp-content/themes/your-theme/fonts/your-font.woff2', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
							<input type="url" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[preload_font]" value="<?php echo esc_attr( $settings['preload_font'] ); ?>" placeholder="<?php echo esc_attr( get_stylesheet_directory_uri() . '/fonts/inter-var.woff2' ); ?>" />
							<div id="i365-po-font-suggestions" class="i365-po-suggestions" aria-live="polite"></div>
						</label>

						<label class="i365-po-field">
							<span>
								<?php esc_html_e( 'Hero image preload URL', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Largest above-the-fold hero image. Preloading can improve LCP. Example: https://yoursite.com/wp-content/themes/your-theme/assets/images/hero.webp', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
							<input type="url" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[preload_hero]" value="<?php echo esc_attr( $settings['preload_hero'] ); ?>" placeholder="<?php echo esc_attr( get_theme_file_uri( 'assets/images/hero.webp' ) ); ?>" />
							<label class="i365-po-toggle inline">
								<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[hero_preload_on_front]" value="1" <?php checked( $settings['hero_preload_on_front'] ); ?> />
								<span>
									<?php esc_html_e( 'Front page only', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Preload the hero image only on the homepage to avoid extra requests on other pages.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
							</label>
						</label>
					</section>

					<section class="i365-po-card">
						<header>
							<h2><?php esc_html_e( 'Delivery Cleanup', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Removes emoji scripts/styles, embeds script, XML-RPC, and optional REST/oEmbed link tags on the front end. Safe defaults for most sites.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Strip extras that slow the front end. Safe for admin and Elementor.', '365i-performance-optimizer' ); ?></p>
						</header>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[remove_emoji]" value="1" <?php checked( $settings['remove_emoji'] ); ?> />
							<span>
								<?php esc_html_e( 'Remove emoji scripts/styles', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Deletes core emoji detection scripts/styles. Safe unless your site truly relies on the emoji fallback.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[disable_embeds]" value="1" <?php checked( $settings['disable_embeds'] ); ?> />
							<span>
								<?php esc_html_e( 'Disable embeds script on front end', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Deregisters wp-embed.js on the front end. Safe unless you depend on embedding other WordPress posts.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[disable_xmlrpc]" value="1" <?php checked( $settings['disable_xmlrpc'] ); ?> />
							<span>
								<?php esc_html_e( 'Disable XML-RPC', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Blocks XML-RPC endpoint. Good for security unless you need Jetpack legacy XML-RPC or remote publishing clients.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[remove_rest_link]" value="1" <?php checked( $settings['remove_rest_link'] ); ?> />
							<span>
								<?php esc_html_e( 'Remove REST API link tag (front end)', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Removes REST API discovery link tag from wp_head on the front end. Mostly cosmetic; can slightly trim HTML.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[remove_oembed_link]" value="1" <?php checked( $settings['remove_oembed_link'] ); ?> />
							<span>
								<?php esc_html_e( 'Remove oEmbed discovery links (front end)', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Removes oEmbed discovery tags from the front end. Safe unless you need external sites to auto-discover embeds for your pages.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
					</section>

					<section class="i365-po-card">
						<header>
							<h2><?php esc_html_e( 'Script Loading', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Defers non-core, non-Elementor, non-jQuery scripts on the front end to reduce render blocking. Exclusions let you opt out fragile scripts.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Defer non-core scripts while keeping Elementor, jQuery, and wp-* handles untouched.', '365i-performance-optimizer' ); ?></p>
						</header>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[defer_scripts]" value="1" <?php checked( $settings['defer_scripts'] ); ?> />
							<span>
								<?php esc_html_e( 'Defer eligible scripts on the front end', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Adds defer to scripts except Elementor, jQuery, wp-* handles, and items you exclude. Turn off if a script requires blocking execution.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-field">
							<span>
								<?php esc_html_e( 'Excluded handles (one per line or comma separated)', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Script handles to avoid deferring. Defaults: elementor, jquery, wp-. Add any handles that must stay blocking.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
							<textarea name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[excluded_defer_handles]" rows="3" placeholder="elementor, jquery, wp-"><?php echo esc_textarea( implode( "\n", $settings['excluded_defer_handles'] ) ); ?></textarea>
						</label>
					</section>

					<section class="i365-po-card">
						<header>
							<h2><?php esc_html_e( 'Images', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Fine-tune LCP priority and lazy-loading. Helpful for hero imagery and above-the-fold content.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Guide LCP and lazy-loading for a faster above-the-fold experience.', '365i-performance-optimizer' ); ?></p>
						</header>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[lcp_priority]" value="1" <?php checked( $settings['lcp_priority'] ); ?> />
							<span>
								<?php esc_html_e( 'Set fetchpriority=high when loading is not defined', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'If an image lacks loading/fetchpriority, mark it high priority to help LCP. Safe default.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[disable_front_lazy]" value="1" <?php checked( $settings['disable_front_lazy'] ); ?> />
							<span>
								<?php esc_html_e( 'Disable lazy-loading for homepage images', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Turns off lazy-loading for homepage images to avoid delaying above-the-fold content. Leave on if your homepage is very image-heavy below the fold.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
					</section>
				</div>

				<div class="i365-po-actions">
					<?php submit_button( __( 'Save performance settings', '365i-performance-optimizer' ), 'primary', 'submit', false ); ?>
					<button type="submit" name="i365_po_reset_defaults" value="1" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Restore default settings?', '365i-performance-optimizer' ) ); ?>');">
						<?php esc_html_e( 'Restore defaults', '365i-performance-optimizer' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Best-effort font URL guess from the active theme.
	 *
	 * @return string
	 */
	private static function guess_font_url() {
		$paths = array(
			get_stylesheet_directory(),
			get_template_directory(),
		);

		$patterns = array(
			'/fonts/*.woff2',
			'/assets/fonts/*.woff2',
			'/assets/*.woff2',
		);

		foreach ( $paths as $path ) {
			if ( empty( $path ) ) {
				continue;
			}
			foreach ( $patterns as $pattern ) {
				$matches = glob( wp_normalize_path( $path . $pattern ) );
				if ( ! empty( $matches ) ) {
					$file = $matches[0];

					// Map back to a URL.
					$stylesheet_dir = wp_normalize_path( get_stylesheet_directory() );
					$template_dir   = wp_normalize_path( get_template_directory() );

					if ( strpos( $file, $stylesheet_dir ) === 0 ) {
						$relative = ltrim( substr( $file, strlen( $stylesheet_dir ) ), '/' );
						return trailingslashit( get_stylesheet_directory_uri() ) . $relative;
					}

					if ( strpos( $file, $template_dir ) === 0 ) {
						$relative = ltrim( substr( $file, strlen( $template_dir ) ), '/' );
						return trailingslashit( get_template_directory_uri() ) . $relative;
					}
				}
			}
		}

		return '';
	}

	/**
	 * AJAX: detect assets from homepage HTML (no external proxy).
	 *
	 * @return void
	 */
	public static function ajax_detect() {
		check_ajax_referer( 'i365-po-detect', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$response = wp_remote_get(
			home_url(),
			array(
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code >= 400 || empty( $body ) ) {
			wp_send_json_error( array( 'message' => __( 'Homepage could not be fetched.', '365i-performance-optimizer' ) ) );
		}

		$result = array(
			'preconnect' => array_filter( array( home_url(), 'https://fonts.gstatic.com' ) ),
			'stylesheet' => '',
			'font'       => '',
			'hero'       => '',
		);

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadHTML( $body );
		libxml_clear_errors();

		if ( $loaded ) {
			$result['stylesheet'] = self::pick_best_stylesheet( $dom );
			$font_pick            = self::pick_best_font_v2( $dom );
			$result['font']       = $font_pick['primary'];
			$result['font_list']  = $font_pick['candidates'];
			$result['hero']       = self::pick_best_image( $dom );
		}

		$result['stylesheet'] = self::strip_query( $result['stylesheet'] );
		$result['font']       = self::strip_query( $result['font'] );
		$result['hero']       = self::strip_query( $result['hero'] );

		if ( ! empty( $result['stylesheet'] ) ) {
			$response_code = wp_remote_retrieve_response_code( wp_remote_head( $result['stylesheet'], array( 'timeout' => 6, 'redirection' => 1 ) ) );
			if ( $response_code >= 400 ) {
				$result['stylesheet'] = '';
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * Normalize relative URL to absolute.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private static function make_absolute_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		// Protocol-relative.
		if ( 0 === strpos( $url, '//' ) ) {
			return set_url_scheme( $url, is_ssl() ? 'https' : 'http' );
		}

		$parts = wp_parse_url( $url );
		if ( ! empty( $parts['scheme'] ) ) {
			return $url;
		}

		if ( ! empty( $parts['path'] ) && '/' === $parts['path'][0] ) {
			return home_url( $parts['path'] );
		}

		return trailingslashit( home_url() ) . ltrim( $url, '/' );
	}

	/**
	 * Remove query string for preload targets.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function strip_query( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['query'] ) ) {
			return $url;
		}

		$queryless = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$queryless .= ':' . $parts['port'];
		}
		if ( ! empty( $parts['path'] ) ) {
			$queryless .= $parts['path'];
		}

		return $queryless;
	}

	/**
	 * Choose the most likely primary stylesheet.
	 *
	 * @param \DOMDocument $dom DOM.
	 * @return string
	 */
	private static function pick_best_stylesheet( \DOMDocument $dom ) {
		$links  = $dom->getElementsByTagName( 'link' );
		$best   = '';
		$best_s = -INF;
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( $links as $idx => $link ) {
			$rel  = strtolower( $link->getAttribute( 'rel' ) );
			$href = $link->getAttribute( 'href' );
			if ( false === strpos( $rel, 'stylesheet' ) ) {
				continue;
			}
			if ( empty( $href ) ) {
				continue;
			}
			$url   = self::make_absolute_url( $href );
			$score = 0 - $idx; // earlier is slightly preferred.

			$target_host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $target_host && $host && $target_host === $host ) {
				$score += 20;
			}
			if ( strpos( $url, 'dashicons' ) !== false || strpos( $url, 'emoji' ) !== false ) {
				$score -= 50;
			}
			if ( strpos( $url, 'googleapis' ) !== false || strpos( $url, 'gstatic' ) !== false ) {
				$score -= 10;
			}
			if ( $score > $best_s ) {
				$best_s = $score;
				$best   = $url;
			}
		}

		return $best;
	}

	/**
	 * Choose the most likely primary font preload or font file.
	 *
	 * @param \DOMDocument $dom DOM.
	 * @return string
	 */
	private static function pick_best_font( \DOMDocument $dom ) {
		$links        = $dom->getElementsByTagName( 'link' );
		$best         = '';
		$best_s       = -INF;
		$host         = wp_parse_url( home_url(), PHP_URL_HOST );
		$google_fonts = '';
		$candidates   = array();

		foreach ( $links as $idx => $link ) {
			$rel  = strtolower( $link->getAttribute( 'rel' ) );
			$href = $link->getAttribute( 'href' );
			$as   = strtolower( $link->getAttribute( 'as' ) );
			if ( empty( $href ) ) {
				continue;
			}
			$url = self::make_absolute_url( $href );

			// Track Google Fonts stylesheet as fallback (remote or local Elementor cache).
			if ( empty( $google_fonts ) && ( false !== strpos( $url, 'fonts.googleapis.com' ) || false !== strpos( $url, 'google-fonts/css' ) ) ) {
				$google_fonts = $url;
			}

			if ( false === stripos( $rel, 'preload' ) && ! preg_match( '/\\.woff2?$/i', $url ) ) {
				continue;
			}
			if ( stripos( $rel, 'preload' ) !== false && 'font' !== $as ) {
				continue;
			}

			$score = 0 - $idx;
			if ( preg_match( '/\\.woff2$/i', $url ) ) {
				$score += 20;
			} elseif ( preg_match( '/\\.woff$/i', $url ) ) {
				$score += 10;
			}
			$target_host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $target_host ) {
				// Only allow same-host or fonts.gstatic.com.
				if ( $host && $target_host === $host ) {
					$score += 10;
				} elseif ( false !== strpos( $target_host, 'fonts.gstatic.com' ) ) {
					$score += 15;
				} else {
					continue;
				}
			}

			// Avoid icon fonts.
			if ( strpos( $url, 'font-awesome' ) !== false || strpos( $url, 'icomoon' ) !== false || strpos( $url, 'icon' ) !== false ) {
				$score -= 50;
			}

			if ( $score > $best_s ) {
				$best_s = $score;
				$best   = $url;
			}

			$candidates[] = $url;
		}

		// Keep only same-host or gstatic candidates.
		$candidates = array_values(
			array_unique(
				array_filter(
					$candidates,
					function ( $url ) use ( $host ) {
						$th = wp_parse_url( $url, PHP_URL_HOST );
						return $th && ( $th === $host || false !== strpos( $th, 'fonts.gstatic.com' ) );
					}
				)
			)
		);

		// Try to fetch first WOFF2 from Google Fonts CSS (prefer variable).
		if ( $google_fonts ) {
			$gf_fonts = self::font_from_stylesheet( $google_fonts );
			if ( ! empty( $gf_fonts ) ) {
				$candidates = array_merge( $gf_fonts, $candidates );
			}
		}

		$candidates = array_values( array_unique( $candidates ) );

		return array(
			'primary'    => isset( $candidates[0] ) ? $candidates[0] : '',
			'candidates' => $candidates,
		);
	}

	/**
	 * Choose a hero-ish image: prefer hero/cover/banner keywords or larger dimensions.
	 *
	 * @param \DOMDocument $dom DOM.
	 * @return string
	 */
	private static function pick_best_image( \DOMDocument $dom ) {
		$images = $dom->getElementsByTagName( 'img' );
		$best   = '';
		$best_s = -INF;
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$keywords_positive = array( 'hero', 'cover', 'banner', 'slider', 'header', 'featured', 'main', 'masthead' );
		$keywords_negative = array( 'logo', 'icon', 'avatar', 'thumb', 'thumbnail', 'emoji' );

		foreach ( $images as $idx => $img ) {
			$src = $img->getAttribute( 'src' );
			if ( empty( $src ) ) {
				continue;
			}
			$url   = self::make_absolute_url( $src );
			$score = 0 - $idx;

			$cls_id = strtolower( $img->getAttribute( 'class' ) . ' ' . $img->getAttribute( 'id' ) . ' ' . $url );
			foreach ( $keywords_positive as $word ) {
				if ( false !== strpos( $cls_id, $word ) ) {
					$score += 25;
				}
			}
			foreach ( $keywords_negative as $word ) {
				if ( false !== strpos( $cls_id, $word ) ) {
					$score -= 30;
				}
			}

			$w = (int) $img->getAttribute( 'width' );
			$h = (int) $img->getAttribute( 'height' );
			if ( $w >= 1200 || $h >= 800 ) {
				$score += 30;
			} elseif ( $w >= 800 || $h >= 600 ) {
				$score += 20;
			} elseif ( $w >= 400 || $h >= 300 ) {
				$score += 10;
			} else {
				$score -= 5;
			}

			$target_host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $target_host && $host && $target_host === $host ) {
				$score += 5;
			}

			if ( $score > $best_s ) {
				$best_s = $score;
				$best   = $url;
			}
		}

		return $best;
	}

	/**
	 * Safe, single-font detector following conservative rules.
	 *
	 * @param \DOMDocument $dom DOM.
	 * @return array {primary, candidates}
	 */
	private static function pick_best_font_v2( \DOMDocument $dom ) {
		$links          = $dom->getElementsByTagName( 'link' );
		$styles         = $dom->getElementsByTagName( 'style' );
		$host           = wp_parse_url( home_url(), PHP_URL_HOST );
		$google_fonts   = array();
		$primary_family = '';

		$priority_selectors = array( 'body', 'html', 'h1', '.entry-title', '.site-title', '.elementor-heading-title' );
		foreach ( $styles as $style ) {
			$css = $style->textContent;
			foreach ( $priority_selectors as $selector ) {
				if ( preg_match( '/' . preg_quote( $selector, '/' ) . '\\s*\\{[^}]*font-family\\s*:\\s*([^;}]+)[;}]/i', $css, $m ) ) {
					$families = explode( ',', $m[1] );
					if ( ! empty( $families[0] ) ) {
						$family = trim( $families[0], "\"' \t\r\n" );
						if ( ! in_array( strtolower( $family ), array( 'sans-serif', 'serif', 'monospace', 'inherit' ), true ) ) {
							$primary_family = $family;
							break 2;
						}
					}
				}
			}
		}

		foreach ( $links as $link ) {
			$rel  = strtolower( $link->getAttribute( 'rel' ) );
			$href = $link->getAttribute( 'href' );
			if ( false === strpos( $rel, 'stylesheet' ) || empty( $href ) ) {
				continue;
			}
			$url = self::make_absolute_url( $href );
			if ( false !== strpos( $url, 'fonts.googleapis.com' ) || false !== strpos( $url, 'google-fonts/css' ) ) {
				$google_fonts[] = $url;
				if ( empty( $primary_family ) && preg_match( '/family=([^:&]+)/', $url, $m ) ) {
					$family = urldecode( str_replace( '+', ' ', $m[1] ) );
					$primary_family = $family;
				}
			}
		}

		if ( empty( $primary_family ) ) {
			return array(
				'primary'    => '',
				'candidates' => array(),
			);
		}

		$candidates = array();
		foreach ( $google_fonts as $css_url ) {
			$fonts = self::font_from_stylesheet( $css_url, $primary_family );
			if ( ! empty( $fonts ) ) {
				foreach ( $fonts as $f ) {
					$th = wp_parse_url( $f, PHP_URL_HOST );
					if ( $th && ( $th === $host || false !== strpos( $th, 'fonts.gstatic.com' ) ) ) {
						$candidates[] = $f;
					}
				}
			}
		}

		$candidates = array_values( array_unique( $candidates ) );

		if ( count( $candidates ) > 1 ) {
			foreach ( $candidates as $cand ) {
				if ( false !== stripos( $cand, 'var' ) ) {
					$candidates = array( $cand );
					break;
				}
			}
		}

		$primary = ( count( $candidates ) === 1 ) ? $candidates[0] : '';

		return array(
			'primary'    => $primary,
			'candidates' => $candidates,
		);
	}

	/**
	 * Fetch a stylesheet and return the first usable woff2 from fonts.gstatic.com (prefer variable).
	 *
	 * @param string $url    Stylesheet URL.
	 * @param string $family Target family (unused currently, kept for clarity).
	 * @return string
	 */
	private static function font_from_stylesheet( $url, $family = '' ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 6,
				'redirection' => 2,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return '';
		}

		$urls = array();
		preg_match_all( '/url\\(([^)]+)\\)/i', $body, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $candidate ) {
				$candidate = trim( $candidate, "\"' \t\n\r\0\x0B" );
				if ( preg_match( '/\\.woff2(\\?|$)/i', $candidate ) ) {
					$target_host = wp_parse_url( $candidate, PHP_URL_HOST );
					if ( $target_host && false !== strpos( $target_host, 'fonts.gstatic.com' ) ) {
						$urls[] = $candidate;
					}
				}
			}
		}

		if ( empty( $urls ) ) {
			return array();
		}

		// Prefer variable font if present.
		foreach ( $urls as $candidate ) {
			if ( false !== stripos( $candidate, 'var' ) ) {
				return array( $candidate );
			}
		}

		return array( $urls[0] );
	}

	/**
	 * Add contextual help tab.
	 *
	 * @return void
	 */
	public static function add_help_tab() {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_i365-po-settings' !== $screen->id ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'i365-po-help',
				'title'   => __( 'Performance Optimizer Help', '365i-performance-optimizer' ),
				'content' => '<p>' . esc_html__( 'Toggle the options to match your site: enable speculation for faster navigation, set preconnect/preload URLs to your theme assets, defer non-core scripts, and refine image loading.', '365i-performance-optimizer' ) . '</p>' .
					'<p>' . esc_html__( 'Use Auto-detect to fill fields from your active theme. Exclude checkout/cart paths from speculation. Save to apply changes; Restore defaults to undo.', '365i-performance-optimizer' ) . '</p>',
			)
		);
	}
}
