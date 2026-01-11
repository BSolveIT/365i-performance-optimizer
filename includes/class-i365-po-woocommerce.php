<?php
/**
 * WooCommerce Conditional Loading.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I365_PO_WooCommerce {
	/**
	 * Initialize WooCommerce optimizations.
	 *
	 * @return void
	 */
	public static function init() {
		// Only run if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$settings = I365_PO_Plugin::get_settings();

		// Only apply if conditional loading is enabled.
		if ( empty( $settings['wc_conditional_enabled'] ) ) {
			return;
		}

		// Hook into wp_enqueue_scripts with high priority to run after WC.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'conditional_assets' ), 99 );
	}

	/**
	 * Conditionally dequeue WooCommerce assets on non-shop pages.
	 *
	 * @return void
	 */
	public static function conditional_assets() {
		// Don't modify assets in admin or when Elementor is editing.
		if ( ! I365_PO_Plugin::is_frontend_context() ) {
			return;
		}

		// Keep all WooCommerce assets on WC pages.
		if ( self::is_woocommerce_page() ) {
			return;
		}

		$settings = I365_PO_Plugin::get_settings();

		// Disable cart fragments AJAX.
		if ( ! empty( $settings['wc_disable_cart_fragments'] ) ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}

		// Disable WooCommerce core styles.
		if ( ! empty( $settings['wc_disable_styles'] ) ) {
			wp_dequeue_style( 'woocommerce-general' );
			wp_dequeue_style( 'woocommerce-layout' );
			wp_dequeue_style( 'woocommerce-smallscreen' );
			wp_dequeue_style( 'woocommerce-inline' );
		}

		// Disable WooCommerce Blocks styles.
		if ( ! empty( $settings['wc_disable_blocks_styles'] ) ) {
			wp_dequeue_style( 'wc-blocks-style' );
			wp_dequeue_style( 'wc-blocks-vendors-style' );
			wp_dequeue_style( 'wc-all-blocks-style' );
		}
	}

	/**
	 * Check if current page is a WooCommerce context.
	 *
	 * @return bool
	 */
	private static function is_woocommerce_page() {
		// Standard WooCommerce conditionals.
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return true;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return true;
		}

		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			return true;
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}

		// Check for WooCommerce shortcodes in post content.
		global $post;
		if ( $post && is_a( $post, 'WP_Post' ) ) {
			$wc_shortcodes = array(
				'woocommerce_cart',
				'woocommerce_checkout',
				'woocommerce_my_account',
				'woocommerce_order_tracking',
				'products',
				'product',
				'product_page',
				'product_category',
				'product_categories',
				'add_to_cart',
				'add_to_cart_url',
			);

			foreach ( $wc_shortcodes as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) {
					return true;
				}
			}
		}

		// Check for WooCommerce blocks in post content.
		if ( $post && is_a( $post, 'WP_Post' ) ) {
			$wc_blocks = array(
				'woocommerce/cart',
				'woocommerce/checkout',
				'woocommerce/mini-cart',
				'woocommerce/all-products',
				'woocommerce/product-collection',
				'woocommerce/products-by-attribute',
			);

			foreach ( $wc_blocks as $block ) {
				if ( has_block( $block, $post ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}
