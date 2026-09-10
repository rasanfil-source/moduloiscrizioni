<?php
// The browser harness invokes this same production selector via stdin.
define( 'ABSPATH', __DIR__ );
function wp_json_encode( $value ) { return json_encode( $value ); }
function remove_accents( $value ) { return strtr( $value, array( 'à'=>'a', 'è'=>'e', 'é'=>'e', 'ì'=>'i', 'ò'=>'o', 'ù'=>'u' ) ); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-list.php';
if ( in_array( '--json', $argv, true ) ) {
	$input = json_decode( stream_get_contents( STDIN ), true );
	echo json_encode( 'compact' === ( $input['operation'] ?? '' ) ? MI_Management_List::compact( $input['summary'] ) : MI_Management_List::page( $input['summary'], $input['context'] ?? array(), $input['offset'] ?? 0, $input['limit'] ?? 30 ) );
	exit;
}
function check_list( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
$summary = array( 'people'=>array(), 'items'=>array() );
for ( $i=1; $i<=65; $i++ ) $summary['people'][] = array( 'id'=>$i, 'number'=>$i, 'code'=>'TEST', 'name'=>'Persona '.$i, 'buyer'=>'Referente', 'email'=>'family@example.invalid', 'phone'=>'123', 'status'=>'CONFIRMED', 'room'=>'A', 'options'=>array(), 'fields'=>array( 'diet'=>'Test' ), 'missing'=>array(), 'collectible'=>true, 'unassigned'=>false, 'requests'=>'', 'requests_reviewed'=>false );
$summary['items'][] = array( 'code'=>'TEST', 'name'=>'Referente', 'status'=>'CONFIRMED', 'active'=>true, 'balance'=>100, 'collectible'=>true, 'missing'=>0, 'unassigned'=>0 );
$first = MI_Management_List::page( $summary, array() );
$second = MI_Management_List::page( $summary, array(), 30 );
$third = MI_Management_List::page( $summary, array(), 60 );
check_list( count( $first['rows'] )===30 && count( $second['rows'] )===30 && count( $third['rows'] )===5, 'Bounded complete pages' );
check_list( count( array_unique( array_column( array_merge( $first['rows'], $second['rows'], $third['rows'] ), 'id' ) ) )===65, 'No duplicates or omissions' );
check_list( $first['rows'][1]['name']==='Persona 2', 'Natural ordering' );
check_list( $first['fingerprint']===$third['fingerprint'], 'Stable snapshot checksum' );
check_list( MI_Management_List::page( $summary, array( 'query'=>'Persona 65' ) )['total']===1, 'Search beyond initial page' );
check_list( MI_Management_List::page( $summary, array( 'query'=>'Persona 65','view'=>'orders' ) )['total']===1, 'Order found by individual name' );
check_list( MI_Management_List::page( $summary, array( 'room'=>'room:B' ) )['total']===0, 'Room filtering' );
$summary['people'][64]['requests']='Richiesta';
$summary['people'][64]['options']=array( array( 'code'=>'meal','quantity'=>1 ) );
check_list( MI_Management_List::page( $summary, array( 'requests'=>'pending','service'=>'meal' ) )['total']===1, 'Combined request and general service filters' );
check_list( MI_Management_List::page( $summary, array( 'requests'=>'reviewed' ) )['total']===0, 'Review state' );
check_list( MI_Management_List::page( $summary, array() )['fingerprint']!==$first['fingerprint'], 'Changes invalidate multi-page exports' );
$summary['people'][64]['status']='CANCELLED';
check_list( MI_Management_List::page( $summary, array( 'filter'=>'balance' ) )['total']===64, 'Closed participants excluded from receivables' );
$summary['people'][64]['status']='WAITLIST_OFFERED';
$summary['people'][64]['offer_expires_at']=gmdate( 'Y-m-d H:i:s', time()+3600 );
check_list( MI_Management_List::page( $summary, array( 'deadline'=>'soon' ) )['total']===1, 'Offer deadline' );
$summary['people'][0]['deposit_missing']=1000;
$summary['people'][1]['deposit_covered']=true;$summary['people'][1]['balance']=2000;
$summary['people'][64]['deposit_missing']=2000;
check_list(MI_Management_List::page($summary,array('deposit'=>'missing'))['total']===1,'Deposit collection excludes waitlist offers');
check_list(MI_Management_List::page($summary,array('deposit'=>'covered'))['total']===1,'Covered deposit with remaining balance');
$summary['people'][1]['balance']=0;
check_list(MI_Management_List::page($summary,array('deposit'=>'covered'))['total']===0,'Settled order excluded from deposit balance filter');
$compact=MI_Management_List::compact( $summary );
check_list( ! isset( $compact['people'][0]['fields'], $compact['people'][0]['email'] ) && $compact['field_keys']===array('diet'), 'Summary omits full personal records but preserves column definitions' );
$payment_summary=$summary;
$payment_summary['people']=array();
foreach(array(array(0,10000,3000,false),array(1000,9000,2000,false),array(3000,7000,0,true),array(10000,0,0,true)) as $i=>$amounts){
	$row=$summary['people'][0];$row['id']=$i+1;$row['status']='CONFIRMED';
	list($row['paid'],$row['balance'],$row['deposit_missing'],$row['deposit_covered'])=$amounts;
	$payment_summary['people'][]=$row;
}
foreach(array('none'=>1,'partial'=>2,'covered'=>3,'settled'=>4) as $filter=>$id){
	$result=MI_Management_List::page($payment_summary,array('deposit'=>$filter));
	check_list($result['total']===1 && $result['rows'][0]['id']===$id,'Payment filter '.$filter);
}
check_list(MI_Management_List::page($payment_summary,array('deposit'=>'unpaid'))['total']===3,'Single payment includes partial payments');
echo "Paginazione, ricerca globale, filtri combinati, coerenza esportazioni e riepilogo compatto verificati.\n";
