<?php
// Contratti PWA: identità pubblica, permessi del gruppo, caricamento e disattivazione.
$fixture_dir = sys_get_temp_dir() . '/mi-pwa-' . bin2hex( random_bytes( 6 ) );
mkdir( $fixture_dir . '/wp-admin/includes', 0777, true );
foreach ( array( 'file', 'image', 'media' ) as $stub ) file_put_contents( $fixture_dir . '/wp-admin/includes/' . $stub . '.php', '<?php' );
define( 'ABSPATH', $fixture_dir . '/' );
define( 'MI_PLUGIN_DIR', __DIR__ . '/../modulo-iscrizioni/' );
define( 'MI_PLUGIN_URL', 'https://example.invalid/site/plugin/' );
define( 'MI_VERSION', 'test' );
define( 'MB_IN_BYTES', 1048576 );
class WP_Error { public function __construct( $code = '', $message = '' ) {} }
class MI_Event_Post_Type { const GROUP_TYPE = 'mi_activity'; }
class MI_Portal { const SHORTCODE = 'mi_portale_gestione'; }
class MI_Access {
	public static function is_suspended() { return $GLOBALS['suspended']; }
	public static function activity_ids() { return $GLOBALS['scope']; }
	public static function event_ids() { $GLOBALS['event_scope_reads']++; return $GLOBALS['events']; }
	public static function can_access_activity( $id ) { return 'ALL' === $GLOBALS['scope'] || in_array( $id, $GLOBALS['scope'], true ); }
}
function verify_pwa( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_action( $name, $callback, $priority = 10 ) { $GLOBALS['actions'][ $name ][] = $callback; }
function absint( $value ) { return abs( (int) $value ); }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function is_ssl() { return $GLOBALS['https']; }
function is_singular() { return $GLOBALS['singular']; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['caps'], true ); }
function wp_get_current_user() { return (object) array( 'roles' => $GLOBALS['roles'] ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; }
function get_post( $id = null ) { return null === $id ? $GLOBALS['page'] : ( $GLOBALS['groups'][ $id ] ?? null ); }
function get_posts( $args ) { return array_values( array_filter( $GLOBALS['groups'], static function ( $g ) use ( $args ) { return 'publish' === $g->post_status && ( ! isset( $args['post__in'] ) || in_array( $g->ID, $args['post__in'], true ) ); } ) ); }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); }
function has_shortcode( $text, $name ) { return false !== strpos( $text, '[' . $name . ']' ); }
function home_url( $path ) { return 'https://example.invalid/site' . $path; }
function admin_url( $path ) { return home_url( '/wp-admin/' . $path ); }
function add_query_arg( $key, $value, $url = null ) {
	$args = is_array( $key ) ? $key : array( $key => $value );
	$url = is_array( $key ) ? $value : $url;
	$parts = explode( '?', $url, 2 ); parse_str( $parts[1] ?? '', $existing );
	return $parts[0] . '?' . http_build_query( array_merge( $existing, $args ) );
}
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="synthetic">'; }
function selected( $a, $b ) { if ( $a === $b ) echo 'selected'; }
function wp_check_filetype_and_ext( $path, $name, $mimes ) { return array( 'type' => $GLOBALS['mime'] ); }
function sanitize_file_name( $name ) { return basename( $name ); }
function wp_getimagesize( $path ) { return $GLOBALS['dimensions']; }
function media_handle_upload( $field, $parent, $data, $options ) { return 777; }
function get_attached_file( $id ) { return 'synthetic.png'; }
function wp_get_attachment_metadata( $id ) { return array( 'width' => 1024, 'height' => 1024, 'sizes' => array() ); }
function image_make_intermediate_size( $path, $width, $height, $crop ) { return $GLOBALS['resize_fail'] ? false : array( 'file' => 'icon-' . $width . '.png', 'width' => $width, 'height' => $height, 'mime-type' => 'image/png' ); }
function wp_update_attachment_metadata( $id, $metadata ) { $GLOBALS['attachments'][ $id ] = $metadata; }
function wp_get_attachment_image_src( $id, $size ) { $image = $GLOBALS['attachments'][ $id ]['sizes'][ $size ] ?? null; return $image ? array( 'https://example.invalid/uploads/' . $image['file'], $image['width'], $image['height'], true ) : false; }
function number_format_i18n( $value, $decimals ) { return number_format( $value, $decimals ); }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
require MI_PLUGIN_DIR . 'includes/class-mi-assets.php';
require MI_PLUGIN_DIR . 'includes/class-mi-portal-pwa.php';
$options = array(); $meta = array(); $attachments = array();
$logged_in = true; $https = true; $singular = false; $suspended = false;
$scope = array( 11 ); $events = array(); $roles = array( 'mi_group_manager' ); $caps = array( 'mi_portal_access' ); $page = null;
$event_scope_reads = 0;
$groups = array(); foreach ( array( 11, 22, 33 ) as $id ) $groups[ $id ] = (object) array( 'ID' => $id, 'post_type' => 'mi_activity', 'post_status' => 33 === $id ? 'draft' : 'publish', 'post_title' => 'Gruppo ' . $id );
$mime = 'image/png'; $dimensions = array( 1024, 1024, 'mime' => 'image/png' ); $resize_fail = false;
try {
	$_GET = array( 'mi_portal' => '1', 'mi_portal_event' => '999', 'token' => 'synthetic' );
	verify_pwa( MI_Portal_PWA::is_portal() && MI_Portal_PWA::enabled(), 'Portale installabile per impostazione predefinita' );
	$manifest = MI_Portal_PWA::manifest( 11 );
	verify_pwa( $manifest['display'] === 'standalone' && $manifest['scope'] === home_url( '/' ), 'Manifest su installazione in sottocartella' );
	verify_pwa( $manifest['id'] === $manifest['start_url'] && false === strpos( $manifest['start_url'], 'token' ) && false === strpos( $manifest['start_url'], '999' ), 'Avvio senza contesto privato' );
	verify_pwa( MI_Portal_PWA::manifest( 22 )['id'] !== $manifest['id'], 'Identità distinta per ogni gruppo' );
	verify_pwa( MI_Portal_PWA::manifest( 33 )['name'] === 'Segreteria eventi', 'Nessuna esposizione di gruppi in bozza' );
	verify_pwa( MI_Portal_PWA::selected_group_id() === 11 && count( MI_Portal_PWA::allowed_groups() ) === 1, 'Gruppo unico assegnato' );
	verify_pwa( 0 === $event_scope_reads, 'Installazione del gruppo senza caricare tutti i suoi eventi' );
	verify_pwa( MI_Portal_PWA::can_edit_icon( 11 ) && ! MI_Portal_PWA::can_edit_icon( 22 ), 'Gestore limitato al proprio gruppo' );
	$scope = array(); verify_pwa( MI_Portal_PWA::allowed_groups() === array(), 'Ambito vuoto non mostra tutti i gruppi' );
	$roles = array( 'mi_assigned_event_manager' ); $events = array( 101 ); $meta[101]['_mi_activity_id'] = 11;
	verify_pwa( array_column( MI_Portal_PWA::allowed_groups(), 'ID' ) === array( 11 ) && ! MI_Portal_PWA::can_edit_icon( 11 ), 'Gestore evento installa il gruppo assegnato senza modificarne l’icona' );
	$scope = array( 11 ); $events = array();
	$roles = array( 'mi_group_manager' ); $suspended = true; verify_pwa( ! MI_Portal_PWA::can_edit_icon( 11 ), 'Account sospeso' ); $suspended = false;
	$_FILES = array( 'mi_pwa_icon' => array( 'name' => 'icona.png', 'size' => 100, 'error' => 0, 'tmp_name' => 'synthetic.png' ) ); $_POST = array();
	verify_pwa( is_wp_error( MI_Portal_PWA::store_icon( 22 ) ), 'Upload di altro gruppo respinto' );
	verify_pwa( true === MI_Portal_PWA::store_icon( 11 ), 'Upload e due varianti' );
	verify_pwa( MI_Portal_PWA::manifest( 11 )['icons'][0]['src'] === 'https://example.invalid/uploads/icon-192.png', 'Manifest con immagine del gruppo' );
	verify_pwa( MI_Portal_PWA::apple_icon() === 'https://example.invalid/uploads/icon-192.png', 'iPhone usa la stessa icona del gruppo' );
	foreach ( array( 'mime', 'rectangular', 'small', 'large', 'bytes', 'resize' ) as $invalid ) {
		$mime = 'mime' === $invalid ? 'image/jpeg' : 'image/png';
		$dimensions = array( 'small' === $invalid ? 128 : ( 'large' === $invalid ? 5000 : 1024 ), 'rectangular' === $invalid ? 512 : ( 'small' === $invalid ? 128 : ( 'large' === $invalid ? 5000 : 1024 ) ), 'mime' => 'image/png' );
		$_FILES['mi_pwa_icon']['size'] = 'bytes' === $invalid ? 3 * MB_IN_BYTES : 100;
		$resize_fail = 'resize' === $invalid;
		verify_pwa( is_wp_error( MI_Portal_PWA::store_icon( 11 ) ) && get_post_meta( 11, '_mi_pwa_icon_id', true ) === 777, 'Errore preserva icona precedente: ' . $invalid );
	}
	$_FILES = array(); $_POST['remove_icon'] = '1'; MI_Portal_PWA::store_icon( 11 );
	verify_pwa( MI_Portal_PWA::manifest( 11 )['icons'][0]['src'] === MI_PLUGIN_URL . 'assets/portal-icon-192.png?ver=' . rawurlencode( MI_VERSION ), 'Ripristino icona predefinita' );
	foreach ( array( 'mi_status', 'mi_waitlist_offer', 'mi_cancel_participant' ) as $flag ) { $_GET[ $flag ] = '1'; ob_start(); MI_Portal_PWA::render_head(); verify_pwa( ob_get_clean() === '', 'Nessun manifest su pagina pubblica: ' . $flag ); unset( $_GET[ $flag ] ); }
	$options[ MI_Portal_PWA::OPTION ] = '0'; ob_start(); MI_Portal_PWA::render_head(); MI_Portal_PWA::render_install_help(); verify_pwa( ob_get_clean() === '', 'Disattivazione completa degli elementi PWA' );
	$options[ MI_Portal_PWA::OPTION ] = '1'; $_GET['mi_pwa_group'] = 11;
	ob_start(); MI_Portal_PWA::render_install_help(); $html = ob_get_clean();
	verify_pwa( strpos( $html, 'Salva icona del gruppo' ) !== false && strpos( $html, 'Installa app' ) !== false, 'Controlli installazione e icona' );
	if ( in_array( '--preview', $argv, true ) ) {
		ob_start(); MI_Portal_PWA::render_head(); $head = ob_get_clean();
		$preview = '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Segreteria eventi — prova sintetica</title><link rel="stylesheet" href="/assets/portal.css">' . $head . '</head><body class="mi-portal-standalone"><main class="mi-portal" data-mi-portal-scope="reserved"><header class="mi-portal-header"><div><span class="mi-portal-eyebrow">Area riservata · dimostrazione</span><h1>Segreteria eventi</h1></div><a class="mi-portal-logout" href="/?mi_portal=1">Esci</a></header><nav class="mi-portal-switcher" aria-label="Segreteria eventi"><a href="/?mi_portal=1&mi_portal_view=management">Iscrizioni</a><a class="is-active" href="/?mi_portal=1&mi_portal_view=manage">Eventi</a><a href="/?mi_portal=1&mi_portal_view=groups">Gruppi</a></nav>' . $html . '<h2>Eventi</h2><p>Anteprima locale con dati sintetici. Il gestionale conserva le sue funzioni.</p></main><script defer src="/assets/portal.js"></script></body></html>';
		$preview = str_replace( array( 'https://example.invalid/site/plugin/', 'https://example.invalid/site/' ), array( '/', '/' ), $preview );
		file_put_contents( __DIR__ . '/../../.tmp/pwa-preview.html', $preview );
		file_put_contents( __DIR__ . '/../../.tmp/pwa-manifest.json', str_replace( array( 'https://example.invalid/site/plugin/', 'https://example.invalid/site/' ), array( '/', '/' ), json_encode( MI_Portal_PWA::manifest( 11 ), JSON_UNESCAPED_SLASHES ) ) );
	}
	$https = false; ob_start(); MI_Portal_PWA::render_head(); verify_pwa( ob_get_clean() === '', 'Installazione solo HTTPS' );
	echo "PWA: identità, ambiti, icone e rollback verificati.\n";
} finally {
	foreach ( array( 'file', 'image', 'media' ) as $stub ) unlink( $fixture_dir . '/wp-admin/includes/' . $stub . '.php' );
	rmdir( $fixture_dir . '/wp-admin/includes' ); rmdir( $fixture_dir . '/wp-admin' ); rmdir( $fixture_dir );
}
