<?php
/** Synthetic, personal-data-free benchmark for the shared management selector. */
define( 'ABSPATH', __DIR__ );
function wp_json_encode( $value ) { return json_encode( $value ); }
function remove_accents( $value ) { return strtr( $value, array( 'à'=>'a', 'è'=>'e', 'é'=>'e', 'ì'=>'i', 'ò'=>'o', 'ù'=>'u' ) ); }
require __DIR__ . '/../wordpress-plugin/modulo-iscrizioni/includes/class-mi-management-list.php';

function synthetic_summary( $count ) {
	$people = array();
	for ( $index = 1; $index <= $count; $index++ ) $people[] = array(
		'id'=>$index, 'number'=>$index, 'code'=>'ORD-' . str_pad( (string) $index, 6, '0', STR_PAD_LEFT ),
		'name'=>( 0 === $index % 17 ? 'René ' : 'Persona ' ) . $index, 'buyer'=>'Referente ' . ( $index % 137 ),
		'email'=>'example@example.invalid', 'phone'=>'000', 'status'=>'CONFIRMED', 'room'=>'Camera ' . ( $index % 40 ),
		'options'=>array(), 'fields'=>array(), 'missing'=>array(), 'collectible'=>true, 'unassigned'=>false,
		'requests'=>0 === $index % 11 ? 'Richiesta' : '', 'requests_reviewed'=>false,
	);
	return array( 'people'=>$people, 'items'=>array() );
}

foreach ( array( 500, 10000 ) as $count ) {
	$summary = synthetic_summary( $count );
	$start = hrtime( true );
	$page = MI_Management_List::page( $summary, array( 'sort'=>'name', 'direction'=>'asc' ), 300, 30 );
	$complete_ms = ( hrtime( true ) - $start ) / 1000000;
	$start = hrtime( true ); $prefix = array(); $seen = 0;
	foreach ( array_chunk( $summary['people'], 200 ) as $chunk ) {
		$filtered = array_values( array_filter( $chunk, static function ( $row ) { return '' !== $row['requests']; } ) );
		$seen += count( $filtered );
		$prefix = MI_Management_List::sorted_prefix( $prefix, $filtered, array( 'sort'=>'name', 'direction'=>'asc' ), 330 );
	}
	$advanced_ms = ( hrtime( true ) - $start ) / 1000000;
	printf( "%d righe: selettore completo %.2f ms; scansione avanzata %.2f ms; trattenute %d; corrispondenze %d; pagina %d.\n", $count, $complete_ms, $advanced_ms, count( $prefix ), $seen, count( $page['rows'] ) );
	if ( count( $prefix ) > 330 || 30 !== count( $page['rows'] ) ) throw new RuntimeException( 'Il benchmark ha violato il limite di memoria della pagina.' );
}
