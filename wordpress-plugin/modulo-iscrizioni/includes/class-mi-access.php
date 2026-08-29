<?php

defined( 'ABSPATH' ) || exit;

final class MI_Access {
	public static function boot() {
		add_action( 'pre_get_posts', array( __CLASS__, 'scope_event_list' ) );
		add_action( 'admin_init', array( __CLASS__, 'guard_event_editor' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_scope' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_scope' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_scope' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_scope' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_filter( 'wp_authenticate_user', array( __CLASS__, 'block_suspended_user' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'trim_delegate_menu' ), 999 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_event_meta_cap' ), 10, 4 );
	}

	public static function block_suspended_user( $user, $password ) {
		if ( $user instanceof WP_User && get_user_meta( $user->ID, '_mi_access_suspended', true ) ) {
			return new WP_Error( 'mi_access_suspended', 'Accesso alla Segreteria eventi sospeso. Contatta un amministratore.' );
		}
		return $user;
	}

	public static function is_suspended( $user_id = 0 ) {
		return (bool) get_user_meta( $user_id ?: get_current_user_id(), '_mi_access_suspended', true );
	}

	public static function is_global_manager( $user_id = 0 ) {
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
		return $user && user_can( $user, 'manage_options' );
	}

	public static function activity_ids( $user_id = 0 ) {
		$user_id = $user_id ?: get_current_user_id();
		if ( self::is_global_manager( $user_id ) ) {
			return 'ALL';
		}
		$scope = get_user_meta( $user_id, '_mi_activity_scope', true );
		return array_values( array_filter( array_map( 'absint', is_array( $scope ) ? $scope : array() ) ) );
	}

	public static function can_access_activity( $activity_id, $user_id = 0 ) {
		$scope = self::activity_ids( $user_id );
		return 'ALL' === $scope || in_array( absint( $activity_id ), $scope, true );
	}

	public static function can_access_event( $event_id, $user_id = 0 ) {
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
		if ( $user && user_can( $user, 'mi_manage_all_events' ) ) return true;
		if ( $user && in_array( 'mi_event_operator', (array) $user->roles, true ) ) {
			$scope = self::event_ids( $user->ID );
			return in_array( absint( $event_id ), $scope, true );
		}
		return self::can_access_activity( absint( get_post_meta( $event_id, '_mi_activity_id', true ) ), $user_id );
	}

	public static function event_ids( $user_id = 0 ) {
		$user_id = $user_id ?: get_current_user_id();
		$user = get_user_by( 'id', $user_id );
		if ( $user && ( user_can( $user, 'manage_options' ) || user_can( $user, 'mi_manage_all_events' ) ) ) return 'ALL';
		$groups = self::activity_ids( $user_id );
		if ( ! is_array( $groups ) || ! $groups ) return array();
		return array_values( array_map( 'absint', get_posts( array(
			'post_type'              => MI_Event_Post_Type::EVENT_TYPE,
			'post_status'            => array( 'publish', 'draft', 'private' ),
			'numberposts'            => -1,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( array( 'key' => '_mi_activity_id', 'value' => $groups, 'compare' => 'IN', 'type' => 'NUMERIC' ) ),
		) ) ) );
	}

	public static function map_event_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_mi_event', 'read_mi_event', 'delete_mi_event' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$event_id = absint( $args[0] );
		if ( MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event_id ) ) {
			return array( 'do_not_allow' );
		}
		$event = get_post( $event_id );
		if ( $event && 'auto-draft' === $event->post_status && (int) $event->post_author === (int) $user_id ) {
			return 'read_mi_event' === $cap ? array( 'read' ) : array( 'mi_manage_events' );
		}
		if ( ! self::can_access_event( $event_id, $user_id ) ) {
			return array( 'do_not_allow' );
		}
		if ( 'read_mi_event' === $cap ) {
			return array( 'read' );
		}
		return array( 'mi_manage_events' );
	}

	public static function scope_event_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || MI_Event_Post_Type::EVENT_TYPE !== $query->get( 'post_type' ) || self::is_global_manager() ) {
			return;
		}
		$scope = self::activity_ids();
		if ( empty( $scope ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}
		$query->set( 'meta_query', array( array( 'key' => '_mi_activity_id', 'value' => $scope, 'compare' => 'IN', 'type' => 'NUMERIC' ) ) );
	}

	public static function guard_event_editor() {
		if ( ! isset( $_GET['post'], $_GET['action'] ) || 'edit' !== $_GET['action'] ) {
			return;
		}
		$post_id = absint( $_GET['post'] );
		if ( MI_Event_Post_Type::EVENT_TYPE === get_post_type( $post_id ) && ! self::can_access_event( $post_id ) ) {
			wp_die( esc_html__( 'Questo evento non appartiene ai gruppi assegnati.', 'modulo-iscrizioni' ), 403 );
		}
	}

	public static function profile_scope( $user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$selected = self::activity_ids( $user->ID );
		if ( 'ALL' === $selected ) {
			$selected = array();
		}
		$activities = get_posts( array( 'post_type' => MI_Event_Post_Type::ACTIVITY_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		wp_nonce_field( 'mi_save_activity_scope_' . $user->ID, 'mi_activity_scope_nonce' );
		?>
		<h2>Modulo iscrizioni</h2>
		<table class="form-table"><tr><th>Gruppi assegnati</th><td>
		<?php if ( ! $activities ) : ?><p>Crea prima almeno un gruppo.</p><?php endif; ?>
		<?php foreach ( $activities as $activity ) : ?><label><input type="checkbox" name="mi_activity_scope[]" value="<?php echo esc_attr( $activity->ID ); ?>" <?php checked( in_array( $activity->ID, $selected, true ) ); ?>> <?php echo esc_html( $activity->post_title ); ?></label><br><?php endforeach; ?>
		<p class="description">Usato soltanto per utenti con ruolo Gestore iscrizioni. Gli amministratori mantengono accesso globale.</p>
		</td></tr></table>
		<?php
	}

	public static function save_profile_scope( $user_id ) {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mi_activity_scope_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_activity_scope_nonce'] ) ), 'mi_save_activity_scope_' . $user_id ) ) {
			return;
		}
		$scope = isset( $_POST['mi_activity_scope'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['mi_activity_scope'] ) ) ) ) : array();
		update_user_meta( $user_id, '_mi_activity_scope', $scope );
	}

	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof WP_User || ( ! user_can( $user, 'mi_portal_access' ) && ! user_can( $user, 'manage_options' ) ) ) {
			return $redirect_to;
		}

		// La Segreteria è una funzione del sito: non deve sostituire la normale destinazione di WordPress.
		// Il pannello viene aperto automaticamente soltanto dal suo modulo di accesso dedicato.
		$destinazione_richiesta = wp_validate_redirect( (string) $requested, '' );
		if ( $destinazione_richiesta && false !== strpos( $destinazione_richiesta, 'mi_portal=1' ) ) {
			return $destinazione_richiesta;
		}

		return $redirect_to;
	}

	public static function trim_delegate_menu() {
		if ( self::is_global_manager() || ! current_user_can( 'mi_manage_events' ) ) {
			return;
		}
		remove_menu_page( 'edit-comments.php' );
	}
}
