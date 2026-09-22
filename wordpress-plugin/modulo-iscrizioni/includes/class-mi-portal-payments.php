<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-mi-booking-search.php';

/** Interfaccia privata: WordPress autorizza, DB_MODULI convalida e registra. */
final class MI_Portal_Payments {
	public static function boot() {
		add_action( 'wp_ajax_mi_portal_payment', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_mi_portal_payment', array( __CLASS__, 'ajax' ) );
	}
	public static function allowed() {
		return is_user_logged_in() && ! MI_Access::is_suspended() && ( current_user_can( 'manage_options' ) || ( current_user_can( 'mi_portal_access' ) && current_user_can( 'mi_manage_payments' ) ) );
	}
	public static function registration( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,event_id,order_code,status FROM {$wpdb->prefix}mi_registrations WHERE id=%d", absint( $id ) ), ARRAY_A );
		return $row && MI_Access::can_access_event( (int) $row['event_id'] ) ? $row : null;
	}
	public static function search( $query, $page = 1, $event_id = 0 ) {
		global $wpdb;
		$query = mb_substr( sanitize_text_field( $query ), 0, 80 );
		if ( mb_strlen( $query ) < 2 ) return array( 'prenotazioni' => array(), 'has_more' => false );
		$scope = MI_Access::event_ids();
		if ( ( 'ALL' !== $scope && ! $scope ) || ( $event_id && ! MI_Access::can_access_event( $event_id ) ) ) return array( 'prenotazioni' => array(), 'has_more' => false );
		$where = 'ALL' === $scope ? '1=1' : 'event_id IN (' . implode( ',', array_map( 'absint', $scope ) ) . ')';
		if ( $event_id ) $where .= $wpdb->prepare( ' AND r.event_id=%d', $event_id );
		// Escludi gli eventi dichiarati gratuiti prima di paginare, anche cercando per codice.
		$where .= " AND NOT EXISTS (SELECT 1 FROM {$wpdb->prefix}postmeta free_event WHERE free_event.post_id=r.event_id AND free_event.meta_key='_mi_pricing_mode' AND free_event.meta_value='ZERO')";
		$offset = ( max( 1, (int) $page ) - 1 ) * 30;
		$match = MI_Booking_Search::sql( $query );
		$rows = $wpdb->get_results( "SELECT r.id,r.event_id,r.order_code,r.buyer_first_name,r.buyer_last_name,event_post.post_title AS event_title FROM {$wpdb->prefix}mi_registrations r LEFT JOIN {$wpdb->posts} event_post ON event_post.ID=r.event_id WHERE {$where} AND {$match} ORDER BY r.buyer_last_name,r.buyer_first_name,r.id LIMIT 31 OFFSET {$offset}", ARRAY_A );
		if ( $wpdb->last_error ) return new WP_Error( 'mi_payment_search', 'Ricerca non disponibile. Riprova.' );
		$has_more = count( $rows ) > 30; $rows = array_slice( $rows, 0, 30 );
		$names = array(); $matches = array();
		if ( $rows ) {
			$ids = implode( ',', array_map( 'intval', array_column( $rows, 'id' ) ) );
			foreach ( $wpdb->get_results( "SELECT id,registration_id,first_name,last_name FROM {$wpdb->prefix}mi_participants WHERE registration_id IN ({$ids}) ORDER BY id", ARRAY_A ) as $person ) {
				$name = trim( $person['first_name'] . ' ' . $person['last_name'] );
				$names[$person['registration_id']][] = $name;
				if ( MI_Booking_Search::matches( array( $name ), $query ) ) $matches[$person['registration_id']][] = array( 'id' => (int) $person['id'], 'name' => $name );
			}
			if ( $wpdb->last_error ) return new WP_Error( 'mi_payment_search', 'Nominativi non disponibili. Riprova.' );
		}
		$results = array_map( static function ( $row ) use ( $names, $matches ) {
			$found = $matches[$row['id']] ?? array();
			return array( 'id' => (int) $row['id'], 'nome' => $found ? implode( ', ', array_column( $found, 'name' ) ) : implode( ', ', $names[$row['id']] ?? array( trim( $row['buyer_first_name'] . ' ' . $row['buyer_last_name'] ) ) ), 'matched_participant_ids' => array_column( $found, 'id' ), 'codice' => $row['order_code'], 'evento' => html_entity_decode( (string) $row['event_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'partecipanti' => $names[$row['id']] ?? array() );
		}, $rows ?: array() );
		return array( 'prenotazioni' => $results, 'has_more' => $has_more );
	}
	public static function ajax() {
		nocache_headers();
		if ( ! self::allowed() ) wp_send_json_error( array( 'message' => 'Sessione scaduta o accesso non consentito. Accedi nuovamente al portale.' ), 403 );
		if ( ! check_ajax_referer( 'mi_portal_payment', 'nonce', false ) ) wp_send_json_error( array( 'message' => 'Sessione scaduta. Riapri la sezione Pagamenti.' ), 403 );
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) );
		if ( 'search' === $operation ) {
			$result = self::search( wp_unslash( $_POST['query'] ?? '' ), absint( $_POST['page'] ?? 1 ), absint( $_POST['event_id'] ?? 0 ) );
			if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
			wp_send_json_success( $result );
		}
		if ( ! in_array( $operation, array( 'detail', 'save' ), true ) ) wp_send_json_error( array( 'message' => 'Operazione non valida.' ), 400 );
		$row = self::registration( $_POST['registration_id'] ?? 0 );
		if ( ! $row ) wp_send_json_error( array( 'message' => 'Prenotazione non accessibile: verifica le iniziative assegnate.' ), 403 );
		$payload = array( 'order_code' => $row['order_code'], 'event_id' => (string) $row['event_id'] );
		if ( 'save' === $operation ) {
			$request_id = sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );
			if ( ! preg_match( '/^[a-f0-9-]{36}$/i', $request_id ) ) wp_send_json_error( array( 'message' => 'Identificativo del movimento non valido.' ), 400 );
			$payload['request_id'] = 'wp_' . get_current_user_id() . '_' . $request_id;
			$payload['operator_label'] = mb_substr( 'WP#' . get_current_user_id() . ' · ' . wp_get_current_user()->display_name, 0, 100 );
			if ( isset( $_POST['participant_ids'] ) ) $payload['participant_ids'] = wp_unslash( $_POST['participant_ids'] );
			foreach ( array( 'data', 'tipo', 'rata', 'importo', 'metodo', 'riferimento', 'nota' ) as $field ) {
				$payload[ $field ] = mb_substr( sanitize_textarea_field( wp_unslash( $_POST[ $field ] ?? '' ) ), 0, 'nota' === $field ? 500 : 120 );
			}
		}
		$result = 'save' === $operation ? MI_Payment_Ledger::save( (int) $row['id'], $payload ) : MI_Payment_Ledger::detail( (int) $row['id'] );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message(), 'uncertain' => 'save' === $operation ), 502 );
		wp_send_json_success( $result );
	}
	public static function render() {
		if ( ! self::allowed() ) { echo '<p class="mi-portal-notice mi-portal-error">Non disponi del permesso per registrare pagamenti.</p>'; return; }
		$scope = MI_Access::event_ids();
		$access_label = 'Hai accesso a tutte le iniziative';
		if ( 'ALL' !== $scope ) {
			$events = $scope ? get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'post__in' => $scope, 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) ) : array();
			$access_label = $events ? 'Hai accesso alle iniziative: ' . implode( ', ', array_map( static function ( $event ) { return html_entity_decode( $event->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }, $events ) ) : 'Non hai iniziative assegnate';
		}
		?>
		<section class="mi-payments" data-mi-payments data-event="<?php echo esc_attr( absint( $_GET['mi_portal_event'] ?? 0 ) ); ?>" data-initial-order="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['mi_order'] ?? '' ) ) ); ?>" data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mi_portal_payment' ) ); ?>">
		<h2>Inserisci un pagamento</h2><p>Cerca una prenotazione, controlla il saldo e registra il movimento.</p>
		<p class="mi-portal-muted"><?php echo esc_html( $access_label ); ?></p>
		<fieldset data-search-fields><label for="mi-payment-search">Nome della persona, email, telefono o codice prenotazione</label>
		<div class="mi-payment-search"><input id="mi-payment-search" type="search" autocomplete="off" maxlength="80" aria-describedby="mi-payment-search-status"><button type="button" data-clear class="mi-secondary" hidden>Cancella ricerca</button></div>
		<p id="mi-payment-search-status" role="status" aria-live="polite">Digita almeno due caratteri.</p><div data-results class="mi-booking-list"></div></fieldset>
		<?php if ( current_user_can( 'mi_view_registrations' ) || current_user_can( 'manage_options' ) ) : ?><p><a class="mi-secondary" href="<?php echo esc_url( add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-payments', 'payment_event_id' => absint( $_GET['mi_portal_event'] ?? 0 ) ), admin_url( 'edit.php' ) ) ); ?>">Report pagamenti e rimborsi</a></p><?php endif; ?>
		<p data-status class="mi-payment-status" role="status" aria-live="polite"></p>
		<button type="button" data-retry-detail class="mi-secondary" hidden>Riprova caricamento saldo</button>
		<form data-payment-form novalidate hidden>
		<section class="mi-payment-summary" aria-label="Riepilogo prenotazione"><h3 data-person></h3><p data-event></p><p data-order class="mi-portal-muted"></p><div class="mi-payment-totals"><p>Totale<strong data-total></strong></p><p>Già versato<strong data-paid></strong></p><p>Da versare<strong data-balance></strong></p></div></section>
		<section data-payment-history aria-label="Storico movimenti"></section><fieldset data-payment-fields><div class="mi-payment-grid">
		<label>Importo in euro<input name="importo" inputmode="decimal" required pattern="[0-9]+([,.][0-9]{1,2})?" placeholder="0,00" aria-describedby="mi-payment-error"></label>
		<label>Metodo<select name="metodo"><option value="BONIFICO">Bonifico</option><option value="CARTA">Carta</option><option value="CONTANTE">Contanti</option></select></label>
		<label>Data effettiva<input name="data" type="date" required aria-describedby="mi-payment-error"></label>
		<label>Movimento<select name="tipo"><option value="INCASSO">Incasso</option><option value="RIMBORSO">Rimborso</option><option value="STORNO">Storno</option></select></label>
		<label data-installment-label hidden>Versamento<select name="rata"><option value="DEPOSIT">Caparra</option><option value="BALANCE">Saldo</option><option value="FULL">Totale</option></select></label>
		<label>Riferimento<input name="riferimento" maxlength="120" placeholder="Bonifico o ricevuta"></label>
		<details class="mi-payment-wide"><summary>Nota amministrativa</summary><textarea name="nota" aria-label="Nota amministrativa" rows="3" maxlength="500"></textarea></details>
		</div><label class="mi-check"><input type="checkbox" name="conferma" required aria-describedby="mi-payment-error"> Ho verificato prenotazione, importo, tipo e data del movimento.</label></fieldset>
		<p id="mi-payment-error" role="alert"></p><div class="mi-payment-actions"><button type="submit" class="mi-primary" data-save>Registra pagamento</button><button type="button" class="mi-secondary" data-new hidden>Inserisci un altro pagamento</button></div>
		</form><noscript>Per cercare e registrare un pagamento è necessario attivare JavaScript.</noscript></section>
		<?php
	}
}
