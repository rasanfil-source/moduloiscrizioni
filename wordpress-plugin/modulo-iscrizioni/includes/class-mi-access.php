<?php

defined( 'ABSPATH' ) || exit;

final class MI_Access {
	private static $event_ids_cache = array();

	public static function boot() {
		add_action( 'transition_post_status', array( __CLASS__, 'initialize_event_group' ), 5, 3 );
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

	/** Assegna il gruppo autorizzato prima che save_post verifichi l'ambito dell'evento. */
	public static function initialize_event_group( $new_status, $old_status, $post ) {
		if ( 'auto-draft' !== $old_status || ! in_array( $new_status, array( 'draft', 'pending', 'publish', 'private', 'future' ), true ) || MI_Event_Post_Type::EVENT_TYPE !== $post->post_type ) return;
		$user_id = get_current_user_id();
		if ( ! $user_id || (int) $post->post_author !== $user_id || self::is_suspended( $user_id ) || ! current_user_can( 'mi_create_events' ) || ! current_user_can( 'mi_manage_events' ) ) return;
		if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) || get_post_meta( $post->ID, '_mi_activity_id', true ) ) return;
		if ( ! isset( $_POST['mi_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_event_nonce'] ) ), 'mi_save_event' ) ) return;
		$group_id = absint( $_POST['mi_activity_id'] ?? 0 );
		if ( ! $group_id || MI_Event_Post_Type::ACTIVITY_TYPE !== get_post_type( $group_id ) || ! self::can_access_activity( $group_id, $user_id ) ) return;
		update_post_meta( $post->ID, '_mi_activity_id', $group_id );
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
		return $user && ( user_can( $user, 'manage_options' ) || user_can( $user, 'mi_manage_all_events' ) );
	}

	public static function activity_ids( $user_id = 0 ) {
		$user_id = $user_id ?: get_current_user_id();
		if ( self::is_global_manager( $user_id ) ) {
			return 'ALL';
		}
		$user = get_user_by( 'id', $user_id );
		if ( $user && in_array( 'mi_assigned_event_manager', (array) $user->roles, true ) ) {
			$events = self::event_ids( $user_id );
			$cache_key = 'assigned-groups:' . $user_id . ':' . hash( 'sha256', implode( ',', $events ) );
			if ( isset( self::$event_ids_cache[ $cache_key ] ) ) return self::$event_ids_cache[ $cache_key ];
			$groups = array();
			foreach ( $events as $event_id ) {
				if ( MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event_id ) ) continue;
				$activity_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
				if ( $activity_id && MI_Event_Post_Type::ACTIVITY_TYPE === get_post_type( $activity_id ) ) $groups[] = $activity_id;
			}
			return self::$event_ids_cache[ $cache_key ] = array_values( array_unique( $groups ) );
		}
		$scope = get_user_meta( $user_id, '_mi_activity_scope', true );
		return array_values( array_filter( array_map( 'absint', is_array( $scope ) ? $scope : array() ) ) );
	}

	public static function can_access_activity( $activity_id, $user_id = 0 ) {
		$scope = self::activity_ids( $user_id );
		return 'ALL' === $scope || in_array( absint( $activity_id ), $scope, true );
	}

	public static function can_access_event( $event_id, $user_id = 0 ) {
		if ( self::is_suspended( $user_id ) ) return false;
		if ( self::is_global_manager( $user_id ) ) return true;
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
		if ( $user && in_array( 'mi_assigned_event_manager', (array) $user->roles, true ) ) {
			$scope = get_user_meta( $user->ID, '_mi_event_scope', true );
			return in_array( absint( $event_id ), array_map( 'absint', is_array( $scope ) ? $scope : array() ), true );
		}
		return self::can_access_activity( absint( get_post_meta( $event_id, '_mi_activity_id', true ) ), $user_id );
	}

	public static function event_ids( $user_id = 0 ) {
		if ( self::is_suspended( $user_id ) ) return array();
		$user_id = $user_id ?: get_current_user_id();
		$user = get_user_by( 'id', $user_id );
		if ( self::is_global_manager( $user_id ) ) return 'ALL';
		if ( $user && in_array( 'mi_assigned_event_manager', (array) $user->roles, true ) ) {
			$scope = array_values( array_unique( array_filter( array_map( 'absint', (array) get_user_meta( $user_id, '_mi_event_scope', true ) ) ) ) );
			$cache_key = 'assigned:' . $user_id . ':' . hash( 'sha256', implode( ',', $scope ) );
			if ( isset( self::$event_ids_cache[ $cache_key ] ) ) return self::$event_ids_cache[ $cache_key ];
			return self::$event_ids_cache[ $cache_key ] = $scope;
		}
		$groups = self::activity_ids( $user_id );
		if ( ! is_array( $groups ) || ! $groups ) return array();
		$groups = array_values( array_unique( array_filter( array_map( 'absint', $groups ) ) ) );
		$cache_key = 'groups:' . $user_id . ':' . hash( 'sha256', implode( ',', $groups ) );
		if ( isset( self::$event_ids_cache[ $cache_key ] ) ) return self::$event_ids_cache[ $cache_key ];
		return self::$event_ids_cache[ $cache_key ] = array_values( array_map( 'absint', get_posts( array(
			'post_type'              => MI_Event_Post_Type::EVENT_TYPE,
			'post_status'            => array( 'publish', 'draft', 'private', 'mi_archived' ),
			'numberposts'            => -1,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( array( 'key' => '_mi_activity_id', 'value' => $groups, 'compare' => 'IN', 'type' => 'NUMERIC' ) ),
		) ) ) );
	}

	public static function map_event_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'read_post', 'delete_post', 'edit_mi_event', 'read_mi_event', 'delete_mi_event' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$event_id = absint( $args[0] );
		if ( MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event_id ) ) {
			return $caps;
		}
		if ( self::is_suspended( $user_id ) ) return array( 'do_not_allow' );
		$read = in_array( $cap, array( 'read_post', 'read_mi_event' ), true );
		$event = get_post( $event_id );
		if ( $event && 'auto-draft' === $event->post_status && (int) $event->post_author === (int) $user_id ) {
			return $read ? array( 'read' ) : array( 'mi_manage_events' );
		}
		if ( ! self::can_access_event( $event_id, $user_id ) ) {
			return array( 'do_not_allow' );
		}
		if ( $read ) {
			return array( 'read' );
		}
		return array( 'mi_manage_events' );
	}

	public static function scope_event_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || MI_Event_Post_Type::EVENT_TYPE !== $query->get( 'post_type' ) || self::is_global_manager() ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( self::is_suspended( $user_id ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}
		$user = get_user_by( 'id', $user_id );
		if ( $user && in_array( 'mi_assigned_event_manager', (array) $user->roles, true ) ) {
			$scope = self::event_ids( $user_id );
			$query->set( 'post__in', $scope ? $scope : array( 0 ) );
			return;
		}
		$groups = self::activity_ids( $user_id );
		if ( ! is_array( $groups ) || ! $groups ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}
		$allowed_statuses = array( 'publish', 'draft', 'private', 'mi_archived' );
		$current_status = $query->get( 'post_status' );
		if ( empty( $current_status ) || 'any' === $current_status || ( is_array( $current_status ) && in_array( 'any', $current_status, true ) ) ) {
			$statuses = $allowed_statuses;
		} else {
			$requested = array_map( 'sanitize_key', (array) $current_status );
			$statuses = array_values( array_intersect( $allowed_statuses, $requested ) );
		}
		if ( ! $statuses ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}
		$query->set( 'post_status', $statuses );
		$group_clause = array( 'key' => '_mi_activity_id', 'value' => array_values( array_map( 'absint', $groups ) ), 'compare' => 'IN', 'type' => 'NUMERIC' );
		$existing_meta_query = $query->get( 'meta_query' );
		if ( is_array( $existing_meta_query ) && $existing_meta_query ) {
			$query->set( 'meta_query', array( 'relation' => 'AND', $existing_meta_query, $group_clause ) );
		} else {
			$query->set( 'meta_query', array( $group_clause ) );
		}
	}

	public static function guard_event_editor() {
		if ( ! isset( $_GET['post'], $_GET['action'] ) || 'edit' !== $_GET['action'] ) {
			return;
		}
		$post_id = absint( $_GET['post'] );
		if ( MI_Event_Post_Type::EVENT_TYPE === get_post_type( $post_id ) && ! self::can_access_event( $post_id ) ) {
			wp_die( esc_html__( 'Questo evento non rientra nel tuo ambito assegnato.', 'modulo-iscrizioni' ), 403 );
		}
	}

	public static function profile_scope( $user ) {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$roles = (array) $user->roles;
		if ( in_array( 'mi_assigned_event_manager', $roles, true ) ) {
			$selected = self::event_ids( $user->ID );
			if ( 'ALL' === $selected ) $selected = array();
			$events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private', 'mi_archived' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
			wp_nonce_field( 'mi_save_event_scope_' . $user->ID, 'mi_event_scope_nonce' );
			?>
			<h2>Modulo iscrizioni</h2>
			<table class="form-table"><tr><th>Eventi assegnati</th><td>
			<?php if ( ! $events ) : ?><p>Crea prima almeno un evento.</p><?php endif; ?>
			<?php foreach ( $events as $event ) : ?><label><input type="checkbox" name="mi_event_scope[]" value="<?php echo esc_attr( $event->ID ); ?>" <?php checked( in_array( (int) $event->ID, $selected, true ) ); ?>> <?php echo esc_html( $event->post_title ); ?></label><br><?php endforeach; ?>
			<p class="description">Usato soltanto per utenti con ruolo Gestore evento. Gli amministratori e i Gestori iscrizioni mantengono accesso globale.</p>
			</td></tr></table>
			<?php
			return;
		}
		if ( ! in_array( 'mi_group_manager', $roles, true ) ) return;
		$selected = self::activity_ids( $user->ID );
		if ( 'ALL' === $selected ) $selected = array();
		$activities = get_posts( array( 'post_type' => MI_Event_Post_Type::ACTIVITY_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		wp_nonce_field( 'mi_save_activity_scope_' . $user->ID, 'mi_activity_scope_nonce' );
		?>
		<h2>Modulo iscrizioni</h2>
		<table class="form-table"><tr><th>Gruppi assegnati</th><td>
		<?php if ( ! $activities ) : ?><p>Crea prima almeno un gruppo.</p><?php endif; ?>
		<?php foreach ( $activities as $activity ) : ?><label><input type="checkbox" name="mi_activity_scope[]" value="<?php echo esc_attr( $activity->ID ); ?>" <?php checked( in_array( $activity->ID, $selected, true ) ); ?>> <?php echo esc_html( $activity->post_title ); ?></label><br><?php endforeach; ?>
		<p class="description">Usato soltanto per utenti con ruolo Gestore gruppo. Gli amministratori e i Gestori iscrizioni mantengono accesso globale.</p>
		</td></tr></table>
		<?php
	}

	public static function save_profile_scope( $user_id ) {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) return;
		if ( in_array( 'mi_assigned_event_manager', (array) $user->roles, true ) ) {
			if ( ! isset( $_POST['mi_event_scope_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_event_scope_nonce'] ) ), 'mi_save_event_scope_' . $user_id ) ) return;
			$scope = isset( $_POST['mi_event_scope'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['mi_event_scope'] ) ) ) ) : array();
			update_user_meta( $user_id, '_mi_event_scope', $scope );
			self::$event_ids_cache = array();
			return;
		}
		if ( ! in_array( 'mi_group_manager', (array) $user->roles, true ) || ! isset( $_POST['mi_activity_scope_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_activity_scope_nonce'] ) ), 'mi_save_activity_scope_' . $user_id ) ) return;
		$scope = isset( $_POST['mi_activity_scope'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['mi_activity_scope'] ) ) ) ) : array();
		update_user_meta( $user_id, '_mi_activity_scope', $scope );
		self::$event_ids_cache = array();
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
