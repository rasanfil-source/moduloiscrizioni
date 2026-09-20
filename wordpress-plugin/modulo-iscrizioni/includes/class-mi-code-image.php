<?php

defined( 'ABSPATH' ) || exit;

final class MI_Code_Image {
	public static function svg( $mode, $payload ) {
		$mode = strtoupper( (string) $mode );
		return 'QR' === $mode ? self::qr_svg( (string) $payload ) : self::barcode_svg( (string) $payload );
	}

	private static function qr_svg( $payload ) {
		$bytes = array_values( unpack( 'C*', $payload ) ?: array() );
		if ( count( $bytes ) > 106 ) {
			$bytes = array_slice( $bytes, 0, 106 );
		}
		$bits = array( 0, 1, 0, 0 );
		self::append_bits( $bits, count( $bytes ), 8 );
		foreach ( $bytes as $byte ) self::append_bits( $bits, $byte, 8 );
		for ( $i = 0; $i < min( 4, 864 - count( $bits ) ); $i++ ) $bits[] = 0;
		while ( count( $bits ) % 8 ) $bits[] = 0;
		$data = array();
		for ( $offset = 0; $offset < count( $bits ); $offset += 8 ) {
			$value = 0;
			for ( $i = 0; $i < 8; $i++ ) $value = ( $value << 1 ) | $bits[ $offset + $i ];
			$data[] = $value;
		}
		$pad = array( 0xec, 0x11 );
		while ( count( $data ) < 108 ) $data[] = $pad[ count( $data ) % 2 ];
		$codewords = array_merge( $data, self::reed_solomon( $data, 26 ) );
		$size = 37;
		$matrix = array_fill( 0, $size, array_fill( 0, $size, null ) );
		self::finder( $matrix, 0, 0 ); self::finder( $matrix, 0, $size - 7 ); self::finder( $matrix, $size - 7, 0 );
		for ( $i = 8; $i < $size - 8; $i++ ) { if ( null === $matrix[6][ $i ] ) $matrix[6][ $i ] = 0 === $i % 2; if ( null === $matrix[ $i ][6] ) $matrix[ $i ][6] = 0 === $i % 2; }
		self::alignment( $matrix, 30, 30 );
		self::format_info( $matrix, 0 );
		$matrix[29][8] = true;
		self::place_data( $matrix, $codewords, 0 );
		return self::matrix_svg( $matrix );
	}

	private static function append_bits( &$bits, $value, $length ) {
		for ( $i = $length - 1; $i >= 0; $i-- ) $bits[] = ( $value >> $i ) & 1;
	}

	private static function finder( &$matrix, $row, $column ) {
		$size = count( $matrix );
		for ( $r = -1; $r <= 7; $r++ ) for ( $c = -1; $c <= 7; $c++ ) {
			$y = $row + $r; $x = $column + $c;
			if ( $y < 0 || $x < 0 || $y >= $size || $x >= $size ) continue;
			$matrix[ $y ][ $x ] = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6 && ( 0 === $r || 6 === $r || 0 === $c || 6 === $c || ( $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4 ) );
		}
	}

	private static function alignment( &$matrix, $row, $column ) {
		for ( $r = -2; $r <= 2; $r++ ) for ( $c = -2; $c <= 2; $c++ ) $matrix[ $row + $r ][ $column + $c ] = 2 === abs( $r ) || 2 === abs( $c ) || ( 0 === $r && 0 === $c );
	}

	private static function format_info( &$matrix, $mask ) {
		$size = count( $matrix );
		$data = ( 1 << 3 ) | $mask;
		$value = $data << 10;
		while ( self::bit_length( $value ) >= 11 ) $value ^= 0x537 << ( self::bit_length( $value ) - 11 );
		$format = ( ( $data << 10 ) | $value ) ^ 0x5412;
		for ( $i = 0; $i < 15; $i++ ) {
			$dark = 1 === ( ( $format >> $i ) & 1 );
			if ( $i < 6 ) $matrix[ $i ][8] = $dark; elseif ( $i < 8 ) $matrix[ $i + 1 ][8] = $dark; else $matrix[ $size - 15 + $i ][8] = $dark;
			if ( $i < 8 ) $matrix[8][ $size - $i - 1 ] = $dark; elseif ( 8 === $i ) $matrix[8][7] = $dark; else $matrix[8][ 15 - $i - 1 ] = $dark;
		}
	}

	private static function bit_length( $value ) { $length = 0; while ( $value ) { $length++; $value >>= 1; } return $length; }

	private static function place_data( &$matrix, $codewords, $mask ) {
		$size = count( $matrix ); $row = $size - 1; $direction = -1; $byte = 0; $bit = 7;
		for ( $column = $size - 1; $column > 0; $column -= 2 ) {
			if ( 6 === $column ) $column--;
			while ( true ) {
				for ( $offset = 0; $offset < 2; $offset++ ) {
					$x = $column - $offset;
					if ( null !== $matrix[ $row ][ $x ] ) continue;
					$dark = $byte < count( $codewords ) && 1 === ( ( $codewords[ $byte ] >> $bit ) & 1 );
					if ( 0 === ( $row + $x ) % 2 ) $dark = ! $dark;
					$matrix[ $row ][ $x ] = $dark;
					$bit--; if ( $bit < 0 ) { $byte++; $bit = 7; }
				}
				$row += $direction;
				if ( $row < 0 || $row >= $size ) { $row -= $direction; $direction = -$direction; break; }
			}
		}
	}

