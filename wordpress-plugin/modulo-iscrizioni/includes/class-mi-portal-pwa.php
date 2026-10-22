<?php

defined( 'ABSPATH' ) || exit;

/** Installazione facoltativa del portale online, senza cache dei dati o service worker. */
final class MI_Portal_PWA {
	const OPTION = 'mi_portal_pwa_enabled';
	private static $selected_group_id = null;

	public static function boot() {
		add_action( 'template_redirect', array( __CLASS__, 'serve_manifest' ), -120 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mi_save_portal_pwa', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_mi_save_pwa_icon', array( __CLASS__, 'save_icon' ) );
	}

	public static function enabled() {
		return '1' === (string) get_option( self::OPTION, '1' );
	}

	public static function is_portal() {
		if ( is_admin() || wp_doing_ajax() ) return false;
		foreach ( array( 'mi_status', 'mi_waitlist_offer', 'mi_cancel_participant', 'mi_cancel_token' ) as $public_flag ) {
			if ( ! empty( $_GET[ $public_flag ] ) ) return false;
		}
		if ( ! empty( $_GET['mi_portal'] ) ) return true;
		if ( ! is_singular() ) return false;
		$post = get_post();
		return $post && has_shortcode( $post->post_content, MI_Portal::SHORTCODE );
	}

	public static function group( $group_id ) {
		$group = $group_id ? get_post( absint( $group_id ) ) : null;
		return $group && MI_Event_Post_Type::GROUP_TYPE === $group->post_type && 'publish' === $group->post_status ? $group : null;
	}

	public static function allowed_groups() {
		if ( ! is_user_logged_in() || MI_Access::is_suspended() ) return array();
		$scope = MI_Access::activity_ids();
		if ( 'ALL' !== $scope ) {
			// I gestori di singoli eventi possono installare l'icona del gruppo,
			// ma non ricevono per questo il diritto di modificarla.
			$user = wp_get_current_user();
			if ( in_array( 'mi_assigned_event_manager', (array) $user->roles, true ) ) {
				$events = MI_Access::event_ids();
				if ( is_array( $events ) ) foreach ( $events as $event_id ) $scope[] = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
			}
			$scope = array_values( array_unique( array_filter( $scope ) ) );
			if ( ! $scope ) return array();
		}
		$args = array( 'post_type' => MI_Event_Post_Type::GROUP_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true );
		if ( 'ALL' !== $scope ) $args['post__in'] = $scope;
		return get_posts( $args );
	}

	public static function selected_group_id() {
		if ( null !== self::$selected_group_id ) return self::$selected_group_id;
		if ( isset( $_GET['mi_pwa_group'] ) ) {
			$group = self::group( is_scalar( $_GET['mi_pwa_group'] ) ? absint( $_GET['mi_pwa_group'] ) : 0 );
			return self::$selected_group_id = $group ? (int) $group->ID : 0;
		}
		$groups = self::allowed_groups();
		return self::$selected_group_id = 1 === count( $groups ) ? (int) $groups[0]->ID : 0;
	}

	public static function can_edit_icon( $group_id ) {
		if ( ! is_user_logged_in() || MI_Access::is_suspended() || ! self::group( $group_id ) ) return false;
		if ( current_user_can( 'manage_options' ) || current_user_can( 'mi_manage_groups' ) ) return true;
		$user = wp_get_current_user();
		return in_array( 'mi_group_manager', (array) $user->roles, true ) && MI_Access::can_access_activity( $group_id );
	}

	public static function icon( $group_id, $size ) {
		$id = $group_id ? absint( get_post_meta( $group_id, '_mi_pwa_icon_id', true ) ) : 0;
		if ( ! $id ) return '';
		$image = wp_get_attachment_image_src( $id, 'mi_pwa_' . $size );
		return $image && $size === (int) $image[1] && $size === (int) $image[2] ? $image[0] : '';
	}

	public static function apple_icon() {
		if ( ! self::enabled() || ! self::is_portal() || ! is_ssl() ) return '';
		return self::icon( self::selected_group_id(), 192 );
	}

