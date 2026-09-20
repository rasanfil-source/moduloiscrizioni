<?php
define( 'ABSPATH', __DIR__ );
define( 'MI_PLUGIN_DIR', __DIR__ . '/../modulo-iscrizioni/' );
define( 'MI_PLUGIN_URL', 'https://example.test/plugin/' );
require MI_PLUGIN_DIR . 'includes/class-mi-assets.php';
function verify_asset( $ok ) { if ( ! $ok ) throw new RuntimeException( 'Asset assertion failed' ); }
foreach ( glob( MI_PLUGIN_DIR . 'assets/*.*' ) as $file ) {
	if ( ! in_array( pathinfo( $file, PATHINFO_EXTENSION ), array( 'css','js' ), true ) ) continue;
	$name = basename( $file ); $url = MI_Assets::filter_url( MI_PLUGIN_URL . 'assets/' . $name . '?ver=1' );
	verify_asset( strpos( $url, '/assets/min/' . $name . '?mi_asset=' ) !== false && substr( $url, -6 ) === '&ver=1' );
}
verify_asset( MI_Assets::filter_url( 'https://other.test/main.js' ) === 'https://other.test/main.js' );
verify_asset( MI_Assets::url( 'missing.js' ) === MI_PLUGIN_URL . 'assets/missing.js' );
define( 'SCRIPT_DEBUG', true );
verify_asset( MI_Assets::url( 'core.js' ) === MI_PLUGIN_URL . 'assets/core.js' );
echo "Asset URLs, cache keys, missing-file fallback and debug mode OK\n";
