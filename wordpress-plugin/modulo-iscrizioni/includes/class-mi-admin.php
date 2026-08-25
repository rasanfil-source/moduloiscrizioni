<?php

defined( 'ABSPATH' ) || exit;

final class MI_Admin {
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mi_archive_event', array( __CLASS__, 'archive_event' ) );
		add_action( 'admin_post_mi_export_registrations', array( __CLASS__, 'export_registrations' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'event_row_actions' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_publication' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'publication_notice' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE,
			'Iscrizioni',
			'Iscrizioni',
			'mi_view_registrations',
			'mi-registrations',
			array( __CLASS__, 'registrations_page' )
		);
		add_submenu_page(
			'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE,
			'Email in anteprima',
			'Email in anteprima',
			'mi_view_registrations',
			'mi-email-outbox',
			array( __CLASS__, 'outbox_page' )
		);
	}

	public static function registrations_page() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'mi_registrations';
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		$search = isset( $_GET['mi_search'] ) ? sanitize_text_field( wp_unslash( $_GET['mi_search'] ) ) : '';
		$detail_id = isset( $_GET['registration_id'] ) ? absint( $_GET['registration_id'] ) : 0;
		$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$per_page = 50;
		$offset = ( $page - 1 ) * $per_page;
		$scope = MI_Access::activity_ids();
		$allowed_events = 'ALL' === $scope ? array() : get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) );
		if ( $event_id && ! MI_Access::can_access_event( $event_id ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		$conditions = array();
		$parameters = array();
		if ( $event_id ) {
			$conditions[] = 'event_id = %d';
			$parameters[] = $event_id;
		} elseif ( 'ALL' !== $scope ) {
			$conditions[] = 'event_id IN (' . implode( ',', array_map( 'absint', $allowed_events ?: array( 0 ) ) ) . ')';
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$conditions[] = '(order_code LIKE %s OR buyer_first_name LIKE %s OR buyer_last_name LIKE %s OR buyer_email LIKE %s)';
			$parameters = array_merge( $parameters, array( $like, $like, $like, $like ) );
		}
		$where = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
		if ( $parameters ) {
			$where = $wpdb->prepare( $where, $parameters );
		}
		$rows = $wpdb->get_results( "SELECT id, order_code, event_id, status, workspace_status, buyer_first_name, buyer_last_name, buyer_email, total_qty, created_at FROM {$table} {$where} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}", ARRAY_A );
		$visible_events = 'ALL' === $scope ? get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) : get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'post__in' => $allowed_events ?: array( 0 ), 'orderby' => 'title', 'order' => 'ASC' ) );
		$detail = null;
		$participants = array();
		if ( $detail_id ) {
			$detail = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $detail_id ), ARRAY_A );
			if ( ! $detail || ! MI_Access::can_access_event( (int) $detail['event_id'] ) ) {
				wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
			}
			$participants_table = $wpdb->prefix . 'mi_participants';
			$participants = $wpdb->get_results( $wpdb->prepare( "SELECT id, first_name, last_name, extra_json FROM {$participants_table} WHERE registration_id = %d ORDER BY id", $detail_id ), ARRAY_A );
		}
		?>
		<div class="wrap"><h1>Iscrizioni</h1>
		<p>Registro locale con replica firmata sul registro Workspace. Le email restano soltanto in anteprima.</p>
		<form method="get" style="margin:16px 0">
		<input type="hidden" name="post_type" value="<?php echo esc_attr( MI_Event_Post_Type::EVENT_TYPE ); ?>">
		<input type="hidden" name="page" value="mi-registrations">
		<label for="mi-event-filter">Evento</label>
		<select id="mi-event-filter" name="event_id"><option value="0">Tutti gli eventi accessibili</option><?php foreach ( $visible_events as $visible_event ) : ?><option value="<?php echo esc_attr( $visible_event->ID ); ?>" <?php selected( $event_id, $visible_event->ID ); ?>><?php echo esc_html( $visible_event->post_title ); ?></option><?php endforeach; ?></select>
		<label for="mi-search">Ricerca</label>
		<input id="mi-search" type="search" name="mi_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Codice, referente o email">
		<button class="button">Filtra</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE . '&page=mi-registrations' ) ); ?>">Azzera</a>
		<?php $export_url = wp_nonce_url( add_query_arg( array( 'action' => 'mi_export_registrations', 'event_id' => $event_id, 'mi_search' => $search ), admin_url( 'admin-post.php' ) ), 'mi_export_registrations' ); ?>
		<a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>">Esporta CSV filtrato</a>
		</form>
		<table class="widefat striped"><thead><tr><th>Codice</th><th>Evento</th><th>Stato</th><th>Workspace</th><th>Referente</th><th>Email</th><th>Persone</th><th>Data UTC</th><th></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="9">Nessuna iscrizione.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?>
		<?php $detail_url = add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-registrations', 'registration_id' => (int) $row['id'] ), admin_url( 'edit.php' ) ); ?>
		<tr><td><code><?php echo esc_html( $row['order_code'] ); ?></code></td><td><?php echo esc_html( get_the_title( (int) $row['event_id'] ) ); ?></td><td><?php echo esc_html( $row['status'] ); ?></td><td><?php echo esc_html( $row['workspace_status'] ); ?></td><td><?php echo esc_html( $row['buyer_first_name'] . ' ' . $row['buyer_last_name'] ); ?></td><td><?php echo esc_html( $row['buyer_email'] ); ?></td><td><?php echo esc_html( $row['total_qty'] ); ?></td><td><?php echo esc_html( $row['created_at'] ); ?></td><td><a href="<?php echo esc_url( $detail_url ); ?>">Dettagli</a></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php if ( $detail ) : ?>
		<hr><h2>Dettaglio iscrizione <code><?php echo esc_html( $detail['order_code'] ); ?></code></h2>
		<table class="widefat striped" style="max-width:900px"><tbody>
		<tr><th scope="row">Evento</th><td><?php echo esc_html( get_the_title( (int) $detail['event_id'] ) ); ?></td></tr>
		<tr><th scope="row">Stato</th><td><?php echo esc_html( $detail['status'] ); ?></td></tr>
		<tr><th scope="row">Workspace</th><td><?php echo esc_html( $detail['workspace_status'] ); ?></td></tr>
		<tr><th scope="row">Referente</th><td><?php echo esc_html( $detail['buyer_first_name'] . ' ' . $detail['buyer_last_name'] ); ?></td></tr>
		<tr><th scope="row">Email</th><td><?php echo esc_html( $detail['buyer_email'] ); ?></td></tr>
		<tr><th scope="row">Cellulare</th><td><?php echo esc_html( $detail['buyer_phone'] ); ?></td></tr>
		</tbody></table>
		<h3>Partecipanti</h3>
		<?php if ( ! $participants ) : ?><p>Nessun partecipante associato.</p><?php endif; ?>
		<?php $catalog = MI_Field_Schema::catalog(); ?>
		<?php foreach ( $participants as $position => $participant ) : ?>
		<?php $answers = json_decode( (string) $participant['extra_json'], true ); $answers = is_array( $answers ) ? $answers : array(); ?>
		<h4><?php echo esc_html( ( $position + 1 ) . '. ' . $participant['first_name'] . ' ' . $participant['last_name'] ); ?></h4>
		<?php if ( ! $answers ) : ?><p>Nessun dato aggiuntivo raccolto.</p><?php else : ?>
		<table class="widefat striped" style="max-width:900px"><tbody>
		<?php foreach ( $answers as $key => $value ) : ?>
		<?php $label = isset( $catalog[ $key ]['label'] ) ? $catalog[ $key ]['label'] : 'Dato aggiuntivo'; ?>
		<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo nl2br( esc_html( (string) $value ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php endif; ?>
		<?php endforeach; ?>
		<?php endif; ?>
		</div>
		<?php
	}

	public static function export_registrations() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		check_admin_referer( 'mi_export_registrations' );
		global $wpdb;
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		$search = isset( $_GET['mi_search'] ) ? sanitize_text_field( wp_unslash( $_GET['mi_search'] ) ) : '';
		if ( $event_id && ! MI_Access::can_access_event( $event_id ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		$scope = MI_Access::activity_ids();
		$allowed_events = 'ALL' === $scope ? array() : get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) );
		$conditions = array();
		$parameters = array();
		if ( $event_id ) {
			$conditions[] = 'r.event_id = %d';
			$parameters[] = $event_id;
		} elseif ( 'ALL' !== $scope ) {
			$conditions[] = 'r.event_id IN (' . implode( ',', array_map( 'absint', $allowed_events ?: array( 0 ) ) ) . ')';
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$conditions[] = '(r.order_code LIKE %s OR r.buyer_first_name LIKE %s OR r.buyer_last_name LIKE %s OR r.buyer_email LIKE %s)';
			$parameters = array_merge( $parameters, array( $like, $like, $like, $like ) );
		}
		$where = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
		if ( $parameters ) {
			$where = $wpdb->prepare( $where, $parameters );
		}
		$registrations = $wpdb->prefix . 'mi_registrations';
		$participants = $wpdb->prefix . 'mi_participants';
		$rows = $wpdb->get_results( "SELECT r.order_code, r.event_id, r.status, r.workspace_status, r.buyer_first_name, r.buyer_last_name, r.buyer_email, r.buyer_phone, r.created_at, p.first_name, p.last_name, p.extra_json FROM {$registrations} r LEFT JOIN {$participants} p ON p.registration_id = r.id {$where} ORDER BY r.id DESC, p.id ASC", ARRAY_A );
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="iscrizioni-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" );
		$catalog = MI_Field_Schema::catalog();
		$extra_keys = array();
		foreach ( $rows as $row ) {
			$answers = json_decode( (string) $row['extra_json'], true );
			if ( is_array( $answers ) ) {
				$extra_keys = array_values( array_unique( array_merge( $extra_keys, array_keys( $answers ) ) ) );
			}
		}
		$headers = array( 'Codice iscrizione', 'Evento', 'Stato', 'Stato Workspace', 'Nome referente', 'Cognome referente', 'Email referente', 'Cellulare referente', 'Data UTC', 'Nome partecipante', 'Cognome partecipante' );
		foreach ( $extra_keys as $key ) {
			$headers[] = isset( $catalog[ $key ]['label'] ) ? $catalog[ $key ]['label'] : 'Dato aggiuntivo';
		}
		fputcsv( $output, $headers, ';' );
		foreach ( $rows as $row ) {
			$answers = json_decode( (string) $row['extra_json'], true );
			$answers = is_array( $answers ) ? $answers : array();
			$line = array( $row['order_code'], get_the_title( (int) $row['event_id'] ), $row['status'], $row['workspace_status'], $row['buyer_first_name'], $row['buyer_last_name'], $row['buyer_email'], $row['buyer_phone'], $row['created_at'], $row['first_name'], $row['last_name'] );
			foreach ( $extra_keys as $key ) {
				$line[] = isset( $answers[ $key ] ) ? $answers[ $key ] : '';
			}
			fputcsv( $output, array_map( array( __CLASS__, 'safe_csv_value' ), $line ), ';' );
		}
		fclose( $output );
		exit;
	}

	private static function safe_csv_value( $value ) {
		$value = (string) $value;
		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
	}

	public static function outbox_page() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		$registrations = $wpdb->prefix . 'mi_registrations';
		$detail_id = isset( $_GET['email_id'] ) ? absint( $_GET['email_id'] ) : 0;
		$scope = MI_Access::activity_ids();
		if ( 'ALL' === $scope ) {
			$where = '';
		} else {
			$allowed_events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) );
			$where = 'WHERE r.event_id IN (' . implode( ',', array_map( 'absint', $allowed_events ?: array( 0 ) ) ) . ')';
		}
		$rows = $wpdb->get_results( "SELECT o.id, o.registration_id, o.recipient, o.template_type, o.status, o.created_at FROM {$table} o INNER JOIN {$registrations} r ON r.id = o.registration_id {$where} ORDER BY o.id DESC LIMIT 100", ARRAY_A );
		$detail = null;
		if ( $detail_id ) {
			$detail = $wpdb->get_row( $wpdb->prepare( "SELECT o.*, r.event_id FROM {$table} o INNER JOIN {$registrations} r ON r.id = o.registration_id WHERE o.id = %d", $detail_id ), ARRAY_A );
			if ( ! $detail || ! MI_Access::can_access_event( (int) $detail['event_id'] ) ) {
				wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
			}
		}
		?>
		<div class="wrap"><h1>Email in anteprima</h1><p>Nessuna email viene spedita in questa fase.</p>
		<table class="widefat striped"><thead><tr><th>ID</th><th>Iscrizione</th><th>Destinatario</th><th>Modello</th><th>Stato</th><th>Data UTC</th><th></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="7">Coda vuota.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?><?php $preview_url = add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-email-outbox', 'email_id' => (int) $row['id'] ), admin_url( 'edit.php' ) ); ?><tr><td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['registration_id'] ); ?></td><td><?php echo esc_html( $row['recipient'] ); ?></td><td><?php echo esc_html( $row['template_type'] ); ?></td><td><?php echo esc_html( $row['status'] ); ?></td><td><?php echo esc_html( $row['created_at'] ); ?></td><td><a href="<?php echo esc_url( $preview_url ); ?>">Apri anteprima</a></td></tr><?php endforeach; ?>
		</tbody></table>
		<?php if ( $detail ) : ?><?php $payload = json_decode( (string) $detail['payload_json'], true ); $preview = is_array( $payload ) && isset( $payload['email_preview'] ) && is_array( $payload['email_preview'] ) ? $payload['email_preview'] : array(); ?>
		<hr><h2>Anteprima email conservata</h2>
		<?php if ( ! $preview ) : ?><p>Questa voce precede l’introduzione delle anteprime complete.</p><?php else : ?>
		<p><strong>Oggetto:</strong> <?php echo esc_html( $preview['oggetto'] ?? '' ); ?></p>
		<p><strong>Preheader:</strong> <?php echo esc_html( $preview['preheader'] ?? '' ); ?></p>
		<div class="card" style="max-width:900px"><div><?php echo wp_kses_post( $preview['html'] ?? '' ); ?></div><hr><p><?php echo nl2br( esc_html( $preview['footer'] ?? '' ) ); ?></p></div>
		<h3>Versione testo semplice</h3><pre style="white-space:pre-wrap;max-width:900px"><?php echo esc_html( $preview['testo'] ?? '' ); ?></pre>
		<p><small>Revisione: <code><?php echo esc_html( substr( (string) ( $preview['revisione'] ?? '' ), 0, 12 ) ); ?></code></small></p>
		<?php endif; ?><?php endif; ?>
		</div>
		<?php
	}

	public static function event_row_actions( $actions, $post ) {
		if ( MI_Event_Post_Type::EVENT_TYPE !== $post->post_type || 'mi_archived' === $post->post_status || ! current_user_can( 'mi_manage_events' ) ) {
			return $actions;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=mi_archive_event&event_id=' . $post->ID ), 'mi_archive_event_' . $post->ID );
		$actions['mi_archive'] = '<a href="' . esc_url( $url ) . '">Archivia</a>';
		return $actions;
	}

	public static function archive_event() {
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		check_admin_referer( 'mi_archive_event_' . $event_id );
		if ( ! $event_id || ! current_user_can( 'mi_manage_events' ) || MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event_id ) || ! MI_Access::can_access_event( $event_id ) ) {
			wp_die( esc_html__( 'Operazione non consentita.', 'modulo-iscrizioni' ) );
		}
		wp_update_post( array( 'ID' => $event_id, 'post_status' => 'mi_archived' ) );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE ) );
		exit;
	}

	public static function guard_publication( $data, $postarr ) {
		if ( MI_Event_Post_Type::EVENT_TYPE !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) ) {
			return $data;
		}
		if ( ! isset( $_POST['mi_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_event_nonce'] ) ), 'mi_save_event' ) ) {
			return $data;
		}
		$activity_id = isset( $_POST['mi_activity_id'] ) ? absint( $_POST['mi_activity_id'] ) : 0;
		$opens = isset( $_POST['mi_registration_opens_at'] ) ? sanitize_text_field( wp_unslash( $_POST['mi_registration_opens_at'] ) ) : '';
		$closes = isset( $_POST['mi_registration_closes_at'] ) ? sanitize_text_field( wp_unslash( $_POST['mi_registration_closes_at'] ) ) : '';
		$valid_dates = preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $opens ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $closes ) && $closes > $opens;
		$has_ticket = ! empty( $_POST['mi_ticket_code'][0] ) && ! empty( $_POST['mi_ticket_name'][0] );
		if ( ! $activity_id || MI_Event_Post_Type::ACTIVITY_TYPE !== get_post_type( $activity_id ) || ! $valid_dates || ! $has_ticket ) {
			$data['post_status'] = 'draft';
			set_transient( 'mi_publication_error_' . get_current_user_id(), '1', MINUTE_IN_SECONDS );
		}
		return $data;
	}

	public static function publication_notice() {
		$key = 'mi_publication_error_' . get_current_user_id();
		if ( get_transient( $key ) ) {
			delete_transient( $key );
			echo '<div class="notice notice-error"><p>Evento mantenuto in bozza: completa attività, date valide e almeno una tipologia di iscrizione.</p></div>';
		}
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->post_type, array( MI_Event_Post_Type::EVENT_TYPE, MI_Event_Post_Type::ACTIVITY_TYPE ), true ) ) {
			echo '<div class="notice notice-info"><p><strong>Vertical slice:</strong> le email sono salvate soltanto in anteprima e non vengono inviate.</p></div>';
		}
	}
}
