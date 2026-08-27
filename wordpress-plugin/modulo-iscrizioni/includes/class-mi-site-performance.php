<?php

defined( 'ABSPATH' ) || exit;

/**
 * Riduce le risorse WooCommerce sulle sole pagine che non usano funzioni
 * commerciali. Non disattiva plugin e non interviene su prodotto, donazione,
 * carrello, pagamento, account o contenuti WooCommerce incorporati.
 */
final class MI_Site_Performance {
	public static function boot() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_unused_woocommerce_assets' ), 200 );
	}

	public static function dequeue_unused_woocommerce_assets() {
		if ( self::needs_woocommerce_frontend() ) return;

		$styles = array(
			'woocommerce-general',
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'wc-blocks-style',
			'wc-blocks-packages-style',
			'wc-blocks-vendors-style',
			'wc-stripe-express-checkout',
			'woocommerce_stripe_payment_request',
			'wcpay-express-checkout',
			'wcpay-blocks-checkout-style',
		);
		$scripts = array(
			'jquery-blockui',
			'js-cookie',
			'wc-add-to-cart',
			'woocommerce',
			'wc-cart-fragments',
			'sourcebuster-js',
			'wc-order-attribution',
			'wc-stripe-express-checkout',
			'woocommerce_stripe_payment_request',
			'wc-stripe-upe-classic',
			'wcpay-express-checkout',
			'wcpay-frontend-tracks',
		);

		foreach ( $styles as $handle ) wp_dequeue_style( $handle );
		foreach ( $scripts as $handle ) wp_dequeue_script( $handle );
	}

	private static function needs_woocommerce_frontend() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return true;
		if ( isset( $_GET['wc-ajax'] ) || isset( $_GET['add-to-cart'] ) || isset( $_GET['order-pay'] ) || isset( $_GET['order-received'] ) ) return true;

		foreach ( array( 'is_woocommerce', 'is_cart', 'is_checkout', 'is_account_page' ) as $conditional ) {
			if ( function_exists( $conditional ) && call_user_func( $conditional ) ) return true;
		}

		$post = get_post();
		if ( ! $post || ! isset( $post->post_content ) ) return false;
		$content = (string) $post->post_content;
		if ( false !== strpos( $content, '<!-- wp:woocommerce/' ) ) return true;
		if ( preg_match( '/\[(?:woocommerce_|product(?:s|_category|_page)?|add_to_cart|shop_messages|wc_)[^\]]*\]/i', $content ) ) return true;

		return false;
	}
}
