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
		$source = MI_PLUGIN_DIR . 'assets/' . $name;
		$minified = MI_PLUGIN_DIR . 'assets/min/' . $name;
		if ( ! $entry || ! is_readable( $source ) || ! is_readable( $minified ) || hash_file( 'sha256', $source ) !== $entry['source'] || hash_file( 'sha256', $minified ) !== $entry['hash'] ) return $urls[$name] = $original;
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
