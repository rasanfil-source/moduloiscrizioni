<?php

defined( 'ABSPATH' ) || exit;

/**
 * Explicitly migrates an event to another activity without touching registrations.
 */
final class MI_Event_Activity_Migration {
	const PAGE_SLUG = 'mi-event-activity-migration';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mi_migrate_event_activity', array( __CLASS__, 'handle' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE,
			'Migrazione attività',
			'Migrazione attività',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}

		$events = get_posts(
			array(
				'post_type'   => MI_Event_Post_Type::EVENT_TYPE,
				'post_status' => array( 'publish', 'draft', 'private', 'mi_archived' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$activities = get_posts(
			array(
				'post_type'   => MI_Event_Post_Type::ACTIVITY_TYPE,
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$notice = get_transient( self::notice_key() );
		if ( $notice ) {
			delete_transient( self::notice_key() );
		}
		?>
		<div class="wrap">
			<h1>Migrazione attività evento</h1>
			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( ! empty( $notice['success'] ) ? 'success' : 'error' ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ?? '' ); ?></p></div>
			<?php endif; ?>
			<p>Questa operazione modifica soltanto l’attività associata all’evento. Iscrizioni, partecipanti, pagamenti, contatori e istantanee storiche restano invariati.</p>
			<p><strong>Usala esclusivamente quando il normale salvataggio impedisce il cambio perché esistono già iscrizioni.</strong> L’evento non viene pubblicato e non vengono inviate email.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mi_migrate_event_activity">
				<?php wp_nonce_field( 'mi_migrate_event_activity' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mi_migration_event_id">Evento</label></th>
						<td><select id="mi_migration_event_id" name="event_id" required><option value="">Seleziona evento</option><?php foreach ( $events as $event ) : ?>
							<option value="<?php echo esc_attr( $event->ID ); ?>"><?php echo esc_html( $event->post_title . ' — ID ' . $event->ID . ' — ' . $event->post_status ); ?></option>
						<?php endforeach; ?></select></td>
					</tr>
					<tr>
						<th scope="row"><label for="mi_migration_activity_id">Nuova attività</label></th>
						<td><select id="mi_migration_activity_id" name="activity_id" required><option value="">Seleziona attività</option><?php foreach ( $activities as $activity ) : ?>
							<option value="<?php echo esc_attr( $activity->ID ); ?>"><?php echo esc_html( $activity->post_title . ' — ID ' . $activity->ID . ' — ' . $activity->post_status ); ?></option>
						<?php endforeach; ?></select></td>
					</tr>
					<tr>
						<th scope="row"><label for="mi_migration_confirmation">Conferma</label></th>
						<td><input id="mi_migration_confirmation" name="confirmation" type="text" class="regular-text" required autocomplete="off"><p class="description">Scrivi esattamente <code>MIGRA &lt;ID EVENTO&gt; A &lt;ID ATTIVITÀ&gt;</code>, per esempio <code>MIGRA 7342 A 7428</code>.</p></td>
					</tr>
				</table>
				<?php submit_button( 'Esegui migrazione sicura', 'primary', 'submit', true, array( 'data-mi-confirm-migration' => '1' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		check_admin_referer( 'mi_migrate_event_activity' );

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$activity_id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;
		$confirmation = isset( $_POST['confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation'] ) ) : '';
		$result = self::migrate( $event_id, $activity_id, $confirmation );

		if ( is_wp_error( $result ) ) {
			$notice = array( 'success' => false, 'message' => $result->get_error_message() );
		} else {
			$notice = array(
				'success' => true,
				'message' => sprintf(
					'Migrazione completata: evento %1$d collegato all’attività %2$d. Verificate %3$d iscrizioni, tutte conservate.',
					$result['event_id'],
					$result['activity_id'],
					$result['registrations_after']
				),
			);
		}
		set_transient( self::notice_key(), $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE . '&page=' . self::PAGE_SLUG ) );
		exit;
	}

	public static function migrate( $event_id, $activity_id, $confirmation ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'mi_migration_forbidden', 'La migrazione richiede un amministratore.' );
		}

		$event_id = absint( $event_id );
		$activity_id = absint( $activity_id );
		$expected_confirmation = sprintf( 'MIGRA %d A %d', $event_id, $activity_id );
		if ( $expected_confirmation !== trim( (string) $confirmation ) ) {
			return new WP_Error( 'mi_migration_confirmation_invalid', 'Conferma non valida. Nessuna modifica eseguita.' );
		}
		if ( MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event_id ) || ! in_array( get_post_status( $event_id ), array( 'publish', 'draft', 'private', 'mi_archived' ), true ) ) {
			return new WP_Error( 'mi_migration_event_invalid', 'Evento non valido. Nessuna modifica eseguita.' );
		}
		if ( MI_Event_Post_Type::ACTIVITY_TYPE !== get_post_type( $activity_id ) || ! in_array( get_post_status( $activity_id ), array( 'publish', 'draft', 'private' ), true ) ) {
			return new WP_Error( 'mi_migration_activity_invalid', 'Attività di destinazione non valida. Nessuna modifica eseguita.' );
		}

		$current_activity_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		if ( $current_activity_id === $activity_id ) {
			return new WP_Error( 'mi_migration_unchanged', 'L’evento è già collegato all’attività selezionata.' );
		}

		global $wpdb;
		$registrations_table = $wpdb->prefix . 'mi_registrations';
		$registrations_before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$registrations_table} WHERE event_id = %d", $event_id ) );
		if ( $registrations_before < 1 ) {
			return new WP_Error( 'mi_migration_not_required', 'L’evento non ha iscrizioni: cambia l’attività dal normale editor.' );
		}

		$updated = update_post_meta( $event_id, '_mi_activity_id', $activity_id );
		if ( false === $updated || $activity_id !== absint( get_post_meta( $event_id, '_mi_activity_id', true ) ) ) {
			return new WP_Error( 'mi_migration_update_failed', 'Associazione non aggiornata. Nessuna iscrizione è stata modificata.' );
		}

		$registrations_after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$registrations_table} WHERE event_id = %d", $event_id ) );
		if ( $registrations_after < $registrations_before ) {
			update_post_meta( $event_id, '_mi_activity_id', $current_activity_id );
			return new WP_Error( 'mi_migration_registration_check_failed', 'Controllo iscrizioni non superato: l’associazione precedente è stata ripristinata.' );
		}

		update_post_meta( $event_id, '_mi_needs_republish', '1' );
		add_post_meta(
			$event_id,
			'_mi_activity_migration_log',
			array(
				'from_activity_id'   => $current_activity_id,
				'to_activity_id'     => $activity_id,
				'registration_count' => $registrations_after,
				'user_id'            => get_current_user_id(),
				'migrated_at'        => current_time( 'mysql', true ),
			)
		);

		return array(
			'event_id'             => $event_id,
			'activity_id'          => $activity_id,
			'registrations_before' => $registrations_before,
			'registrations_after'  => $registrations_after,
		);
	}

	private static function notice_key() {
		return 'mi_activity_migration_notice_' . get_current_user_id();
	}
}
