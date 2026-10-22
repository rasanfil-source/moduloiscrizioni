<?php
defined( 'ABSPATH' ) || exit;

final class MI_Assets {
	public static function url( $name ) {
		$original = MI_PLUGIN_URL . 'assets/' . $name;
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) return $original;
		static $manifest = null, $urls = array();
		if ( isset( $urls[$name] ) ) return $urls[$name];
		if ( null === $manifest ) {
			$file = MI_PLUGIN_DIR . 'assets/min/manifest.json';
			$manifest = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : array();
		}
		$entry = $manifest[$name] ?? null;
		$minified = MI_PLUGIN_DIR . 'assets/min/' . $name;
		// Gli hash vengono verificati durante build e packaging. Ricalcolarli durante
		// ogni richiesta aggiunge I/O al percorso pubblico senza aumentare la
		// sicurezza: manifest e file minificati appartengono allo stesso rilascio.
		if ( ! is_array( $entry ) || empty( $entry['hash'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', (string) $entry['hash'] ) || ! is_readable( $minified ) ) return $urls[$name] = $original;
		return $urls[$name] = MI_PLUGIN_URL . 'assets/min/' . $name . '?mi_asset=' . substr( $entry['hash'], 0, 16 );
	}

	public static function filter_url( $url ) {
		$prefix = MI_PLUGIN_URL . 'assets/';
		if ( 0 !== strpos( $url, $prefix ) ) return $url;
		$parts = explode( '?', substr( $url, strlen( $prefix ) ), 2 );
		if ( ! preg_match( '/^[a-z0-9][a-z0-9.-]*\.(js|css)$/D', $parts[0] ) ) return $url;
		$result = self::url( $parts[0] );
		return isset( $parts[1] ) ? $result . ( false === strpos( $result, '?' ) ? '?' : '&' ) . $parts[1] : $result;
	}
}
