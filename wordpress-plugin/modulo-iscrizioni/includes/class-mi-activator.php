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
		wp_clear_scheduled_hook( 'mi_spedisci_email_in_coda' );
		wp_clear_scheduled_hook( 'mi_expire_registrations' );
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
		if ( ! wp_next_scheduled( 'mi_expire_registrations' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'mi_expire_registrations' );
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
		$payments = $wpdb->prefix . 'mi_payments';
		$ticket_counters = $wpdb->prefix . 'mi_ticket_counters';
		$event_revisions = $wpdb->prefix . 'mi_event_revisions';
		$registration_events = $wpdb->prefix . 'mi_registration_events';

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
			order_options_json longtext NULL,
			idempotency_key varchar(64) NOT NULL,
			workspace_status varchar(24) NOT NULL DEFAULT 'PENDING',
			workspace_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			workspace_last_error varchar(80) NULL,
			workspace_synced_at datetime NULL,
			event_revision_id bigint(20) unsigned NULL,
			event_revision_hash varchar(64) NULL,
			snapshot_json longtext NULL,
			privacy_consent_id varchar(100) NULL,
			privacy_policy_version varchar(64) NULL,
			privacy_accepted_at datetime NULL,
			marketing_consent_id varchar(100) NULL,
			marketing_accepted_at datetime NULL,
			expires_at datetime NULL,
			capacity_released_at datetime NULL,
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
			ticket_type_name varchar(180) NOT NULL DEFAULT '',
			quantity smallint(5) unsigned NOT NULL,
			unit_price_cents int(10) unsigned NOT NULL DEFAULT 0,
			options_json longtext NULL,
			PRIMARY KEY  (id),
			KEY registration_id (registration_id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$participants} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			ticket_type_code varchar(64) NOT NULL DEFAULT '',
			ticket_index smallint(5) unsigned NOT NULL DEFAULT 0,
			first_name varchar(80) NOT NULL,
			last_name varchar(80) NOT NULL,
			extra_json longtext NULL,
			options_json longtext NULL,
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

		dbDelta( "CREATE TABLE {$ticket_counters} (
			event_id bigint(20) unsigned NOT NULL,
			ticket_type_code varchar(64) NOT NULL,
			confirmed_count int(10) unsigned NOT NULL DEFAULT 0,
			waitlisted_count int(10) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY (event_id,ticket_type_code)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$event_revisions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			revision_number int(10) unsigned NOT NULL,
			config_hash varchar(64) NOT NULL,
			config_json longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY event_revision (event_id,revision_number),
			UNIQUE KEY event_hash (event_id,config_hash)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$registration_events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			event_type varchar(40) NOT NULL,
			from_status varchar(24) NULL,
			to_status varchar(24) NULL,
			actor_label varchar(120) NULL,
			detail_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY registration_id (registration_id)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$outbox} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			recipient varchar(254) NOT NULL,
			template_type varchar(40) NOT NULL,
			payload_json longtext NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'PREVIEW',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			last_error varchar(190) NULL,
			sent_at datetime NULL,
			processing_started_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY registration_id (registration_id),
			KEY status (status)
		) ENGINE=InnoDB {$charset};" );

		dbDelta( "CREATE TABLE {$payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			registration_id bigint(20) unsigned NOT NULL,
			transaction_kind varchar(24) NOT NULL DEFAULT 'PAYMENT',
			installment_kind varchar(24) NOT NULL DEFAULT 'FULL',
			effective_at datetime NOT NULL,
			amount_cents int(10) unsigned NOT NULL DEFAULT 0,
			payment_source varchar(24) NOT NULL,
			external_reference varchar(120) NULL,
			operator_label varchar(120) NULL,
			administrative_note text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY registration_id (registration_id),
			KEY effective_at (effective_at)
		) ENGINE=InnoDB {$charset};" );

		self::backfill_ticket_counters( $ticket_counters, $registrations, $items );
		update_option( 'mi_db_version', MI_VERSION, false );
	}

	private static function backfill_ticket_counters( $ticket_counters, $registrations, $items ) {
		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ticket_counters}" );
		if ( $count > 0 ) {
			return;
		}
		$now = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$ticket_counters} (event_id, ticket_type_code, confirmed_count, waitlisted_count, updated_at)
				 SELECT r.event_id, i.ticket_type_code,
				 SUM(CASE WHEN r.status = 'CONFIRMED' AND r.capacity_released_at IS NULL THEN i.quantity ELSE 0 END),
				 SUM(CASE WHEN r.status = 'WAITLISTED' AND r.capacity_released_at IS NULL THEN i.quantity ELSE 0 END), %s
				 FROM {$items} i INNER JOIN {$registrations} r ON r.id = i.registration_id
				 GROUP BY r.event_id, i.ticket_type_code",
				$now
			)
		);
	}

	private static function add_roles_and_capabilities() {
		$capabilities = array(
			'read'                  => true,
			'mi_manage_events'      => true,
			'mi_publish_events'     => true,
			'mi_view_registrations' => true,
			'mi_manage_payments'   => true,
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
