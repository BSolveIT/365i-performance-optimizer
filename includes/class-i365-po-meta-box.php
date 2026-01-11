<?php
/**
 * Per-Page Performance Overrides Meta Box.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I365_PO_Meta_Box {
	/**
	 * Post meta key for storing overrides.
	 *
	 * @var string
	 */
	const META_KEY = '_i365_po_overrides';

	/**
	 * Nonce action name.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'i365_po_meta_box_save';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'i365_po_meta_box_nonce';

	/**
	 * Initialize meta box hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Register the meta box for supported post types.
	 *
	 * @return void
	 */
	public static function register() {
		/**
		 * Filter the post types that show the performance overrides meta box.
		 *
		 * @param array $post_types Array of post type slugs.
		 */
		$post_types = apply_filters( 'i365_po_meta_box_post_types', array( 'page', 'post' ) );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'i365_po_overrides',
				__( 'Performance Optimizer', '365i-performance-optimizer' ),
				array( __CLASS__, 'render' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box content.
	 *
	 * @param WP_Post $post Current post object.
	 *
	 * @return void
	 */
	public static function render( $post ) {
		// Add nonce field.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		// Get current overrides.
		$overrides = self::get_overrides( $post->ID );

		$override_options = self::get_override_options();
		?>
		<div class="i365-po-meta-box">
			<p class="description">
				<?php esc_html_e( 'Override global performance settings for this page.', '365i-performance-optimizer' ); ?>
			</p>

			<div class="i365-po-meta-box__options">
				<?php foreach ( $override_options as $key => $option ) : ?>
					<label class="i365-po-meta-box__option">
						<input
							type="checkbox"
							name="i365_po_overrides[<?php echo esc_attr( $key ); ?>]"
							value="1"
							<?php checked( ! empty( $overrides[ $key ] ) ); ?>
						/>
						<span><?php echo esc_html( $option['label'] ); ?></span>
						<?php if ( ! empty( $option['description'] ) ) : ?>
							<span class="i365-po-meta-box__desc"><?php echo esc_html( $option['description'] ); ?></span>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="i365-po-meta-box__custom">
				<label for="i365_po_excluded_scripts">
					<strong><?php esc_html_e( 'Additional excluded scripts', '365i-performance-optimizer' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Script handles to exclude from optimization on this page (comma-separated).', '365i-performance-optimizer' ); ?></span>
				</label>
				<input
					type="text"
					id="i365_po_excluded_scripts"
					name="i365_po_overrides[excluded_scripts]"
					value="<?php echo esc_attr( isset( $overrides['excluded_scripts'] ) ? $overrides['excluded_scripts'] : '' ); ?>"
					class="widefat"
					placeholder="my-script, another-script"
				/>
			</div>
		</div>

		<style>
			.i365-po-meta-box .description {
				color: #646970;
				font-style: italic;
				margin-bottom: 12px;
			}
			.i365-po-meta-box__options {
				margin-bottom: 15px;
			}
			.i365-po-meta-box__option {
				display: flex;
				flex-wrap: wrap;
				align-items: flex-start;
				gap: 6px;
				margin-bottom: 10px;
				cursor: pointer;
			}
			.i365-po-meta-box__option input[type="checkbox"] {
				margin-top: 2px;
			}
			.i365-po-meta-box__option span:first-of-type {
				flex: 1;
				font-weight: 500;
			}
			.i365-po-meta-box__desc {
				width: 100%;
				font-size: 11px;
				color: #646970;
				margin-left: 22px;
			}
			.i365-po-meta-box__custom {
				border-top: 1px solid #dcdcde;
				padding-top: 12px;
			}
			.i365-po-meta-box__custom label {
				display: block;
				margin-bottom: 8px;
			}
			.i365-po-meta-box__custom label strong {
				display: block;
				margin-bottom: 4px;
			}
			.i365-po-meta-box__custom .description {
				font-size: 11px;
				margin: 0;
			}
		</style>
		<?php
	}

	/**
	 * Get available override options.
	 *
	 * @return array
	 */
	public static function get_override_options() {
		return array(
			'disable_all'         => array(
				'label'       => __( 'Disable all optimizations', '365i-performance-optimizer' ),
				'description' => __( 'Turn off all plugin optimizations for this page.', '365i-performance-optimizer' ),
			),
			'disable_speculation' => array(
				'label'       => __( 'Disable speculative loading', '365i-performance-optimizer' ),
				'description' => '',
			),
			'disable_defer'       => array(
				'label'       => __( 'Disable script deferral', '365i-performance-optimizer' ),
				'description' => '',
			),
			'disable_delay_js'    => array(
				'label'       => __( 'Disable JS delay', '365i-performance-optimizer' ),
				'description' => '',
			),
			'force_lazy_load'     => array(
				'label'       => __( 'Force lazy-load images', '365i-performance-optimizer' ),
				'description' => __( 'Override homepage lazy-load disable setting.', '365i-performance-optimizer' ),
			),
			'disable_local_fonts' => array(
				'label'       => __( 'Disable local fonts', '365i-performance-optimizer' ),
				'description' => __( 'Use original Google Fonts on this page.', '365i-performance-optimizer' ),
			),
		);
	}

	/**
	 * Save meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public static function save( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		$post_type = get_post_type_object( $post->post_type );
		if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
			return;
		}

		// Get and sanitize overrides.
		$overrides = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Individual values are sanitized below.
		$raw_input = isset( $_POST['i365_po_overrides'] ) ? (array) wp_unslash( $_POST['i365_po_overrides'] ) : array();

		// Sanitize checkbox values.
		$valid_keys = array_keys( self::get_override_options() );
		foreach ( $valid_keys as $key ) {
			if ( ! empty( $raw_input[ $key ] ) ) {
				$overrides[ $key ] = true;
			}
		}

		// Sanitize excluded scripts.
		if ( ! empty( $raw_input['excluded_scripts'] ) ) {
			$scripts = sanitize_text_field( $raw_input['excluded_scripts'] );
			$overrides['excluded_scripts'] = $scripts;
		}

		// Save or delete meta.
		if ( ! empty( $overrides ) ) {
			update_post_meta( $post_id, self::META_KEY, $overrides );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Get overrides for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	public static function get_overrides( $post_id ) {
		$overrides = get_post_meta( $post_id, self::META_KEY, true );

		return is_array( $overrides ) ? $overrides : array();
	}

	/**
	 * Check if a specific override is active for the current post.
	 *
	 * @param string $override Override key to check.
	 *
	 * @return bool
	 */
	public static function is_override_active( $override ) {
		if ( is_admin() ) {
			return false;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		$overrides = self::get_overrides( $post_id );

		// Check for disable_all first.
		if ( ! empty( $overrides['disable_all'] ) ) {
			return true;
		}

		return ! empty( $overrides[ $override ] );
	}

	/**
	 * Get excluded script handles for the current post.
	 *
	 * @return array
	 */
	public static function get_excluded_scripts() {
		if ( is_admin() ) {
			return array();
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return array();
		}

		$overrides = self::get_overrides( $post_id );

		if ( empty( $overrides['excluded_scripts'] ) ) {
			return array();
		}

		// Split by comma and clean up.
		$scripts = explode( ',', $overrides['excluded_scripts'] );
		$scripts = array_map( 'trim', $scripts );
		$scripts = array_filter( $scripts );

		return $scripts;
	}

	/**
	 * Check if all optimizations should be disabled for current page.
	 *
	 * @return bool
	 */
	public static function should_disable_all() {
		return self::is_override_active( 'disable_all' );
	}
}
