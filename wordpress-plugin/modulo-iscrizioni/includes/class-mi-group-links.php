<?php
defined( 'ABSPATH' ) || exit;

/** Indirizzi permanenti delle serie di eventi di un gruppo. */
final class MI_Group_Links {
	const META = '_mi_group_links';
	const EVENT_META = '_mi_group_link';
	const LIMIT = 20;

	public static function boot() {
		add_action( 'template_redirect', array( __CLASS__, 'route' ), -90 );
	}

	public static function links( $group_id ) {
		$value = get_post_meta( $group_id, self::META, true );
		return is_array( $value ) ? $value : array();
	}

	public static function url( $slug ) { return home_url( '/' . $slug ); }

	/** Il lock copre validazione e salvataggio, anche quando il gruppo deve ancora nascere. */
	public static function validate( $group_id, $rows ) {
		if ( ! is_array( $rows ) || count( $rows ) > self::LIMIT ) return new WP_Error( 'mi_links_limit', 'Puoi configurare fino a 20 link permanenti per gruppo.' );
		global $wpdb;
		$lock = 'mi_group_links_' . md5( $wpdb->prefix );
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) return new WP_Error( 'mi_links_busy', 'Un altro salvataggio dei link è in corso. Riprova tra poco.' );
		register_shutdown_function( static function () use ( $wpdb, $lock ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); } );
		$existing = self::links( $group_id );
		$links = $existing;
		$seen = array();
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) || ! ctype_digit( (string) $index ) ) return new WP_Error( 'mi_links_input', 'Configurazione dei link non valida.' );
			foreach ( $row as $value ) if ( ! is_scalar( $value ) ) return new WP_Error( 'mi_links_input', 'Configurazione dei link non valida.' );
			$slug = strtolower( trim( (string) ( $row['slug'] ?? '' ) ) );
			$label = mb_substr( sanitize_text_field( $row['label'] ?? '' ), 0, 100 );
			if ( '' === $slug && '' === $label ) continue;
			if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) || strlen( $slug ) > 60 || ! preg_match( '/[a-z]/', $slug ) ) return new WP_Error( 'mi_links_slug', 'Il link deve contenere almeno una lettera: usa fino a 60 lettere senza accenti, numeri e trattini, senza dominio o barre.' );
			if ( '' === $label ) return new WP_Error( 'mi_links_label', 'Indica il nome della categoria per ogni link, per esempio Catechesi o Gite.' );
			if ( isset( $seen[ $slug ] ) ) return new WP_Error( 'mi_links_duplicate', 'Hai inserito due volte lo stesso indirizzo.' );
			$seen[ $slug ] = true;
			$original = (string) ( $row['original'] ?? '' );
			if ( $original && ( $original !== $slug || ! isset( $existing[ $original ] ) ) ) return new WP_Error( 'mi_links_immutable', 'Un indirizzo già salvato resta permanente. Aggiungi un nuovo link per usare un altro indirizzo.' );
			$reserved = array( 'v', 'wp-admin', 'wp-login', 'wp-json', 'wp-content', 'wp-includes', 'feed', 'comments', 'author', 'category', 'tag', 'search', 'page', 'embed', 'attachment', 'index', 'xmlrpc', 'robots', 'sitemap', 'favicon', 'wp-sitemap' );
			if ( in_array( $slug, $reserved, true ) || 0 === strpos( $slug, 'wp-' ) || get_page_by_path( $slug, OBJECT, get_post_types( array( 'public' => true ) ) ) ) return new WP_Error( 'mi_links_reserved', 'L’indirizzo /' . $slug . ' è riservato o già utilizzato da una pagina del sito.' );
			$owner = absint( get_option( 'mi_group_link_' . $slug, 0 ) );
			if ( $owner && $owner !== (int) $group_id ) return new WP_Error( 'mi_links_taken', 'L’indirizzo /' . $slug . ' è già riservato a un altro gruppo.' );
			$links[ $slug ] = array(
				'label' => $label,
				'enabled' => ! empty( $row['enabled'] ),
				'title' => mb_substr( sanitize_text_field( $row['title'] ?? '' ), 0, 180 ),
				'text' => mb_substr( sanitize_textarea_field( $row['text'] ?? '' ), 0, 1500 ),
				'image_id' => empty( $row['remove_image'] ) ? absint( $existing[ $slug ]['image_id'] ?? 0 ) : 0,
				'upload_index' => (int) $index,
			);
		}
		if ( count( $links ) > self::LIMIT ) return new WP_Error( 'mi_links_limit', 'Puoi configurare fino a 20 link permanenti per gruppo.' );
		return $links;
	}

	public static function save( $group_id, $links ) {
		foreach ( $links as $slug => &$link ) {
			$key = 'mi_group_link_' . $slug;
			if ( ! add_option( $key, (int) $group_id, '', false ) && (int) get_option( $key ) !== (int) $group_id ) return new WP_Error( 'mi_links_taken', 'Indirizzo già assegnato: /' . $slug );
			unset( $link['upload_index'] );
		}
		unset( $link );
		update_post_meta( $group_id, self::META, wp_slash( $links ) );
		return true;
	}

	public static function validate_event_link( $group_id, $slug, $previous = '' ) {
		if ( ! is_string( $slug ) ) return new WP_Error( 'mi_link_invalid', 'Scegli un link permanente del gruppo selezionato.' );
		if ( '' === $slug ) return '';
		$links = self::links( $group_id );
		if ( ! isset( $links[ $slug ] ) || ( empty( $links[ $slug ]['enabled'] ) && $slug !== $previous ) ) return new WP_Error( 'mi_link_invalid', 'Scegli un link permanente attivo del gruppo selezionato.' );
		return $slug;
	}

	/** Date della revisione pubblicata; nessun filtro sull'apertura delle iscrizioni. */
	public static function next_event( $group_id, $slug ) {
		$ids = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => array(
			array( 'key' => '_mi_activity_id', 'value' => $group_id ),
			array( 'key' => self::EVENT_META, 'value' => $slug ),
		) ) );
		$next = 0;
		$first = PHP_INT_MAX;
		// Come il portale, mantiene disponibile l'incontro per tutta la sua giornata.
		$today = ( new DateTimeImmutable( '@' . current_time( 'timestamp', true ) ) )->setTimezone( wp_timezone() )->setTime( 0, 0 );
		foreach ( $ids as $id ) {
			if ( get_post_meta( $id, '_mi_event_cancelled_at', true ) || get_post_meta( $id, '_mi_event_archived_at', true ) ) continue;
			$event = MI_Registration_Service::public_event( $id );
			if ( is_wp_error( $event ) ) continue;
			$raw = (string) ( $event['event_starts_at'] ?? '' );
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $raw, wp_timezone() );
			if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $raw || $date < $today ) continue;
			if ( $date->getTimestamp() < $first ) { $first = $date->getTimestamp(); $next = (int) $id; }
		}
		return $next;
	}

	public static function request_slug( $uri ) {
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		return preg_match( '#^' . preg_quote( $base, '#' ) . '/([a-z0-9-]{1,60})/?$#D', (string) $path, $match ) ? $match[1] : '';
	}

	public static function route() {
		$slug = self::request_slug( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		$group_id = $slug ? absint( get_option( 'mi_group_link_' . $slug, 0 ) ) : 0;
		if ( ! $group_id ) return;
		$group = get_post( $group_id );
		$link = self::links( $group_id )[ $slug ] ?? null;
		if ( ! $group || MI_Event_Post_Type::GROUP_TYPE !== $group->post_type || 'publish' !== $group->post_status || ! $link || empty( $link['enabled'] ) ) return;
		self::prevent_caching();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		$event_id = self::next_event( $group_id, $slug );
		if ( $event_id ) { wp_safe_redirect( MI_Shortcode::url_iscrizione( $event_id ), 302 ); exit; }
		status_header( 200 );
		wp_enqueue_style( 'mi-public', MI_PLUGIN_URL . 'assets/public.css', array(), MI_VERSION );
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html( $group->post_title . ' — ' . $link['label'] ); ?></title><?php wp_print_styles( 'mi-public' ); ?></head><body class="mi-focused-page"><?php echo self::waiting_content( $group_id, $link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer con escaping. ?></body></html><?php
		exit;
	}

	/** Il resolver deve essere rivalutato a ogni accesso, anche da cache condivise e proxy. */
	private static function prevent_caching() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
		nocache_headers();
		if ( headers_sent() ) return;
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, s-maxage=0', true );
		header( 'CDN-Cache-Control: no-store', true );
		header( 'Surrogate-Control: no-store', true );
		header( 'X-Accel-Expires: 0', true );
		header( 'X-LiteSpeed-Cache-Control: no-cache', true );
		do_action( 'litespeed_control_set_nocache', 'MI group link resolver' );
	}

	public static function waiting_content( $group_id, $link ) {
		$image_id = absint( $link['image_id'] ?? 0 ) ?: ( absint( get_post_meta( $group_id, '_mi_group_cover_image_id', true ) ) ?: get_post_thumbnail_id( $group_id ) );
		$image = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
		if ( ! $image ) $image = esc_url_raw( get_post_meta( $group_id, '_mi_group_cover_image_url', true ), array( 'https' ) );
		$title = $link['title'] ?: 'Ci ritroviamo presto';
		$text = $link['text'] ?: 'Al momento non ci sono nuovi eventi in programma. Torna a visitare questo stesso link per le prossime iscrizioni.';
		ob_start(); ?>
		<main class="mi-group-waiting"><?php if ( $image ) : ?><img class="mi-group-waiting__image" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '' ); ?>"><?php endif; ?><div class="mi-group-waiting__body"><p><?php echo esc_html( get_the_title( $group_id ) . ' · ' . $link['label'] ); ?></p><h1><?php echo esc_html( $title ); ?></h1><p class="mi-group-waiting__text"><?php echo esc_html( $text ); ?></p></div></main>
		<?php return ob_get_clean();
	}

	public static function group_fields( $group_id = 0 ) {
		$links = self::links( $group_id ); ?>
		<details class="mi-group-links" data-mi-group-links><summary>Link permanenti per le iscrizioni</summary><p>Un indirizzo da condividere per ogni categoria, per esempio Catechesi o Gite. Aprirà il prossimo evento pubblicato collegato, anche se le iscrizioni non sono ancora aperte.</p><input type="hidden" name="mi_group_links_present" value="1"><div data-mi-link-rows>
		<?php $index = 0; foreach ( $links as $slug => $link ) self::link_fields( $index++, $slug, $link ); ?>
		</div><template data-mi-link-template><?php self::link_fields( '__INDEX__', '', array() ); ?></template><button type="button" class="mi-secondary" data-mi-add-link <?php disabled( count( $links ) >= self::LIMIT ); ?>>Aggiungi link permanente</button><p class="mi-portal-muted">Fino a 20 link per gruppo. Gli indirizzi salvati restano riservati; puoi disattivarli e riattivarli. L’immagine e il messaggio compaiono soltanto quando non ci sono eventi futuri pubblicati nella categoria.</p><noscript><p>Attiva JavaScript per aggiungere nuovi link permanenti.</p></noscript></details>
		<?php
	}

	private static function link_fields( $index, $slug, $link ) {
		$prefix = 'group_links[' . $index . ']'; ?>
		<fieldset class="mi-group-link-row" data-mi-link-row><legend><?php echo esc_html( $link['label'] ?? 'Nuovo link permanente' ); ?></legend><input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[original]" value="<?php echo esc_attr( $slug ); ?>"><div class="mi-group-form-grid"><label>Categoria<input name="<?php echo esc_attr( $prefix ); ?>[label]" maxlength="100" required value="<?php echo esc_attr( $link['label'] ?? '' ); ?>" placeholder="Es. Catechesi"></label><label>Indirizzo permanente<input name="<?php echo esc_attr( $prefix ); ?>[slug]" maxlength="60" pattern="[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*" required <?php echo $slug ? 'readonly' : ''; ?> value="<?php echo esc_attr( $slug ); ?>" placeholder="Es. 12cesteroma"><small><?php echo esc_html( home_url( '/' ) ); ?><span data-mi-link-slug><?php echo esc_html( $slug ?: 'nome-del-link' ); ?></span></small></label></div>
		<?php if ( $slug ) : ?><p><a href="<?php echo esc_url( self::url( $slug ) ); ?>" target="_blank" rel="noopener">Apri link</a> <button type="button" class="mi-secondary" data-mi-copy="<?php echo esc_attr( self::url( $slug ) ); ?>">Copia link</button></p><?php endif; ?>
		<label class="mi-check"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="1" <?php checked( $link['enabled'] ?? true ); ?>> Link attivo</label><details><summary>Pagina di attesa senza eventi</summary><label>Titolo<input name="<?php echo esc_attr( $prefix ); ?>[title]" maxlength="180" value="<?php echo esc_attr( $link['title'] ?? '' ); ?>" placeholder="Ci ritroviamo presto"></label><label>Messaggio<textarea name="<?php echo esc_attr( $prefix ); ?>[text]" rows="3" maxlength="1500" placeholder="Al momento non ci sono nuovi eventi in programma. Torna a visitare questo stesso link per le prossime iscrizioni."><?php echo esc_textarea( $link['text'] ?? '' ); ?></textarea></label>
		<?php if ( ! empty( $link['image_id'] ) ) : ?><img class="mi-group-link-preview" src="<?php echo esc_url( wp_get_attachment_image_url( $link['image_id'], 'medium' ) ); ?>" alt=""><label class="mi-check"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[remove_image]" value="1"> Torna all’immagine del gruppo</label><?php endif; ?>
		<label>Immagine di attesa <small>(facoltativa)</small><input type="file" name="group_link_image_<?php echo esc_attr( $index ); ?>" accept="image/jpeg,image/png,image/webp" data-mi-max-bytes="2097152"><small>JPG, PNG o WebP, massimo 2 MB. Se non la carichi, viene usata l’immagine del gruppo.</small></label></details><?php if ( ! $slug ) : ?><button type="button" class="mi-secondary" data-mi-remove-link>Rimuovi questo nuovo link</button><?php endif; ?></fieldset>
		<?php
	}

	public static function event_field( $groups, $selected ) { ?>
		<label>Categoria / link permanente <small>(facoltativo)</small><select name="group_link" data-mi-event-group-link><option value="">Nessuno — solo il link di questo evento</option><?php foreach ( $groups as $group ) : foreach ( self::links( $group->ID ) as $slug => $link ) : if ( empty( $link['enabled'] ) && $slug !== $selected ) continue; ?><option value="<?php echo esc_attr( $slug ); ?>" data-group="<?php echo esc_attr( $group->ID ); ?>" <?php selected( $slug, $selected ); ?>><?php echo esc_html( $link['label'] . ' — /' . $slug . ( empty( $link['enabled'] ) ? ' (disattivato)' : '' ) ); ?></option><?php endforeach; endforeach; ?></select><small>Collega l’evento a una categoria del gruppo. Il link individuale dell’evento resta disponibile.</small></label>
		<?php
	}
}
