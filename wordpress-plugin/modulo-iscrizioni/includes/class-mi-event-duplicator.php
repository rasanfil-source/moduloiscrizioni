<?php

defined( 'ABSPATH' ) || exit;

final class MI_Event_Duplicator {
	public static function duplicate( $source_id, $request_id ) {
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( $source_id ); if ( is_wp_error( $lease ) ) return $lease; }
		global $wpdb;
		$source = get_post( $source_id );
		if ( ! $source || MI_Event_Post_Type::EVENT_TYPE !== $source->post_type || 'trash' === $source->post_status || ! MI_Access::can_access_event( $source_id ) || ( ! current_user_can( 'mi_create_events' ) && ! current_user_can( 'manage_options' ) ) ) return new WP_Error( 'access', 'Non puoi duplicare questo evento.' );
		if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $request_id ) ) return new WP_Error( 'request', 'Ricarica la pagina e riprova.' );
		$lock = 'mi_duplicate_' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) return new WP_Error( 'busy', 'Duplicazione già in corso. Riprova tra poco.' );
		$target = 0;
		try {
			$token = get_current_user_id() . ':' . $source_id . ':' . $request_id;
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_type=%s AND m.meta_key='_mi_duplicate_request' AND m.meta_value=%s LIMIT 1", MI_Event_Post_Type::EVENT_TYPE, $token ) );
			if ( $existing ) return (int) $existing;
			$base = preg_replace( '/^Copia(?: \([0-9]+\))? /u', '', $source->post_title );
			$number = 0;
			do {
				$title = ( $number ? 'Copia (' . $number . ') ' : 'Copia ' ) . $base;
				++$number;
			} while ( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_title=%s LIMIT 1", MI_Event_Post_Type::EVENT_TYPE, $title ) ) );
			$target = wp_insert_post( wp_slash( array( 'post_type' => $source->post_type, 'post_status' => 'draft', 'post_title' => $title, 'post_content' => $source->post_content, 'post_excerpt' => $source->post_excerpt, 'post_author' => get_current_user_id(), 'menu_order' => $source->menu_order, 'comment_status' => $source->comment_status, 'ping_status' => $source->ping_status ) ), true );
			if ( is_wp_error( $target ) ) return $target;
			$excluded = array( '_mi_duplicate_request', '_mi_balance_url', '_mi_registration_url', '_mi_registration_page_id', '_mi_workspace_draft_id', '_mi_operational_sheet_id', '_mi_operational_sheet_url', '_mi_outputs_prepared_at', '_mi_sheet_archived_at', '_mi_sheet_missing', '_mi_event_archived_at', '_mi_event_cancelled_at', '_mi_event_cancellation_reason', '_mi_published_revision_id', '_mi_needs_republish', '_mi_privacy_consent_id', '_mi_marketing_consent_id' );
			foreach ( get_post_meta( $source_id ) as $key => $values ) {
				if ( ( 0 !== strpos( $key, '_mi_' ) && '_thumbnail_id' !== $key ) || in_array( $key, $excluded, true ) ) continue;
				foreach ( $values as $value ) {
					if ( ! add_post_meta( $target, $key, wp_slash( maybe_unserialize( $value ) ) ) ) throw new RuntimeException( 'meta' );
				}
			}
			foreach ( array( '_mi_privacy_consent_id' => 'privacy-' . $target, '_mi_marketing_consent_id' => 'marketing-' . $target, '_mi_duplicate_request' => $token ) as $key => $value ) {
				if ( ! add_post_meta( $target, $key, $value, true ) ) throw new RuntimeException( 'identity' );
			}
			return (int) $target;
		} catch ( Throwable $error ) {
			if ( $target && ! is_wp_error( $target ) ) {
				// Only this newly created, unpublished copy can bypass the normal deletion flow.
				$cleanup = static function ( $delete, $post ) use ( $target ) {
					if ( (int) $target !== (int) $post->ID || 'draft' !== $post->post_status ) return $delete;
					remove_filter( 'pre_delete_post', array( 'MI_Event_Deletion', 'guard_delete' ), 10 );
					return $delete;
				};
				add_filter( 'pre_delete_post', $cleanup, 9, 2 );
				try { wp_delete_post( $target, true ); }
				finally {
					remove_filter( 'pre_delete_post', $cleanup, 9 );
					if ( class_exists( 'MI_Event_Deletion' ) ) add_filter( 'pre_delete_post', array( 'MI_Event_Deletion', 'guard_delete' ), 10, 3 );
				}
			}
			return new WP_Error( 'copy', 'Non è stato possibile completare la copia. Riprova.' );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}
}