	private static function reed_solomon( $data, $degree ) {
		list( $exp, $log ) = self::gf_tables();
		$generator = array( 1 );
		for ( $i = 0; $i < $degree; $i++ ) {
			$next = array_fill( 0, count( $generator ) + 1, 0 );
			foreach ( $generator as $position => $coefficient ) { $next[ $position ] ^= $coefficient; $next[ $position + 1 ] ^= self::gf_multiply( $coefficient, $exp[ $i ], $exp, $log ); }
			$generator = $next;
		}
		$result = array_merge( $data, array_fill( 0, $degree, 0 ) );
		for ( $i = 0; $i < count( $data ); $i++ ) {
			$factor = $result[ $i ]; if ( 0 === $factor ) continue;
			foreach ( $generator as $position => $coefficient ) $result[ $i + $position ] ^= self::gf_multiply( $coefficient, $factor, $exp, $log );
		}
		return array_slice( $result, -$degree );
	}

	private static function gf_tables() {
		$exp = array_fill( 0, 512, 0 ); $log = array_fill( 0, 256, 0 ); $value = 1;
		for ( $i = 0; $i < 255; $i++ ) { $exp[ $i ] = $value; $log[ $value ] = $i; $value <<= 1; if ( $value & 0x100 ) $value ^= 0x11d; }
		for ( $i = 255; $i < 512; $i++ ) $exp[ $i ] = $exp[ $i - 255 ];
		return array( $exp, $log );
	}

	private static function gf_multiply( $left, $right, $exp, $log ) { return 0 === $left || 0 === $right ? 0 : $exp[ $log[ $left ] + $log[ $right ] ]; }

	private static function matrix_svg( $matrix ) {
		$quiet = 4; $size = count( $matrix ); $view = $size + 2 * $quiet; $rects = '';
		foreach ( $matrix as $row => $columns ) foreach ( $columns as $column => $dark ) if ( $dark ) $rects .= '<rect x="' . ( $column + $quiet ) . '" y="' . ( $row + $quiet ) . '" width="1" height="1"/>';
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $view . ' ' . $view . '" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="white"/><g fill="black">' . $rects . '</g></svg>';
	}

	private static function barcode_svg( $payload ) {
		$patterns = array( '0'=>'nnnwwnwnn','1'=>'wnnwnnnnw','2'=>'nnwwnnnnw','3'=>'wnwwnnnnn','4'=>'nnnwwnnnw','5'=>'wnnwwnnnn','6'=>'nnwwwnnnn','7'=>'nnnwnnwnw','8'=>'wnnwnnwnn','9'=>'nnwwnnwnn','A'=>'wnnnnwnnw','B'=>'nnwnnwnnw','C'=>'wnwnnwnnn','D'=>'nnnnwwnnw','E'=>'wnnnwwnnn','F'=>'nnwnwwnnn','G'=>'nnnnnwwnw','H'=>'wnnnnwwnn','I'=>'nnwnnwwnn','J'=>'nnnnwwwnn','K'=>'wnnnnnnww','L'=>'nnwnnnnww','M'=>'wnwnnnnwn','N'=>'nnnnwnnww','O'=>'wnnnwnnwn','P'=>'nnwnwnnwn','Q'=>'nnnnnnwww','R'=>'wnnnnnwwn','S'=>'nnwnnnwwn','T'=>'nnnnwnwwn','U'=>'wwnnnnnnw','V'=>'nwwnnnnnw','W'=>'wwwnnnnnn','X'=>'nwnnwnnnw','Y'=>'wwnnwnnnn','Z'=>'nwwnwnnnn','-'=>'nwnnnnwnw','.'=>'wwnnnnwnn',' '=>'nwwnnnwnn','*'=>'nwnnwnwnn' );
		$text = strtoupper( preg_replace( '/[^0-9A-Z. -]/', '', $payload ) ); $encoded = '*' . $text . '*'; $x = 10; $bars = '';
		foreach ( str_split( $encoded ) as $character ) {
			$pattern = $patterns[ $character ] ?? $patterns['-'];
			foreach ( str_split( $pattern ) as $index => $width ) { $units = 'w' === $width ? 3 : 1; if ( 0 === $index % 2 ) $bars .= '<rect x="' . $x . '" y="5" width="' . $units . '" height="60"/>'; $x += $units; }
			$x += 1;
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . ( $x + 10 ) . ' 82"><rect width="100%" height="100%" fill="white"/><g fill="black">' . $bars . '</g><text x="50%" y="78" text-anchor="middle" font-family="monospace" font-size="10">' . esc_html( $text ) . '</text></svg>';
	}
}