	public static function manifest( $group_id = 0 ) {
		// L'avvio non incorpora evento, persona, token, nonce o filtri della pagina corrente.
		$start = add_query_arg( 'mi_portal', '1', home_url( '/' ) );
		$group = self::group( $group_id );
		if ( $group ) $start = add_query_arg( 'mi_pwa_group', $group->ID, $start );
		return array(
			'id' => $start,
			'name' => $group ? 'Segreteria — ' . wp_strip_all_tags( $group->post_title ) : 'Segreteria eventi',
			'short_name' => $group ? mb_substr( wp_strip_all_tags( $group->post_title ), 0, 24 ) : 'Segreteria',
			'description' => 'Il portale della segreteria, disponibile anche dal browser.',
			'lang' => 'it',
			'start_url' => $start,
			'scope' => home_url( '/' ),
			'display' => 'standalone',
			'background_color' => '#F5F3EE',
			'theme_color' => '#1B2B52',
			'prefer_related_applications' => false,
			'icons' => array(
				array( 'src' => ( $group ? self::icon( $group->ID, 192 ) : '' ) ?: MI_PLUGIN_URL . 'assets/portal-icon-192.png?ver=' . rawurlencode( MI_VERSION ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => ( $group ? self::icon( $group->ID, 512 ) : '' ) ?: MI_PLUGIN_URL . 'assets/portal-icon-512.png?ver=' . rawurlencode( MI_VERSION ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
			),
		);
	}

	public static function serve_manifest() {
		if ( 'manifest' !== ( $_GET['mi_pwa'] ?? '' ) ) return;
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( ! self::enabled() ) {
			status_header( 404 );
			echo '{}';
		} else {
			status_header( 200 );
			echo wp_json_encode( self::manifest( is_scalar( $_GET['group'] ?? 0 ) ? absint( $_GET['group'] ?? 0 ) : 0 ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		exit;
	}

	public static function render_head() {
		if ( ! self::enabled() || ! self::is_portal() || ! is_ssl() ) return;
		$group_id = self::selected_group_id();
		$manifest = self::manifest( $group_id );
		echo '<link rel="manifest" href="' . esc_url( add_query_arg( array( 'mi_pwa' => 'manifest', 'group' => $group_id, 'ver' => MI_VERSION ), home_url( '/' ) ) ) . '" data-mi-pwa-group="' . esc_attr( $group_id ) . '">';
		echo '<meta name="theme-color" content="#1B2B52"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="' . esc_attr( $manifest['short_name'] ) . '">';
		// Anche la pagina autonoma, che non esegue wp_head/wp_footer, usa questo punto d'ingresso.
		echo '<script defer src="' . esc_url( MI_Assets::filter_url( MI_PLUGIN_URL . 'assets/portal-pwa.js?ver=' . rawurlencode( MI_VERSION ) ) ) . '"></script>';
	}

	public static function render_install_help() {
		if ( ! self::enabled() || ! self::is_portal() || ! is_ssl() ) return;
		$groups = self::allowed_groups();
		$group_id = self::selected_group_id();
		$manifest = self::manifest( $group_id );
		$notice = sanitize_key( wp_unslash( $_GET['mi_pwa_notice'] ?? '' ) );
		?>
		<details class="mi-pwa-install" id="mi-app" data-mi-pwa-install <?php if ( $notice || isset( $_GET['mi_pwa_choice'] ) ) echo 'open'; ?>>
			<summary>App sul telefono</summary>
			<?php if ( 'saved' === $notice ) : ?><p role="status">Icona aggiornata. Per vedere subito la nuova icona sul telefono potrebbe essere necessario rimuovere e reinstallare l’app.</p><?php endif; ?>
			<?php if ( 'error' === $notice ) : ?><p role="alert">Icona non aggiornata. Scegli un’immagine PNG quadrata, da 512 a 4096 pixel per lato, massimo 2 MB. Se il problema persiste, verifica il supporto immagini dell’hosting.</p><?php endif; ?>
			<div class="mi-pwa-preview"><img src="<?php echo esc_url( $manifest['icons'][0]['src'] ); ?>" width="48" height="48" alt="" loading="lazy"><strong><?php echo esc_html( $manifest['name'] ); ?></strong></div>
			<?php if ( $groups ) : ?>
			<form method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="mi-pwa-group-choice">
				<input type="hidden" name="mi_portal" value="1"><input type="hidden" name="mi_portal_view" value="groups">
				<input type="hidden" name="mi_pwa_choice" value="1">
				<label>Gruppo per l’icona dell’app<select name="mi_pwa_group"><option value="0">Segreteria eventi</option><?php foreach ( $groups as $group ) : ?><option value="<?php echo esc_attr( $group->ID ); ?>" <?php selected( $group_id, (int) $group->ID ); ?>><?php echo esc_html( $group->post_title ); ?></option><?php endforeach; ?></select></label>
				<small>La scelta si applica automaticamente e ricarica questa scheda.</small>
				<noscript><button class="mi-secondary" type="submit">Applica gruppo</button></noscript>
			</form>
			<?php endif; ?>
			<p>Aggiungi Segreteria eventi alla schermata iniziale. Accedi con il tuo account e usa le stesse funzioni del portale. È necessaria la connessione Internet.</p>
			<button class="mi-secondary" type="button" data-mi-pwa-prompt hidden>Installa app</button>
			<p data-mi-pwa-help><strong>Android:</strong> apri il menu di Chrome e scegli «Installa app» o «Aggiungi a schermata Home». <strong>iPhone:</strong> apri il portale in Safari, tocca Condividi e scegli «Aggiungi alla schermata Home»; se compare, attiva «Apri come app web».</p>
			<p>Puoi sempre aprire lo stesso indirizzo nel browser. Per rimuovere l’app usa i comandi del telefono: i dati della segreteria rimangono nel portale.</p>
			<p role="status" aria-live="polite" data-mi-pwa-status></p>
			<?php if ( self::can_edit_icon( $group_id ) ) : ?>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mi-pwa-icon-form">
				<input type="hidden" name="action" value="mi_save_pwa_icon"><input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>">
				<?php wp_nonce_field( 'mi_save_pwa_icon_' . $group_id ); ?>
				<label>Icona del gruppo<input type="file" name="mi_pwa_icon" accept="image/png" data-mi-max-bytes="2097152"><small>PNG quadrato, da 512 a 4096 pixel per lato, massimo 2 MB.</small></label>
				<label class="mi-check"><input type="checkbox" name="remove_icon" value="1"> Ripristina l’icona della segreteria</label>
				<button class="mi-secondary" type="submit">Salva icona del gruppo</button>
			</form>
			<?php endif; ?>
		</details>
		<?php
	}

	/** Conserva il contesto grafico nei normali collegamenti, senza cambiare l'ambito dei dati. */
	public static function with_group_url( $url ) {
		if ( ! self::enabled() || ! isset( $_GET['mi_pwa_group'] ) || ! is_scalar( $_GET['mi_pwa_group'] ) ) return $url;
		return add_query_arg( 'mi_pwa_group', self::group( absint( $_GET['mi_pwa_group'] ) ) ? absint( $_GET['mi_pwa_group'] ) : 0, $url );
	}

	public static function save_icon() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) wp_die( 'Metodo non consentito.', '', array( 'response' => 405 ) );
		$group_id = absint( $_POST['group_id'] ?? 0 );
		if ( ! self::can_edit_icon( $group_id ) ) wp_die( 'Gruppo non accessibile.', '', array( 'response' => 403 ) );
		check_admin_referer( 'mi_save_pwa_icon_' . $group_id );
		$result = self::store_icon( $group_id );
		wp_safe_redirect( add_query_arg( array( 'mi_portal' => '1', 'mi_portal_view' => 'groups', 'mi_pwa_group' => $group_id, 'mi_pwa_notice' => is_wp_error( $result ) ? 'error' : 'saved' ), home_url( '/' ) ) . '#mi-app' );
		exit;
	}

	public static function store_icon( $group_id ) {
		// Verifica anche qui: nessun chiamante può aggirare il confine del gruppo.
		if ( ! self::can_edit_icon( $group_id ) ) return new WP_Error( 'mi_pwa_forbidden', 'Gruppo non accessibile.' );
		$file = $_FILES['mi_pwa_icon'] ?? array();
		if ( empty( $file['name'] ) ) {
			if ( '1' === ( $_POST['remove_icon'] ?? '' ) ) delete_post_meta( $group_id, '_mi_pwa_icon_id' );
			return true;
		}
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || (int) $file['size'] > 2 * MB_IN_BYTES ) return new WP_Error( 'mi_pwa_file', 'File non valido.' );
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ), array( 'png' => 'image/png' ) );
		$dimensions = wp_getimagesize( $file['tmp_name'] );
		if ( 'image/png' !== ( $checked['type'] ?? '' ) || ! $dimensions || 'image/png' !== ( $dimensions['mime'] ?? '' ) || $dimensions[0] !== $dimensions[1] || $dimensions[0] < 512 || $dimensions[0] > 4096 ) return new WP_Error( 'mi_pwa_image', 'Immagine non valida.' );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$id = media_handle_upload( 'mi_pwa_icon', $group_id, array(), array( 'test_form' => false, 'mimes' => array( 'png' => 'image/png' ) ) );
		if ( is_wp_error( $id ) ) return $id;
		$path = get_attached_file( $id );
		$metadata = wp_get_attachment_metadata( $id );
		if ( ! is_array( $metadata ) ) return new WP_Error( 'mi_pwa_metadata', 'Immagine non elaborabile.' );
		foreach ( array( 192, 512 ) as $size ) {
			if ( $size === (int) $metadata['width'] && $size === (int) $metadata['height'] ) continue;
			$image = image_make_intermediate_size( $path, $size, $size, true );
			if ( ! $image || $size !== (int) $image['width'] || $size !== (int) $image['height'] ) return new WP_Error( 'mi_pwa_resize', 'Immagine non elaborabile.' );
			$metadata['sizes'][ 'mi_pwa_' . $size ] = $image;
		}
		wp_update_attachment_metadata( $id, $metadata );
		update_post_meta( $group_id, '_mi_pwa_icon_id', $id );
		return true;
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE, 'App sul telefono', 'App sul telefono', 'manage_options', 'mi-portal-pwa', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accesso non consentito.', '', array( 'response' => 403 ) );
		?>
		<div class="wrap">
			<h1>App sul telefono</h1>
			<?php if ( '1' === ( $_GET['saved'] ?? '' ) ) : ?><div class="notice notice-success"><p>Impostazione salvata.</p></div><?php endif; ?>
			<p>Il gestionale rimane disponibile dal browser. Questa opzione aggiunge l’installazione con un’icona sul telefono, senza nuovi servizi o abbonamenti.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mi_save_portal_pwa">
				<?php wp_nonce_field( 'mi_save_portal_pwa' ); ?>
				<p><label><input type="checkbox" name="mi_portal_pwa_enabled" value="1" <?php checked( self::enabled() ); ?>> Consenti l’installazione di Segreteria eventi come app</label></p>
				<?php submit_button( 'Salva impostazione' ); ?>
			</form>
			<h2>Tornare alla sola pagina web</h2>
			<p>Disattiva l’opzione e salva. Il portale, i collegamenti e i dati continuano a funzionare. Le icone già installate si rimuovono dal singolo telefono: non vengono disinstallate a distanza.</p>
			<p>L’app richiede Internet per consultare e salvare i dati. L’installazione non rende più veloci i salvataggi o la sincronizzazione Google.</p>
		</div>
		<?php
	}

	public static function save() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) wp_die( 'Metodo non consentito.', '', array( 'response' => 405 ) );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accesso non consentito.', '', array( 'response' => 403 ) );
		check_admin_referer( 'mi_save_portal_pwa' );
		update_option( self::OPTION, '1' === ( $_POST['mi_portal_pwa_enabled'] ?? '' ) ? '1' : '0', false );
		wp_safe_redirect( add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-portal-pwa', 'saved' => '1' ), admin_url( 'edit.php' ) ) );
		exit;
	}
}
