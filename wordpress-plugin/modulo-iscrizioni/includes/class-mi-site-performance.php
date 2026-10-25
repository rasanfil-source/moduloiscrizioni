<?php

defined( 'ABSPATH' ) || exit;

/** Rimuove gli asset WooCommerce solo dal portale Segreteria, che non li usa. */
final class MI_Site_Performance {
	private const WOOCOMMERCE_STYLE_HANDLES = array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-style', 'wc-blocks-packages-style', 'wc-blocks-vendors-style', 'wc-stripe-express-checkout', 'woocommerce_stripe_payment_request', 'wcpay-express-checkout', 'wcpay-blocks-checkout-style' );
	private const WOOCOMMERCE_SCRIPT_HANDLES = array( 'sourcebuster-js', 'wc-order-attribution', 'wc-stripe-express-checkout', 'woocommerce_stripe_payment_request', 'wc-stripe-upe-classic', 'wcpay-express-checkout', 'wcpay-frontend-tracks' );
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
		if ( ! self::is_secretariat_portal() ) return;

		foreach ( self::woocommerce_style_handles() as $handle ) wp_dequeue_style( $handle );
		foreach ( self::woocommerce_script_handles() as $handle ) wp_dequeue_script( $handle );
	}

	public static function filter_unused_woocommerce_style( $html, $handle ) {
		if ( ! in_array( $handle, self::woocommerce_style_handles(), true ) ) return $html;
		if ( ! self::is_secretariat_portal() ) return $html;
		return in_array( $handle, self::woocommerce_style_handles(), true ) ? '' : $html;
	}

	public static function filter_unused_woocommerce_script( $tag, $handle ) {
		if ( ! in_array( $handle, self::woocommerce_script_handles(), true ) ) return $tag;
		if ( ! self::is_secretariat_portal() ) return $tag;
		return in_array( $handle, self::woocommerce_script_handles(), true ) ? '' : $tag;
	}

	private static function woocommerce_style_handles() {
		return self::WOOCOMMERCE_STYLE_HANDLES;
	}

	private static function woocommerce_script_handles() {
		return self::WOOCOMMERCE_SCRIPT_HANDLES;
	}

	private static function is_secretariat_portal() {
		return class_exists( 'MI_Portal_PWA' ) && MI_Portal_PWA::is_portal();
	}
}
