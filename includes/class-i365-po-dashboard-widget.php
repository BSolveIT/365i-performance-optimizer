<?php
/**
 * Dashboard Widget.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I365_PO_Dashboard_Widget {
	/**
	 * Initialize dashboard widget hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register the dashboard widget.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'i365_po_dashboard_widget',
			__( 'Performance Optimizer', '365i-performance-optimizer' ),
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Enqueue dashboard widget styles.
	 *
	 * @param string $hook Current admin page.
	 *
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'i365-po-dashboard',
			plugin_dir_url( I365_PO_PLUGIN_FILE ) . 'assets/css/dashboard-widget.css',
			array(),
			I365_PO_VERSION
		);
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * @return void
	 */
	public static function render() {
		$settings = I365_PO_Plugin::get_settings();
		$stats    = self::get_optimization_stats( $settings );
		$last_backup = I365_PO_Plugin::get_last_backup_time();
		?>
		<div class="i365-po-widget">
			<div class="i365-po-widget__summary">
				<div class="i365-po-widget__count">
					<span class="i365-po-widget__active"><?php echo esc_html( $stats['active'] ); ?></span>
					<span class="i365-po-widget__separator">/</span>
					<span class="i365-po-widget__total"><?php echo esc_html( $stats['total'] ); ?></span>
					<span class="i365-po-widget__label"><?php esc_html_e( 'Active Optimizations', '365i-performance-optimizer' ); ?></span>
				</div>
				<div class="i365-po-widget__progress">
					<div class="i365-po-widget__progress-bar" style="width: <?php echo esc_attr( $stats['percentage'] ); ?>%;"></div>
				</div>
			</div>

			<div class="i365-po-widget__features">
				<h4><?php esc_html_e( 'Feature Status', '365i-performance-optimizer' ); ?></h4>
				<ul class="i365-po-widget__list">
					<?php foreach ( $stats['features'] as $feature ) : ?>
						<li class="<?php echo $feature['enabled'] ? 'is-enabled' : 'is-disabled'; ?>">
							<span class="i365-po-widget__icon"><?php echo $feature['enabled'] ? '&#10003;' : '&#10005;'; ?></span>
							<span class="i365-po-widget__name"><?php echo esc_html( $feature['name'] ); ?></span>
							<?php if ( ! empty( $feature['detail'] ) ) : ?>
								<span class="i365-po-widget__detail"><?php echo esc_html( $feature['detail'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="i365-po-widget__footer">
				<div class="i365-po-widget__backup">
					<?php if ( $last_backup ) : ?>
						<span class="i365-po-widget__backup-label"><?php esc_html_e( 'Last backup:', '365i-performance-optimizer' ); ?></span>
						<span class="i365-po-widget__backup-time"><?php echo esc_html( human_time_diff( $last_backup, time() ) . ' ' . __( 'ago', '365i-performance-optimizer' ) ); ?></span>
					<?php else : ?>
						<span class="i365-po-widget__backup-label"><?php esc_html_e( 'No backups yet', '365i-performance-optimizer' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="i365-po-widget__actions">
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=i365-po-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Settings', '365i-performance-optimizer' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get optimization statistics.
	 *
	 * @param array $settings Current settings.
	 *
	 * @return array Stats array with active, total, percentage, and features.
	 */
	private static function get_optimization_stats( $settings ) {
		$features = array(
			array(
				'key'     => 'enable_speculation',
				'name'    => __( 'Speculative Loading', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['enable_speculation'] ),
				'detail'  => ! empty( $settings['enable_speculation'] ) ? $settings['speculation_eagerness'] : '',
			),
			array(
				'key'     => 'enable_preload',
				'name'    => __( 'Preconnect & Preload', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['enable_preload'] ),
				'detail'  => '',
			),
			array(
				'key'     => 'defer_scripts',
				'name'    => __( 'Script Deferral', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['defer_scripts'] ),
				'detail'  => '',
			),
			array(
				'key'     => 'delay_js_enabled',
				'name'    => __( 'JS Delay Until Interaction', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['delay_js_enabled'] ),
				'detail'  => ! empty( $settings['delay_js_enabled'] ) ? sprintf( '%dms timeout', $settings['delay_js_timeout'] ) : '',
			),
			array(
				'key'     => 'remove_emoji',
				'name'    => __( 'Emoji Removed', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['remove_emoji'] ),
				'detail'  => '',
			),
			array(
				'key'     => 'disable_embeds',
				'name'    => __( 'Embeds Disabled', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['disable_embeds'] ),
				'detail'  => '',
			),
			array(
				'key'     => 'heartbeat_behavior',
				'name'    => __( 'Heartbeat Control', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['heartbeat_behavior'] ) && 'default' !== $settings['heartbeat_behavior'],
				'detail'  => self::get_heartbeat_detail( $settings['heartbeat_behavior'] ),
			),
			array(
				'key'     => 'remove_query_strings',
				'name'    => __( 'Query Strings Removed', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['remove_query_strings'] ),
				'detail'  => '',
			),
			array(
				'key'     => 'lcp_priority',
				'name'    => __( 'LCP Priority', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['lcp_priority'] ),
				'detail'  => '',
			),
			array(
				'key'     => 'disable_front_lazy',
				'name'    => __( 'Homepage Lazy-load Disabled', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['disable_front_lazy'] ),
				'detail'  => '',
			),
			array(
				'key'     => 'local_fonts_enabled',
				'name'    => __( 'Local Google Fonts', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['local_fonts_enabled'] ),
				'detail'  => '',
			),
			array(
				'key'     => 'wc_conditional_enabled',
				'name'    => __( 'WooCommerce Conditional', '365i-performance-optimizer' ),
				'enabled' => ! empty( $settings['wc_conditional_enabled'] ),
				'detail'  => '',
			),
		);

		$active = 0;
		foreach ( $features as $feature ) {
			if ( $feature['enabled'] ) {
				$active++;
			}
		}

		$total      = count( $features );
		$percentage = $total > 0 ? round( ( $active / $total ) * 100 ) : 0;

		return array(
			'active'     => $active,
			'total'      => $total,
			'percentage' => $percentage,
			'features'   => $features,
		);
	}

	/**
	 * Get human-readable heartbeat detail.
	 *
	 * @param string $behavior Heartbeat behavior setting.
	 *
	 * @return string
	 */
	private static function get_heartbeat_detail( $behavior ) {
		switch ( $behavior ) {
			case 'reduce':
				return __( 'reduced', '365i-performance-optimizer' );
			case 'disable_frontend':
				return __( 'frontend disabled', '365i-performance-optimizer' );
			case 'disable_everywhere':
				return __( 'fully disabled', '365i-performance-optimizer' );
			default:
				return '';
		}
	}
}
