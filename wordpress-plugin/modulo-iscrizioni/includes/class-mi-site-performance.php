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
		// Alcuni stili dei blocchi WooCommerce vengono accodati tardi.
		// Ripetere la pulizia subito prima della stampa li intercetta senza
		// modificare le pagine che usano davvero WooCommerce.
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_unused_woocommerce_assets' ), 100 );
		// Ultima rete di sicurezza per risorse accodate durante la resa dei blocchi,
		// quindi dopo wp_enqueue_scripts/wp_print_styles.
		add_filter( 'style_loader_tag', array( __CLASS__, 'filter_unused_woocommerce_style' ), 999, 4 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_unused_woocommerce_script' ), 999, 3 );
	}

	public static function dequeue_unused_woocommerce_assets() {
		if ( self::needs_woocommerce_frontend() ) return;

		foreach ( self::woocommerce_style_handles() as $handle ) wp_dequeue_style( $handle );
		foreach ( self::woocommerce_script_handles() as $handle ) wp_dequeue_script( $handle );
	}

	public static function filter_unused_woocommerce_style( $html, $handle ) {
		if ( self::needs_woocommerce_frontend() ) return $html;
		return in_array( $handle, self::woocommerce_style_handles(), true ) ? '' : $html;
	}

	public static function filter_unused_woocommerce_script( $tag, $handle ) {
		if ( self::needs_woocommerce_frontend() ) return $tag;
		return in_array( $handle, self::woocommerce_script_handles(), true ) ? '' : $tag;
	}

	private static function woocommerce_style_handles() {
		return array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-style', 'wc-blocks-packages-style', 'wc-blocks-vendors-style', 'wc-stripe-express-checkout', 'woocommerce_stripe_payment_request', 'wcpay-express-checkout', 'wcpay-blocks-checkout-style' );
	}

	private static function woocommerce_script_handles() {
		return array( 'jquery-blockui', 'js-cookie', 'wc-add-to-cart', 'woocommerce', 'wc-cart-fragments', 'sourcebuster-js', 'wc-order-attribution', 'wc-stripe-express-checkout', 'woocommerce_stripe_payment_request', 'wc-stripe-upe-classic', 'wcpay-express-checkout', 'wcpay-frontend-tracks' );
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
