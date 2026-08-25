<?php

defined( 'ABSPATH' ) || exit;

final class MI_Plugin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot() {
		add_action( 'plugins_loaded', array( 'MI_Activator', 'maybe_upgrade' ), 5 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		MI_Event_Post_Type::boot();
		MI_Access::boot();
		MI_Admin::boot();
		MI_Modello_Email::avvia();
		MI_Workspace_Settings::boot();
		MI_REST_Controller::boot();
		MI_Shortcode::boot();
		add_action( 'mi_sync_workspace_pending', array( 'MI_Registration_Service', 'sync_pending_workspace' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'modulo-iscrizioni', false, dirname( plugin_basename( MI_PLUGIN_FILE ) ) . '/languages' );
	}
}
