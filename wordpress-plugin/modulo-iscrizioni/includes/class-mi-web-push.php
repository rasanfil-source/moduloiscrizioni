<?php

defined( 'ABSPATH' ) || exit;

/** Notifiche Web Push per i gestori autenticati del portale. */
final class MI_Web_Push {
	const VAPID_OPTION = 'mi_web_push_vapid';
	const AJAX_ACTION = 'mi_web_push_subscription';
	const CRON_HOOK = 'mi_web_push_registration';
	const WORKER_QUERY = 'mi_web_push_worker';

	public static function boot() {
		add_action( 'template_redirect', array( __CLASS__, 'serve_worker' ), -130 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'save_subscription' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_registration' ), 10, 2 );
	}

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'mi_web_push_subscriptions';
	}

	private static function user_can_manage_portal( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && ! MI_Access::is_suspended( $user_id ) && ( user_can( $user_id, 'mi_portal_access' ) || user_can( $user_id, 'manage_options' ) );
	}

	private static function vapid_keys() {
		$keys = get_option( self::VAPID_OPTION, false );
		if ( is_array( $keys ) && ! empty( $keys['private'] ) && ! empty( $keys['public'] ) ) return $keys;
		if ( false !== $keys || ! function_exists( 'openssl_pkey_new' ) || ! function_exists( 'openssl_pkey_derive' ) || ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'random_bytes' ) ) return false;

