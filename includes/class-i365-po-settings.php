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

		// Backup & Restore AJAX handlers.
		add_action( 'wp_ajax_i365_po_restore_backup', array( __CLASS__, 'ajax_restore_backup' ) );
		add_action( 'wp_ajax_i365_po_delete_backup', array( __CLASS__, 'ajax_delete_backup' ) );

		// Profile AJAX handlers.
		add_action( 'wp_ajax_i365_po_apply_profile', array( __CLASS__, 'ajax_apply_profile' ) );
		add_action( 'wp_ajax_i365_po_save_profile', array( __CLASS__, 'ajax_save_profile' ) );
		add_action( 'wp_ajax_i365_po_delete_profile', array( __CLASS__, 'ajax_delete_profile' ) );

		// Import/Export handlers.
		add_action( 'wp_ajax_i365_po_export', array( __CLASS__, 'ajax_export' ) );
		add_action( 'wp_ajax_i365_po_import', array( __CLASS__, 'ajax_import' ) );
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

		// Utilities nonce and messages for backup/restore, profiles, import/export.
		wp_localize_script(
			'i365-po-admin',
			'I365POUtilities',
			array(
				'nonce'    => wp_create_nonce( 'i365-po-utilities' ),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'messages' => array(
					'confirmRestore'       => __( 'Restore this backup? Current settings will be backed up first.', '365i-performance-optimizer' ),
					'confirmDeleteBackup'  => __( 'Delete this backup permanently?', '365i-performance-optimizer' ),
					'confirmApplyProfile'  => __( 'Apply this profile? Current settings will be backed up first.', '365i-performance-optimizer' ),
					'confirmDeleteProfile' => __( 'Delete this custom profile?', '365i-performance-optimizer' ),
					'confirmImport'        => __( 'Import these settings? Current settings will be backed up first.', '365i-performance-optimizer' ),
					'profileNameRequired'  => __( 'Please enter a profile name.', '365i-performance-optimizer' ),
					'selectFile'           => __( 'Please select a JSON file to import.', '365i-performance-optimizer' ),
					'invalidFile'          => __( 'Please select a valid JSON file.', '365i-performance-optimizer' ),
					'processing'           => __( 'Processing…', '365i-performance-optimizer' ),
					'error'                => __( 'An error occurred. Please try again.', '365i-performance-optimizer' ),
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
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[remove_query_strings]" value="1" <?php checked( $settings['remove_query_strings'] ); ?> />
							<span>
								<?php esc_html_e( 'Remove version query strings from assets', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Removes ?ver=x.x.x from CSS and JS URLs. Improves caching on some CDNs. Note: May cause cache issues if you update plugins frequently.', '365i-performance-optimizer' ) ); ?>">i</span>
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
							<h2><?php esc_html_e( 'JavaScript Optimization', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Advanced JavaScript optimizations: delay script loading until user interaction and control WordPress Heartbeat API.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Reduce initial JavaScript load for faster page rendering.', '365i-performance-optimizer' ); ?></p>
						</header>

						<div class="i365-po-subsection">
							<h3><?php esc_html_e( 'Delay JavaScript Until Interaction', '365i-performance-optimizer' ); ?></h3>
							<label class="i365-po-toggle">
								<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[delay_js_enabled]" value="1" <?php checked( $settings['delay_js_enabled'] ); ?> />
								<span>
									<?php esc_html_e( 'Delay non-critical scripts until user interacts', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Scripts are not loaded until user scrolls, clicks, touches, or presses a key. Dramatically improves initial page load. jQuery, Elementor, and wp-* scripts are never delayed.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
							</label>
							<label class="i365-po-field">
								<span>
									<?php esc_html_e( 'Fallback timeout (milliseconds)', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Scripts will load after this time even without interaction. Default: 5000ms (5 seconds). Range: 1000-30000ms.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
								<input type="number" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[delay_js_timeout]" value="<?php echo esc_attr( $settings['delay_js_timeout'] ); ?>" min="1000" max="30000" step="500" />
							</label>
							<label class="i365-po-field">
								<span>
									<?php esc_html_e( 'Additional excluded handles (one per line)', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Script handles that should never be delayed. jQuery, Elementor, and wp-* are always excluded automatically.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
								<textarea name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[delay_js_exclude]" rows="2" placeholder="custom-critical-script"><?php echo esc_textarea( implode( "\n", $settings['delay_js_exclude'] ) ); ?></textarea>
							</label>
						</div>

						<div class="i365-po-subsection">
							<h3><?php esc_html_e( 'Heartbeat API Control', '365i-performance-optimizer' ); ?></h3>
							<label class="i365-po-field">
								<span>
									<?php esc_html_e( 'Heartbeat behavior', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'WordPress Heartbeat sends AJAX requests every 15-60 seconds. Reducing or disabling saves server resources.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
								<select name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[heartbeat_behavior]">
									<?php
									$heartbeat_options = array(
										'default'           => __( 'Default (unchanged)', '365i-performance-optimizer' ),
										'reduce'            => __( 'Reduce frequency', '365i-performance-optimizer' ),
										'disable_frontend'  => __( 'Disable on frontend only', '365i-performance-optimizer' ),
										'disable_everywhere'=> __( 'Disable everywhere (use with caution)', '365i-performance-optimizer' ),
									);
									foreach ( $heartbeat_options as $value => $label ) :
										?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['heartbeat_behavior'], $value ); ?>><?php echo esc_html( $label ); ?></option>
										<?php
									endforeach;
									?>
								</select>
							</label>
							<label class="i365-po-field">
								<span>
									<?php esc_html_e( 'Reduced interval (seconds)', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Only applies when "Reduce frequency" is selected. Default: 60 seconds. Range: 15-300 seconds.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
								<input type="number" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[heartbeat_interval]" value="<?php echo esc_attr( $settings['heartbeat_interval'] ); ?>" min="15" max="300" step="5" />
							</label>
							<div class="i365-po-note">
								<strong><?php esc_html_e( 'Note:', '365i-performance-optimizer' ); ?></strong>
								<?php esc_html_e( 'Disabling Heartbeat everywhere may affect auto-save, post locking, and real-time notifications in the admin.', '365i-performance-optimizer' ); ?>
							</div>
						</div>
					</section>

					<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<section class="i365-po-card">
						<header>
							<h2><?php esc_html_e( 'WooCommerce', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Conditionally load WooCommerce assets only on shop-related pages to reduce bloat on other pages.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Reduce WooCommerce overhead on non-shop pages.', '365i-performance-optimizer' ); ?></p>
						</header>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[wc_conditional_enabled]" value="1" <?php checked( $settings['wc_conditional_enabled'] ); ?> />
							<span>
								<?php esc_html_e( 'Enable conditional WooCommerce loading', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'When enabled, WooCommerce assets are only loaded on shop, product, cart, checkout, and account pages.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<div class="i365-po-note">
							<strong><?php esc_html_e( 'On non-shop pages, the following will be disabled:', '365i-performance-optimizer' ); ?></strong>
						</div>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[wc_disable_cart_fragments]" value="1" <?php checked( $settings['wc_disable_cart_fragments'] ); ?> />
							<span>
								<?php esc_html_e( 'Cart fragments AJAX', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Disables the cart fragments script that updates the mini-cart. May affect mini-cart widgets on non-shop pages.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[wc_disable_styles]" value="1" <?php checked( $settings['wc_disable_styles'] ); ?> />
							<span>
								<?php esc_html_e( 'WooCommerce core styles', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Disables woocommerce-general, woocommerce-layout, and woocommerce-smallscreen stylesheets.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[wc_disable_blocks_styles]" value="1" <?php checked( $settings['wc_disable_blocks_styles'] ); ?> />
							<span>
								<?php esc_html_e( 'WooCommerce Blocks styles', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Disables WooCommerce Blocks stylesheets (wc-blocks-style, wc-blocks-vendors-style).', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
					</section>
					<?php endif; ?>

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

					<section class="i365-po-card">
						<header>
							<h2><?php esc_html_e( 'Local Fonts', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Host Google Fonts locally for GDPR compliance, faster loading, and reduced external requests.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Download and serve Google Fonts from your server instead of Google.', '365i-performance-optimizer' ); ?></p>
						</header>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[local_fonts_enabled]" value="1" <?php checked( $settings['local_fonts_enabled'] ); ?> />
							<span>
								<?php esc_html_e( 'Enable local Google Fonts hosting', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'When enabled, Google Fonts CSS/files are downloaded and served locally. Eliminates external font requests.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>
						<label class="i365-po-field">
							<span>
								<?php esc_html_e( 'Font display behavior', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Controls how fonts are displayed while loading. "swap" shows fallback text immediately (recommended for performance).', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
							<select name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[local_fonts_display]">
								<?php
								$display_options = array(
									'swap'     => __( 'swap (recommended)', '365i-performance-optimizer' ),
									'block'    => __( 'block', '365i-performance-optimizer' ),
									'fallback' => __( 'fallback', '365i-performance-optimizer' ),
									'optional' => __( 'optional', '365i-performance-optimizer' ),
									'auto'     => __( 'auto', '365i-performance-optimizer' ),
								);
								foreach ( $display_options as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['local_fonts_display'], $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php
								endforeach;
								?>
							</select>
						</label>
						<label class="i365-po-toggle">
							<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[local_fonts_preload]" value="1" <?php checked( $settings['local_fonts_preload'] ); ?> />
							<span>
								<?php esc_html_e( 'Add preload hints for font files', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Adds preload link tags for font files to improve loading performance.', '365i-performance-optimizer' ) ); ?>">i</span>
							</span>
						</label>

						<div class="i365-po-subsection">
							<h3><?php esc_html_e( 'Font Management', '365i-performance-optimizer' ); ?></h3>
							<?php
							$fonts_info = I365_PO_Local_Fonts::get_fonts_info();
							?>
							<div class="i365-po-fonts-status" id="i365-po-fonts-status">
								<?php if ( $fonts_info['has_fonts'] ) : ?>
									<div class="i365-po-fonts-info">
										<p>
											<strong><?php esc_html_e( 'Status:', '365i-performance-optimizer' ); ?></strong>
											<?php
											printf(
												/* translators: %d: number of font files */
												esc_html__( '%d font files downloaded', '365i-performance-optimizer' ),
												absint( $fonts_info['font_count'] )
											);
											?>
										</p>
										<?php if ( $fonts_info['downloaded'] ) : ?>
											<p>
												<strong><?php esc_html_e( 'Downloaded:', '365i-performance-optimizer' ); ?></strong>
												<?php echo esc_html( human_time_diff( $fonts_info['downloaded'], time() ) . ' ' . __( 'ago', '365i-performance-optimizer' ) ); ?>
											</p>
										<?php endif; ?>
										<?php if ( $fonts_info['disk_usage'] > 0 ) : ?>
											<p>
												<strong><?php esc_html_e( 'Disk usage:', '365i-performance-optimizer' ); ?></strong>
												<?php echo esc_html( size_format( $fonts_info['disk_usage'] ) ); ?>
											</p>
										<?php endif; ?>
									</div>
								<?php else : ?>
									<p class="i365-po-fonts-empty"><?php esc_html_e( 'No local fonts downloaded yet. Click "Download Fonts" to scan your site and download Google Fonts locally.', '365i-performance-optimizer' ); ?></p>
								<?php endif; ?>
							</div>
							<div class="i365-po-fonts-actions">
								<label class="i365-po-field i365-po-field--inline">
									<span><?php esc_html_e( 'Google Fonts URL (optional):', '365i-performance-optimizer' ); ?></span>
									<input type="url" id="i365-po-fonts-url" placeholder="https://fonts.googleapis.com/css2?family=..." class="regular-text" />
								</label>
								<div class="i365-po-fonts-buttons">
									<button type="button" class="button button-primary" id="i365-po-download-fonts">
										<?php esc_html_e( 'Download Fonts', '365i-performance-optimizer' ); ?>
									</button>
									<button type="button" class="button button-secondary" id="i365-po-clear-fonts" <?php disabled( ! $fonts_info['has_fonts'] ); ?>>
										<?php esc_html_e( 'Clear Local Fonts', '365i-performance-optimizer' ); ?>
									</button>
								</div>
							</div>
							<p class="description"><?php esc_html_e( 'Leave the URL empty to auto-detect Google Fonts from your homepage. Or paste a specific Google Fonts URL.', '365i-performance-optimizer' ); ?></p>
						</div>
					</section>

					<section class="i365-po-card">
						<header>
							<h2><?php esc_html_e( 'Database Cleanup', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Clean up database bloat: revisions, auto-drafts, spam, orphaned data, and expired transients.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Remove unnecessary database entries to improve performance.', '365i-performance-optimizer' ); ?></p>
						</header>

						<div class="i365-po-subsection">
							<h3><?php esc_html_e( 'Scheduled Cleanup', '365i-performance-optimizer' ); ?></h3>
							<label class="i365-po-toggle">
								<input type="checkbox" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[db_cleanup_enabled]" value="1" <?php checked( $settings['db_cleanup_enabled'] ); ?> />
								<span>
									<?php esc_html_e( 'Enable automatic scheduled cleanup', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Automatically clean database on a schedule. Runs all cleanup tasks.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
							</label>
							<label class="i365-po-field">
								<span>
									<?php esc_html_e( 'Schedule', '365i-performance-optimizer' ); ?>
								</span>
								<select name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[db_cleanup_schedule]">
									<?php
									$schedule_options = array(
										'daily'   => __( 'Daily', '365i-performance-optimizer' ),
										'weekly'  => __( 'Weekly', '365i-performance-optimizer' ),
										'monthly' => __( 'Monthly', '365i-performance-optimizer' ),
									);
									foreach ( $schedule_options as $value => $label ) :
										?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['db_cleanup_schedule'], $value ); ?>><?php echo esc_html( $label ); ?></option>
										<?php
									endforeach;
									?>
								</select>
							</label>
							<label class="i365-po-field">
								<span>
									<?php esc_html_e( 'Revisions to keep per post', '365i-performance-optimizer' ); ?>
									<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Number of revisions to keep for each post. Set to 0 to remove all revisions. Range: 0-50.', '365i-performance-optimizer' ) ); ?>">i</span>
								</span>
								<input type="number" name="<?php echo esc_attr( I365_PO_OPTION_KEY ); ?>[db_revisions_keep]" value="<?php echo esc_attr( $settings['db_revisions_keep'] ); ?>" min="0" max="50" step="1" />
							</label>
						</div>

						<div class="i365-po-subsection">
							<h3><?php esc_html_e( 'Manual Cleanup', '365i-performance-optimizer' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Analyze and clean specific database items. A backup is created before cleanup.', '365i-performance-optimizer' ); ?></p>
							<div class="i365-po-db-actions">
								<button type="button" class="button button-secondary" id="i365-po-db-analyze">
									<?php esc_html_e( 'Analyze Database', '365i-performance-optimizer' ); ?>
								</button>
								<button type="button" class="button button-primary" id="i365-po-db-cleanup" disabled>
									<?php esc_html_e( 'Clean Selected', '365i-performance-optimizer' ); ?>
								</button>
							</div>
							<div id="i365-po-db-stats-container" class="i365-po-db-stats-container">
								<p class="i365-po-db-placeholder"><?php esc_html_e( 'Click "Analyze Database" to see cleanup options.', '365i-performance-optimizer' ); ?></p>
							</div>
						</div>
					</section>

					<section class="i365-po-card i365-po-card--utilities">
						<header>
							<h2><?php esc_html_e( 'Utilities', '365i-performance-optimizer' ); ?>
								<span class="i365-po-help" tabindex="0" data-tip="<?php echo esc_attr( __( 'Backup/restore settings, apply preset profiles, and import/export configurations.', '365i-performance-optimizer' ) ); ?>">i</span>
							</h2>
							<p><?php esc_html_e( 'Manage backups, profiles, and transfer settings between sites.', '365i-performance-optimizer' ); ?></p>
						</header>

						<!-- Profiles Section -->
						<div class="i365-po-subsection">
							<h3><?php esc_html_e( 'Configuration Profiles', '365i-performance-optimizer' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Quickly apply preset configurations or save your current settings as a custom profile.', '365i-performance-optimizer' ); ?></p>
							<div id="i365-po-profiles-container">
								<?php echo self::render_profiles_list(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<div class="i365-po-save-profile">
								<input type="text" id="i365-po-profile-name" placeholder="<?php esc_attr_e( 'New profile name', '365i-performance-optimizer' ); ?>" class="regular-text" />
								<input type="text" id="i365-po-profile-desc" placeholder="<?php esc_attr_e( 'Description (optional)', '365i-performance-optimizer' ); ?>" class="regular-text" />
								<button type="button" class="button button-secondary" id="i365-po-save-profile">
									<?php esc_html_e( 'Save Current as Profile', '365i-performance-optimizer' ); ?>
								</button>
							</div>
						</div>

						<!-- Backups Section -->
						<div class="i365-po-subsection">
							<h3><?php esc_html_e( 'Settings Backups', '365i-performance-optimizer' ); ?></h3>
							<p class="description">
								<?php
								printf(
									/* translators: %d: maximum number of backups */
									esc_html__( 'Automatic backups are created before each save. Up to %d backups are retained.', '365i-performance-optimizer' ),
									absint( I365_PO_Plugin::MAX_BACKUPS )
								);
								?>
							</p>
							<div id="i365-po-backups-container">
								<?php echo self::render_backups_list(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>

						<!-- Import/Export Section -->
						<div class="i365-po-subsection">
							<h3><?php esc_html_e( 'Import / Export', '365i-performance-optimizer' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Transfer settings between sites or create a backup file.', '365i-performance-optimizer' ); ?></p>
							<div class="i365-po-import-export">
								<div class="i365-po-export">
									<button type="button" class="button button-secondary" id="i365-po-export">
										<?php esc_html_e( 'Export Settings', '365i-performance-optimizer' ); ?>
									</button>
								</div>
								<div class="i365-po-import">
									<input type="file" id="i365-po-import-file" accept=".json" style="display:none;" />
									<button type="button" class="button button-secondary" id="i365-po-import-btn">
										<?php esc_html_e( 'Import Settings', '365i-performance-optimizer' ); ?>
									</button>
									<span class="i365-po-import-filename" id="i365-po-import-filename"></span>
								</div>
							</div>
						</div>
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

	/*
	|--------------------------------------------------------------------------
	| Backup & Restore AJAX Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * AJAX: Restore settings from a backup.
	 *
	 * @return void
	 */
	public static function ajax_restore_backup() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$timestamp = isset( $_POST['timestamp'] ) ? absint( $_POST['timestamp'] ) : 0;

		if ( empty( $timestamp ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid backup timestamp.', '365i-performance-optimizer' ) ) );
		}

		$result = I365_PO_Plugin::restore_backup( $timestamp );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Settings restored successfully. Page will reload.', '365i-performance-optimizer' ),
			'reload'  => true,
		) );
	}

	/**
	 * AJAX: Delete a specific backup.
	 *
	 * @return void
	 */
	public static function ajax_delete_backup() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$timestamp = isset( $_POST['timestamp'] ) ? absint( $_POST['timestamp'] ) : 0;

		if ( empty( $timestamp ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid backup timestamp.', '365i-performance-optimizer' ) ) );
		}

		$result = I365_PO_Plugin::delete_backup( $timestamp );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete backup.', '365i-performance-optimizer' ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Backup deleted.', '365i-performance-optimizer' ),
			'backups' => self::render_backups_list(),
		) );
	}

	/*
	|--------------------------------------------------------------------------
	| Profile AJAX Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * AJAX: Apply a profile.
	 *
	 * @return void
	 */
	public static function ajax_apply_profile() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$slug = isset( $_POST['profile'] ) ? sanitize_text_field( wp_unslash( $_POST['profile'] ) ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid profile.', '365i-performance-optimizer' ) ) );
		}

		$result = I365_PO_Plugin::apply_profile( $slug );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$profiles = I365_PO_Plugin::get_all_profiles();
		$profile_name = isset( $profiles[ $slug ]['name'] ) ? $profiles[ $slug ]['name'] : $slug;

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %s: profile name */
				__( 'Profile "%s" applied. Page will reload.', '365i-performance-optimizer' ),
				$profile_name
			),
			'reload' => true,
		) );
	}

	/**
	 * AJAX: Save current settings as a custom profile.
	 *
	 * @return void
	 */
	public static function ajax_save_profile() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Profile name is required.', '365i-performance-optimizer' ) ) );
		}

		$result = I365_PO_Plugin::save_profile( $name, $description );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => sprintf(
				/* translators: %s: profile name */
				__( 'Profile "%s" saved.', '365i-performance-optimizer' ),
				$name
			),
			'profiles' => self::render_profiles_list(),
		) );
	}

	/**
	 * AJAX: Delete a custom profile.
	 *
	 * @return void
	 */
	public static function ajax_delete_profile() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$slug = isset( $_POST['profile'] ) ? sanitize_text_field( wp_unslash( $_POST['profile'] ) ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid profile.', '365i-performance-optimizer' ) ) );
		}

		$result = I365_PO_Plugin::delete_profile( $slug );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'Profile deleted.', '365i-performance-optimizer' ),
			'profiles' => self::render_profiles_list(),
		) );
	}

	/*
	|--------------------------------------------------------------------------
	| Import/Export AJAX Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * AJAX: Export settings as JSON.
	 *
	 * @return void
	 */
	public static function ajax_export() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$json = I365_PO_Plugin::export_settings();

		wp_send_json_success( array(
			'json'     => $json,
			'filename' => 'performance-optimizer-settings-' . gmdate( 'Y-m-d' ) . '.json',
		) );
	}

	/**
	 * AJAX: Import settings from JSON.
	 *
	 * @return void
	 */
	public static function ajax_import() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON needs to be parsed, sanitization happens in import_settings().
		$json = isset( $_POST['json'] ) ? sanitize_textarea_field( wp_unslash( $_POST['json'] ) ) : '';

		if ( empty( $json ) ) {
			wp_send_json_error( array( 'message' => __( 'No settings data provided.', '365i-performance-optimizer' ) ) );
		}

		$result = I365_PO_Plugin::import_settings( $json );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Settings imported successfully. Page will reload.', '365i-performance-optimizer' ),
			'reload'  => true,
		) );
	}

	/*
	|--------------------------------------------------------------------------
	| Helper Render Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Render backups list HTML for AJAX response.
	 *
	 * @return string HTML.
	 */
	private static function render_backups_list() {
		$backups = I365_PO_Plugin::get_backups();

		if ( empty( $backups ) ) {
			return '<p class="i365-po-empty">' . esc_html__( 'No backups available.', '365i-performance-optimizer' ) . '</p>';
		}

		$html = '<ul class="i365-po-backups-list">';
		foreach ( $backups as $timestamp => $backup ) {
			$date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
			$user = ! empty( $backup['user'] ) ? $backup['user'] : __( 'Unknown', '365i-performance-optimizer' );
			$version = ! empty( $backup['version'] ) ? $backup['version'] : '?';

			$html .= '<li class="i365-po-backup-item" data-timestamp="' . esc_attr( $timestamp ) . '">';
			$html .= '<span class="i365-po-backup-date">' . esc_html( $date ) . '</span>';
			/* translators: %1$s: user name or ID, %2$s: plugin version */
			$html .= '<span class="i365-po-backup-meta">' . esc_html( sprintf( __( 'by %1$s (v%2$s)', '365i-performance-optimizer' ), $user, $version ) ) . '</span>';
			$html .= '<span class="i365-po-backup-actions">';
			$html .= '<button type="button" class="button button-small i365-po-restore-backup" data-timestamp="' . esc_attr( $timestamp ) . '">' . esc_html__( 'Restore', '365i-performance-optimizer' ) . '</button>';
			$html .= '<button type="button" class="button button-small button-link-delete i365-po-delete-backup" data-timestamp="' . esc_attr( $timestamp ) . '">' . esc_html__( 'Delete', '365i-performance-optimizer' ) . '</button>';
			$html .= '</span>';
			$html .= '</li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Render profiles list HTML for AJAX response.
	 *
	 * @return string HTML.
	 */
	private static function render_profiles_list() {
		$profiles = I365_PO_Plugin::get_all_profiles();

		$html = '<div class="i365-po-profiles-grid">';
		foreach ( $profiles as $slug => $profile ) {
			$is_builtin = ! empty( $profile['builtin'] );
			$html .= '<div class="i365-po-profile-card' . ( $is_builtin ? ' is-builtin' : '' ) . '" data-slug="' . esc_attr( $slug ) . '">';
			$html .= '<h4>' . esc_html( $profile['name'] ) . '</h4>';
			$html .= '<p>' . esc_html( $profile['description'] ) . '</p>';
			$html .= '<div class="i365-po-profile-actions">';
			$html .= '<button type="button" class="button button-small i365-po-apply-profile" data-profile="' . esc_attr( $slug ) . '">' . esc_html__( 'Apply', '365i-performance-optimizer' ) . '</button>';
			if ( ! $is_builtin ) {
				$html .= '<button type="button" class="button button-small button-link-delete i365-po-delete-profile" data-profile="' . esc_attr( $slug ) . '">' . esc_html__( 'Delete', '365i-performance-optimizer' ) . '</button>';
			}
			$html .= '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';

		return $html;
	}
}
