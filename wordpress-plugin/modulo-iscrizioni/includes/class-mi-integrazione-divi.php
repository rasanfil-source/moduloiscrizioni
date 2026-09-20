<?php

defined( 'ABSPATH' ) || exit;

/** Collega il motore del modulo all’editor Divi senza renderlo una dipendenza. */
final class MI_Integrazione_Divi {
	public static function avvia() {
		add_action( 'et_builder_ready', array( __CLASS__, 'registra_modulo' ) );
	}

	public static function registra_modulo() {
		if ( ! class_exists( 'ET_Builder_Module' ) || class_exists( 'MI_Divi_Modulo_Iscrizioni' ) ) return;
		require_once MI_PLUGIN_DIR . 'includes/class-mi-divi-modulo-iscrizioni.php';
		new MI_Divi_Modulo_Iscrizioni();
	}
}
