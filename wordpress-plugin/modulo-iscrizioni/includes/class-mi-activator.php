<?php

defined( 'ABSPATH' ) || exit;

final class MI_Activator {
	public static function activate() {
		self::create_tables();
		self::add_roles_and_capabilities();
		self::ensure_schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'mi_sync_workspace_pending' );
		wp_clear_scheduled_hook( 'mi_sync_workspace_registration' );
	}

	public static function maybe_upgrade() {
		if ( MI_VERSION !== get_option( 'mi_db_version' ) ) {
			self::create_tables();
			self::add_roles_and_capabilities();
		}
		self::ensure_schedule();
	}

	private static function ensure_schedule() {
		if ( ! wp_next_scheduled( 'mi_sync_workspace_pending' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'mi_sync_workspace_pending' );
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$registrations = $wpdb->prefix . 'mi_registrations';
		$items = $wpdb->prefix . 'mi_registration_items';
		$participants = $wpdb->prefix . 'mi_participants';
		$counters = $wpdb->prefix . 'mi_event_counters';
		$outbox = $wpdb->prefix . 'mi_email_outbox';

		dbDelta( "CREATE TABLE {$registrations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_code varchar(32) NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			status varchar(24) NOT NULL,
			buyer_first_name varchar(80) NOT NULL,
			buyer_last_name varchar(80) NOT NULL,
			buyer_email varchar(254) NOT NULL,
			buyer_phone varchar(32) NOT NULL,
			total_qty smallint(5) unsigned NOT NULL,
			economic_mode varchar(24) NOT NULL DEFAULT 'REGISTRATION_ONLY',
			total_cents int(10) unsigned NOT NULL DEFAULT 0,
			initial_due_cents int(10) unsigned NOT NULL DEFAULT 0,
			balance_cents int(10) unsigned NOT NULL DEFAULT 0,
			payment_methods_json longtext NULL,
			idempotency_key varchar(64) NOT NULL,
			workspace_status varchar(24) NOT NULL DEFAULT 'PENDING',
			workspace_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			workspace_last_error varchar(80) NULL,
			workspace_synced_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_code (order_code),
			UNIQUE KEY idempotency_key (event_id,idempotency_key),
			KEY event_status (event_id,status),
			KEY workspace_status (workspace_status)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			ticket_type_code varchar(64) NOT NULL,
			quantity smallint(5) unsigned NOT NULL,
			unit_price_cents int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY registration_id (registration_id)
		) ENGINE=InnoDB {$charset};" );

			dbDelta( "CREATE TABLE {$participants} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			first_name varchar(80) NOT NULL,
			last_name varchar(80) NOT NULL,
			extra_json longtext NULL,
			PRIMARY KEY  (id),
			KEY registration_id (registration_id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$counters} (
			event_id bigint(20) unsigned NOT NULL,
			confirmed_count int(10) unsigned NOT NULL DEFAULT 0,
			waitlisted_count int(10) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (event_id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$outbox} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			recipient varchar(254) NOT NULL,
			template_type varchar(40) NOT NULL,
			payload_json longtext NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'PREVIEW',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY registration_id (registration_id),
			KEY status (status)
		) ENGINE=InnoDB {$charset};" );

		update_option( 'mi_db_version', MI_VERSION, false );
	}

	private static function add_roles_and_capabilities() {
		$capabilities = array(
			'read'                  => true,
			'mi_manage_events'      => true,
			'mi_publish_events'     => true,
			'mi_view_registrations' => true,
		);

		add_role( 'mi_event_manager', 'Gestore iscrizioni', $capabilities );
		$manager = get_role( 'mi_event_manager' );
		if ( $manager ) {
			foreach ( $capabilities as $capability => $grant ) {
				$manager->add_cap( $capability, $grant );
			}
		}
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( array_keys( $capabilities ) as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
	}
}
