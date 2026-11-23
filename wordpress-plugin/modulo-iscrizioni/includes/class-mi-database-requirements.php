<?php
defined( 'ABSPATH' ) || exit;

final class MI_Database_Requirements {
	/** I lock evento e worker devono poter coesistere nella stessa connessione. */
	public static function supports_multiple_locks( $server ) {
		if ( preg_match( '/(\d+\.\d+\.\d+)-MariaDB/i', $server, $match ) ) return version_compare( $match[1], '10.0.2', '>=' );
		if ( false !== stripos( $server, 'MariaDB' ) ) return false;
		return preg_match( '/^(\d+\.\d+\.\d+)/', $server, $match ) && version_compare( $match[1], '5.7.5', '>=' );
	}
	public static function supported() {
		global $wpdb;
		return self::supports_multiple_locks( (string) $wpdb->db_server_info() );
	}
	public static function message() {
		return 'Modulo Iscrizioni richiede MySQL 5.7.5 o successivo oppure MariaDB 10.0.2 o successivo per mantenere correttamente i blocchi delle operazioni. Chiedi al gestore dell’hosting di aggiornare il database. Il plugin non può avviare le operazioni su questo server.';
	}
	public static function activation_check() {
		if ( ! self::supported() ) wp_die( esc_html( self::message() ) );
	}
	public static function notice() {
		if ( current_user_can( 'manage_options' ) ) echo '<div class="notice notice-error"><p>' . esc_html( self::message() ) . '</p></div>';
	}
}
