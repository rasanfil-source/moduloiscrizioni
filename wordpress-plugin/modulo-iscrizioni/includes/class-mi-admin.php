<?php

defined( 'ABSPATH' ) || exit;

final class MI_Admin {
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mi_archive_event', array( __CLASS__, 'archive_event' ) );
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
		$detail_id = isset( $_GET['registration_id'] ) ? absint( $_GET['registration_id'] ) : 0;
		$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$per_page = 50;
		$offset = ( $page - 1 ) * $per_page;
		$scope = MI_Access::activity_ids();
		$allowed_events = 'ALL' === $scope ? array() : get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) );
		if ( $event_id && ! MI_Access::can_access_event( $event_id ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		if ( $event_id ) {
			$where = $wpdb->prepare( 'WHERE event_id = %d', $event_id );
		} elseif ( 'ALL' !== $scope ) {
			$where = 'WHERE event_id IN (' . implode( ',', array_map( 'absint', $allowed_events ?: array( 0 ) ) ) . ')';
		} else {
			$where = '';
		}
		$rows = $wpdb->get_results( "SELECT id, order_code, event_id, status, workspace_status, buyer_first_name, buyer_last_name, buyer_email, total_qty, created_at FROM {$table} {$where} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}", ARRAY_A );
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

	public static function outbox_page() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		$registrations = $wpdb->prefix . 'mi_registrations';
		$scope = MI_Access::activity_ids();
		if ( 'ALL' === $scope ) {
			$where = '';
		} else {
			$allowed_events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) );
			$where = 'WHERE r.event_id IN (' . implode( ',', array_map( 'absint', $allowed_events ?: array( 0 ) ) ) . ')';
		}
		$rows = $wpdb->get_results( "SELECT o.id, o.registration_id, o.recipient, o.template_type, o.status, o.created_at FROM {$table} o INNER JOIN {$registrations} r ON r.id = o.registration_id {$where} ORDER BY o.id DESC LIMIT 100", ARRAY_A );
		?>
		<div class="wrap"><h1>Email in anteprima</h1><p>Nessuna email viene spedita in questa fase.</p>
		<table class="widefat striped"><thead><tr><th>ID</th><th>Iscrizione</th><th>Destinatario</th><th>Template</th><th>Stato</th><th>Data UTC</th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="6">Outbox vuota.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['registration_id'] ); ?></td><td><?php echo esc_html( $row['recipient'] ); ?></td><td><?php echo esc_html( $row['template_type'] ); ?></td><td><?php echo esc_html( $row['status'] ); ?></td><td><?php echo esc_html( $row['created_at'] ); ?></td></tr><?php endforeach; ?>
		</tbody></table></div>
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
