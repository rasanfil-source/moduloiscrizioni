<?php
define( 'ABSPATH', __DIR__ );
define( 'MI_PLUGIN_DIR', __DIR__ . '/../modulo-iscrizioni/' );
define( 'MI_PLUGIN_URL', 'https://example.test/plugin/' );
define( 'MI_VERSION', 'test' );
require MI_PLUGIN_DIR . 'includes/class-mi-assets.php';
function verify_asset( $ok ) { if ( ! $ok ) throw new RuntimeException( 'Asset assertion failed' ); }
foreach ( glob( MI_PLUGIN_DIR . 'assets/*.*' ) as $file ) {
	if ( ! in_array( pathinfo( $file, PATHINFO_EXTENSION ), array( 'css','js' ), true ) ) continue;
	$name = basename( $file ); $url = MI_Assets::filter_url( MI_PLUGIN_URL . 'assets/' . $name . '?ver=1' );
	verify_asset( strpos( $url, '/assets/min/' . $name . '?mi_asset=' ) !== false && substr( $url, -6 ) === '&ver=1' );
}
verify_asset( MI_Assets::filter_url( 'https://other.test/main.js' ) === 'https://other.test/main.js' );
verify_asset( MI_Assets::url( 'missing.js' ) === MI_PLUGIN_URL . 'assets/missing.js' );
verify_asset( false === strpos( file_get_contents( MI_PLUGIN_DIR . 'includes/class-mi-assets.php' ), 'hash_file(' ) );
verify_asset( false === strpos( file_get_contents( MI_PLUGIN_DIR . 'includes/class-mi-portal.php' ), 'hash_file(' ) );
verify_asset( false === strpos( file_get_contents( MI_PLUGIN_DIR . 'includes/class-mi-portal.php' ), 'filemtime(' ) );
define( 'SCRIPT_DEBUG', true );
verify_asset( MI_Assets::url( 'core.js' ) === MI_PLUGIN_URL . 'assets/core.js' );
// Esercita il caricamento reale dello shortcode: core deve precedere public,
// anche se WordPress deve rimuovere defer per una dipendenza o uno script inline.
function wp_enqueue_style( ...$args ) {}
function wp_enqueue_script( $handle, $src, $deps, $version, $args ) {
	$GLOBALS['enqueued_scripts'][$handle] = compact( 'src', 'deps', 'version', 'args' );
}
class WP_Post { public $post_content = '[modulo_iscrizioni]'; }
function is_singular() { return true; }
function has_shortcode( $content, $shortcode ) { return false !== strpos( $content, '[' . $shortcode . ']' ); }
require MI_PLUGIN_DIR . 'includes/class-mi-shortcode.php';
$post = new WP_Post();
$enqueued_scripts = array();
MI_Shortcode::maybe_enqueue_assets();
verify_asset( array_keys( $enqueued_scripts ) === array( 'mi-core', 'mi-public' ) );
verify_asset( $enqueued_scripts['mi-core']['deps'] === array() );
verify_asset( $enqueued_scripts['mi-public']['deps'] === array( 'mi-core' ) );
foreach ( $enqueued_scripts as $script ) {
	verify_asset( $script['args']['in_footer'] === true && $script['args']['strategy'] === 'defer' );
}
$post->post_content = 'Pagina senza modulo';
$enqueued_scripts = array();
MI_Shortcode::maybe_enqueue_assets();
verify_asset( $enqueued_scripts === array() );
echo "Asset URLs, cache keys, missing-file fallback, debug mode and deferred shortcode loading OK\n";