		try {
			$private_key = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) );
			if ( ! $private_key ) return false;
			$details = openssl_pkey_get_details( $private_key );
			if ( ! is_array( $details ) || empty( $details['ec']['x'] ) || empty( $details['ec']['y'] ) ) return false;
			$point = self::point_from_details( $details );
			$private_pem = '';
			if ( ! $point || ! openssl_pkey_export( $private_key, $private_pem ) ) return false;
			$generated = array( 'private' => $private_pem, 'public' => self::base64url_encode( $point ) );
			add_option( self::VAPID_OPTION, $generated, '', false );
			$keys = get_option( self::VAPID_OPTION, false );
			return is_array( $keys ) && ! empty( $keys['private'] ) && ! empty( $keys['public'] ) ? $keys : false;
		} catch ( Throwable $error ) {
			return false;
		}
	}

	private static function point_from_details( $details ) {
		$x = (string) ( $details['ec']['x'] ?? '' );
		$y = (string) ( $details['ec']['y'] ?? '' );
		if ( strlen( $x ) > 32 || strlen( $y ) > 32 || '' === $x || '' === $y ) return '';
		return "\x04" . str_pad( $x, 32, "\x00", STR_PAD_LEFT ) . str_pad( $y, 32, "\x00", STR_PAD_LEFT );
	}

	private static function base64url_encode( $value ) {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	private static function base64url_decode( $value ) {
		if ( ! is_string( $value ) ) return '';
		$value = strtr( (string) $value, '-_', '+/' );
		$value .= str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 );
		$decoded = base64_decode( $value, true );
		return false === $decoded ? '' : $decoded;
	}

	private static function log_diagnostic( $message ) {
		$message = preg_replace( '/[\r\n\t]+/', ' ', (string) $message );
		error_log( '[Modulo Iscrizioni Web Push] ' . substr( $message, 0, 240 ) );
	}

	private static function der_length( $length ) {
		if ( $length < 128 ) return chr( $length );
		$bytes = '';
		while ( $length > 0 ) { $bytes = chr( $length & 255 ) . $bytes; $length >>= 8; }
		return chr( 128 | strlen( $bytes ) ) . $bytes;
	}

	private static function der_value( $tag, $value ) {
		return chr( $tag ) . self::der_length( strlen( $value ) ) . $value;
	}

	private static function public_key_pem( $point ) {
		if ( 65 !== strlen( $point ) || "\x04" !== $point[0] ) return '';
		$algorithm = self::der_value( 0x30, hex2bin( '06072a8648ce3d020106082a8648ce3d030107' ) );
		$spki = self::der_value( 0x30, $algorithm . self::der_value( 0x03, "\x00" . $point ) );
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	private static function read_der_length( $der, &$offset ) {
		if ( ! isset( $der[ $offset ] ) ) return false;
		$first = ord( $der[ $offset++ ] );
		if ( $first < 128 ) return $first;
		$count = $first & 127;
		if ( ! $count || $count > 4 || $offset + $count > strlen( $der ) ) return false;
		$length = 0;
		for ( $index = 0; $index < $count; $index++ ) $length = ( $length << 8 ) | ord( $der[ $offset++ ] );
		return $length;
	}

	private static function der_signature_to_raw( $signature ) {
		$offset = 0;
		if ( ! isset( $signature[ $offset ] ) || 0x30 !== ord( $signature[ $offset++ ] ) ) return '';
		$sequence_length = self::read_der_length( $signature, $offset );
		if ( false === $sequence_length || $offset + $sequence_length !== strlen( $signature ) ) return '';
		$values = array();
		for ( $index = 0; $index < 2; $index++ ) {
			if ( ! isset( $signature[ $offset ] ) || 0x02 !== ord( $signature[ $offset++ ] ) ) return '';
			$length = self::read_der_length( $signature, $offset );
			if ( false === $length || $length < 1 || $offset + $length > strlen( $signature ) ) return '';
			$value = substr( $signature, $offset, $length );
			$offset += $length;
			$value = ltrim( $value, "\x00" );
			if ( strlen( $value ) > 32 ) return '';
			$values[] = str_pad( $value, 32, "\x00", STR_PAD_LEFT );
		}
		return 2 === count( $values ) ? $values[0] . $values[1] : '';
	}

	private static function hkdf_expand( $prk, $info, $length ) {
		$output = '';
		$previous = '';
		for ( $counter = 1; strlen( $output ) < $length; $counter++ ) {
			$previous = hash_hmac( 'sha256', $previous . $info . chr( $counter ), $prk, true );
			$output .= $previous;
		}
		return substr( $output, 0, $length );
	}

	private static function encrypt_payload( $payload, $subscription ) {
		$ua_public = self::base64url_decode( $subscription['keys']['p256dh'] ?? '' );
		$auth_secret = self::base64url_decode( $subscription['keys']['auth'] ?? '' );
		if ( 65 !== strlen( $ua_public ) || "\x04" !== $ua_public[0] || 16 !== strlen( $auth_secret ) ) return new WP_Error( 'mi_push_keys', 'Chiavi del dispositivo non valide.' );
		$ua_key = openssl_pkey_get_public( self::public_key_pem( $ua_public ) );
		$as_private = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) );
		if ( ! $ua_key || ! $as_private ) return new WP_Error( 'mi_push_crypto', 'Crittografia delle notifiche non disponibile.' );
		$as_details = openssl_pkey_get_details( $as_private );
		$as_public = is_array( $as_details ) ? self::point_from_details( $as_details ) : '';
		$shared_secret = $as_public ? openssl_pkey_derive( $ua_key, $as_private, 32 ) : false;
		if ( false === $shared_secret || 32 !== strlen( $shared_secret ) ) return new WP_Error( 'mi_push_ecdh', 'Scambio delle chiavi non riuscito.' );

		$prk_key = hash_hmac( 'sha256', $shared_secret, $auth_secret, true );
		$key_info = "WebPush: info\x00" . $ua_public . $as_public;
		$ikm = self::hkdf_expand( $prk_key, $key_info, 32 );
		$salt = random_bytes( 16 );
		$prk = hash_hmac( 'sha256', $ikm, $salt, true );
		$cek = self::hkdf_expand( $prk, "Content-Encoding: aes128gcm\x00", 16 );
		$nonce = self::hkdf_expand( $prk, "Content-Encoding: nonce\x00", 12 );
		$tag = '';
		$ciphertext = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
		if ( false === $ciphertext || 16 !== strlen( $tag ) || 65 !== strlen( $as_public ) ) return new WP_Error( 'mi_push_encrypt', 'Crittografia delle notifiche non riuscita.' );
		return $salt . pack( 'N', 4096 ) . chr( strlen( $as_public ) ) . $as_public . $ciphertext . $tag;
	}

	private static function vapid_token( $endpoint, $keys ) {
		$parts = wp_parse_url( $endpoint );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || 'https' !== strtolower( $parts['scheme'] ) ) return new WP_Error( 'mi_push_endpoint', 'Endpoint non valido.' );
		$audience = 'https://' . strtolower( $parts['host'] ) . ( ! empty( $parts['port'] ) && 443 !== (int) $parts['port'] ? ':' . (int) $parts['port'] : '' );
		$header = self::base64url_encode( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
		$contact = sanitize_email( get_option( 'admin_email' ) );
		$subject = is_email( $contact ) ? 'mailto:' . $contact : home_url( '/' );
		$claims = self::base64url_encode( wp_json_encode( array( 'aud' => $audience, 'exp' => time() + 12 * HOUR_IN_SECONDS, 'sub' => $subject ) ) );
		$unsigned = $header . '.' . $claims;
		$signature = '';
		if ( ! openssl_sign( $unsigned, $signature, $keys['private'], OPENSSL_ALGO_SHA256 ) ) return new WP_Error( 'mi_push_vapid', 'Firma VAPID non riuscita.' );
		$signature = self::der_signature_to_raw( $signature );
		if ( 64 !== strlen( $signature ) ) return new WP_Error( 'mi_push_vapid_signature', 'Firma VAPID non valida.' );
		return $unsigned . '.' . self::base64url_encode( $signature );
	}

	private static function valid_endpoint( $endpoint ) {
		if ( ! is_string( $endpoint ) ) return '';
		$endpoint = esc_url_raw( (string) $endpoint, array( 'https' ) );
		if ( '' === $endpoint || strlen( $endpoint ) > 4096 || ! function_exists( 'wp_http_validate_url' ) ) return '';
		return wp_http_validate_url( $endpoint ) ? $endpoint : '';
	}

	public static function save_subscription() {
		if ( ! self::user_can_manage_portal() ) wp_send_json_error( array( 'message' => 'Accesso non consentito.' ), 403 );
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mi_web_push' ) ) wp_send_json_error( array( 'message' => 'Sessione scaduta. Ricarica il portale.' ), 403 );
		$subscription_json = (string) wp_unslash( $_POST['subscription'] ?? '' );
		if ( strlen( $subscription_json ) > 8192 ) wp_send_json_error( array( 'message' => 'Dati della notifica troppo grandi.' ), 400 );
		$payload = json_decode( $subscription_json, true );
		if ( ! is_array( $payload ) ) wp_send_json_error( array( 'message' => 'Dati della notifica non validi.' ), 400 );
		$endpoint = self::valid_endpoint( $payload['endpoint'] ?? '' );
		if ( ! $endpoint ) wp_send_json_error( array( 'message' => 'Endpoint del browser non valido.' ), 400 );
		$endpoint_hash = hash( 'sha256', $endpoint );
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) );
		if ( ! in_array( $operation, array( 'subscribe', 'unsubscribe' ), true ) ) wp_send_json_error( array( 'message' => 'Operazione non valida.' ), 400 );
		global $wpdb;
		$table = self::table();
		if ( 'unsubscribe' === $operation ) {
			$wpdb->delete( $table, array( 'user_id' => get_current_user_id(), 'endpoint_hash' => $endpoint_hash ), array( '%d', '%s' ) );
			wp_send_json_success( array( 'message' => 'Notifiche disattivate su questo dispositivo.' ) );
		}
		$public_key = self::base64url_decode( $payload['keys']['p256dh'] ?? '' );
		$auth_secret = self::base64url_decode( $payload['keys']['auth'] ?? '' );
		if ( 65 !== strlen( $public_key ) || "\x04" !== $public_key[0] || 16 !== strlen( $auth_secret ) ) wp_send_json_error( array( 'message' => 'Chiavi del dispositivo non valide.' ), 400 );
		$now = current_time( 'mysql', true );
		$sql = $wpdb->prepare(
			"INSERT INTO {$table} (user_id,endpoint_hash,endpoint,p256dh,auth,created_at,updated_at) VALUES (%d,%s,%s,%s,%s,%s,%s)
			ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),endpoint=VALUES(endpoint),p256dh=VALUES(p256dh),auth=VALUES(auth),updated_at=VALUES(updated_at)",
			get_current_user_id(), $endpoint_hash, $endpoint, sanitize_text_field( $payload['keys']['p256dh'] ), sanitize_text_field( $payload['keys']['auth'] ), $now, $now
		);
		if ( false === $wpdb->query( $sql ) ) wp_send_json_error( array( 'message' => 'Non è stato possibile salvare questa preferenza.' ), 500 );
		wp_send_json_success( array( 'message' => 'Notifiche attive su questo dispositivo.' ) );
	}

	public static function schedule_registration( $registration_id, $event_id = 0 ) {
		$registration_id = absint( $registration_id );
		if ( ! $registration_id ) return;
		global $wpdb;
		if ( ! (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ) ) {
			self::log_diagnostic( 'registration=' . $registration_id . ' queued=0 reason=no_subscriptions' );
			return;
		}
		if ( ! self::vapid_keys() ) {
			self::log_diagnostic( 'registration=' . $registration_id . ' queued=0 reason=vapid_keys_unavailable' );
			return;
		}
		$args = array( $registration_id, 1 );
		if ( wp_next_scheduled( self::CRON_HOOK, $args ) ) return;
		$scheduled = wp_schedule_single_event( time(), self::CRON_HOOK, $args, true );
		if ( is_wp_error( $scheduled ) ) {
			self::log_diagnostic( 'registration=' . $registration_id . ' queued=0 reason=' . $scheduled->get_error_code() );
		} elseif ( ! $scheduled ) {
			self::log_diagnostic( 'registration=' . $registration_id . ' queued=0 reason=scheduler_rejected' );
		} else {
			self::log_diagnostic( 'registration=' . $registration_id . ' queued=1' );
		}
	}

	private static function event_icon( $event_id, $group_id ) {
		$icon = '';
		if ( $group_id ) {
			$logo_id = get_post_thumbnail_id( $group_id );
			if ( $logo_id ) $icon = wp_get_attachment_image_url( $logo_id, 'thumbnail' );
			if ( ! $icon ) $icon = (string) get_post_meta( $group_id, '_mi_group_logo_url', true );
			if ( ! $icon && class_exists( 'MI_Portal_PWA' ) ) $icon = MI_Portal_PWA::icon( $group_id, 192 );
		}
		return esc_url_raw( $icon ?: MI_PLUGIN_URL . 'assets/portal-icon-192.png?ver=' . rawurlencode( MI_VERSION ) );
	}

	private static function registration_url( $event_id, $group_id ) {
		$url = class_exists( 'MI_Portal_Management' ) ? MI_Portal_Management::url( $event_id ) : add_query_arg( array( 'mi_portal' => '1', 'mi_portal_view' => 'management', 'mi_portal_event' => $event_id ), home_url( '/' ) );
		return $group_id ? add_query_arg( 'mi_pwa_group', $group_id, $url ) : $url;
	}

	private static function send_to_subscription( $subscription, $payload, $keys ) {
		$endpoint = self::valid_endpoint( $subscription['endpoint'] ?? '' );
		if ( ! $endpoint ) return new WP_Error( 'mi_push_endpoint', 'Endpoint non valido.' );
		$encrypted = self::encrypt_payload( $payload, array( 'keys' => array( 'p256dh' => $subscription['p256dh'], 'auth' => $subscription['auth'] ) ) );
		if ( is_wp_error( $encrypted ) ) return $encrypted;
		$token = self::vapid_token( $endpoint, $keys );
		if ( is_wp_error( $token ) ) return $token;
		return wp_safe_remote_post( $endpoint, array(
			'timeout' => 5,
			'redirection' => 0,
			'headers' => array(
				'Authorization' => 'vapid t=' . $token . ', k=' . $keys['public'],
				'TTL' => '60',
				'Content-Type' => 'application/octet-stream',
				'Content-Encoding' => 'aes128gcm',
			),
			'body' => $encrypted,
		) );
	}

	public static function send_registration( $registration_id, $attempt = 1 ) {
		$registration_id = absint( $registration_id );
		$attempt = max( 1, absint( $attempt ) );
		$keys = self::vapid_keys();
		if ( ! $registration_id ) return;
		if ( ! $keys ) {
			self::log_diagnostic( 'registration=' . $registration_id . ' dispatched=0 reason=vapid_keys_unavailable' );
			return;
		}
		global $wpdb;
		$registration = $wpdb->get_row( $wpdb->prepare( "SELECT id,event_id FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $registration_id ), ARRAY_A );
		if ( ! is_array( $registration ) ) {
			self::log_diagnostic( 'registration=' . $registration_id . ' dispatched=0 reason=registration_not_found' );
			return;
		}
		$event_id = absint( $registration['event_id'] );
		$group_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		$event_title = html_entity_decode( wp_strip_all_tags( get_the_title( $event_id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$group_title = $group_id ? html_entity_decode( wp_strip_all_tags( get_the_title( $group_id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
		$payload = wp_json_encode( array(
			'title' => 'Nuova iscrizione',
			'body' => $event_title . ( $group_title ? ' · ' . $group_title : '' ),
			'icon' => self::event_icon( $event_id, $group_id ),
			'badge' => MI_PLUGIN_URL . 'assets/portal-icon-32.png?ver=' . rawurlencode( MI_VERSION ),
			'tag' => 'mi-registration-' . $registration_id,
			'url' => self::registration_url( $event_id, $group_id ),
		) );
		if ( false === $payload ) return;
		$table = self::table();
		$subscriptions = $wpdb->get_results( "SELECT id,user_id,endpoint,p256dh,auth FROM {$table}", ARRAY_A );
		$retry = false;
		$eligible = 0;
		$accepted = 0;
		$failed = 0;
		foreach ( (array) $subscriptions as $subscription ) {
			$user_id = absint( $subscription['user_id'] );
			if ( ! self::user_can_manage_portal( $user_id ) || ! MI_Access::can_access_event( $event_id, $user_id ) ) continue;
			$eligible++;
			$response = self::send_to_subscription( $subscription, $payload, $keys );
			if ( is_wp_error( $response ) ) {
				$failed++;
				$error_message = preg_replace( '~https?://\S+~i', '[push endpoint]', $response->get_error_message() );
				self::log_diagnostic( 'registration=' . $registration_id . ' user=' . $user_id . ' delivery_error=' . $response->get_error_code() . ' ' . $error_message );
				$retry = true;
				continue;
			}
			$status = wp_remote_retrieve_response_code( $response );
			if ( in_array( $status, array( 404, 410 ), true ) ) {
				$failed++;
				$wpdb->delete( $table, array( 'id' => absint( $subscription['id'] ) ), array( '%d' ) );
				self::log_diagnostic( 'registration=' . $registration_id . ' user=' . $user_id . ' delivery_http=' . $status . ' reason=subscription_expired' );
			} elseif ( $status >= 500 || 429 === $status ) {
				$failed++;
				self::log_diagnostic( 'registration=' . $registration_id . ' user=' . $user_id . ' delivery_http=' . $status . ' retry=1' );
				$retry = true;
			} elseif ( $status >= 200 && $status < 300 ) {
				$accepted++;
			} else {
				$failed++;
				$response_body = sanitize_text_field( wp_remote_retrieve_body( $response ) );
				self::log_diagnostic( 'registration=' . $registration_id . ' user=' . $user_id . ' delivery_http=' . $status . ' ' . $response_body );
			}
		}
		if ( ! $eligible ) self::log_diagnostic( 'registration=' . $registration_id . ' dispatched=0 reason=no_eligible_subscriptions' );
		else self::log_diagnostic( 'registration=' . $registration_id . ' eligible=' . $eligible . ' accepted=' . $accepted . ' failed=' . $failed );
		if ( $retry && $attempt < 3 ) {
			$scheduled = wp_schedule_single_event( time() + 60 * $attempt, self::CRON_HOOK, array( $registration_id, $attempt + 1 ), true );
			if ( is_wp_error( $scheduled ) ) self::log_diagnostic( 'registration=' . $registration_id . ' retry_queued=0 reason=' . $scheduled->get_error_code() );
			elseif ( ! $scheduled ) self::log_diagnostic( 'registration=' . $registration_id . ' retry_queued=0 reason=scheduler_rejected' );
		}
	}

	public static function serve_worker() {
		if ( ! isset( $_GET[ self::WORKER_QUERY ] ) || ! is_scalar( $_GET[ self::WORKER_QUERY ] ) || '1' !== (string) wp_unslash( $_GET[ self::WORKER_QUERY ] ) ) return;
		$worker = MI_PLUGIN_DIR . 'assets/portal-push-sw.js';
		if ( ! is_readable( $worker ) ) { status_header( 404 ); exit; }
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		header( 'Service-Worker-Allowed: ' . trailingslashit( $path ?: '/' ) );
		readfile( $worker );
		exit;
	}

	public static function render_control() {
		if ( ! is_user_logged_in() || ! self::user_can_manage_portal() ) return;
		$keys = self::vapid_keys();
		if ( ! $keys ) {
			echo '<span class="mi-web-push-unavailable" role="status">Notifiche non disponibili su questo server.</span>';
			return;
		}
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$worker_url = add_query_arg( array( self::WORKER_QUERY => '1', 'ver' => MI_VERSION ), home_url( '/' ) );
		$script_url = MI_Assets::filter_url( MI_PLUGIN_URL . 'assets/portal-push.js?ver=' . rawurlencode( MI_VERSION ) );
		echo '<div class="mi-web-push" data-mi-web-push data-ajax-url="' . esc_attr( set_url_scheme( admin_url( 'admin-ajax.php' ), 'https' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'mi_web_push' ) ) . '" data-public-key="' . esc_attr( $keys['public'] ) . '" data-worker-url="' . esc_attr( $worker_url ) . '" data-worker-scope="' . esc_attr( trailingslashit( $home_path ?: '/' ) ) . '"><button class="mi-secondary mi-web-push__button" type="button" data-mi-web-push-toggle aria-label="Attiva o disattiva gli avvisi per le nuove iscrizioni degli eventi che puoi gestire" aria-pressed="false"><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/><path class="mi-web-push__slash" d="M3 3l18 18"/></svg><span data-mi-web-push-label>Attiva notifiche</span></button><span class="mi-web-push__status" data-mi-web-push-status role="status" aria-live="polite"></span></div>';
		echo '<script defer src="' . esc_url( $script_url ) . '"></script>';
	}
}
