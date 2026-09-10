<?php
require __DIR__.'/innodb-fixture.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-booking-search.php';
function get_the_title($id){return 'Evento sintetico';}
function remove_accents($value){return $value;}
$source=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-portal-payments.php');
$source=preg_replace('/^<\?php/', '', $source);
$source=str_replace(array("require_once __DIR__ . '/class-mi-booking-search.php';",'final class MI_Portal_Payments'),array('','final class SearchPaymentsTest'),$source);
eval($source);
$wpdb=new DatabaseAdapter();$wpdb->db->select_db('mi_ledger_test');
function verify_search($ok,$message){if(!$ok)throw new RuntimeException($message);}
$wpdb->query('START TRANSACTION');
try{
 for($i=1;$i<=65;$i++){
  $wpdb->insert('wp_mi_registrations',array('order_code'=>'SEARCH-'.$i,'event_id'=>42,'status'=>'CONFIRMED','buyer_first_name'=>'Referente','buyer_last_name'=>'Famiglia','buyer_email'=>'ricerca@example.invalid','buyer_phone'=>'12345678','total_qty'=>1,'idempotency_key'=>'search-'.$i,'created_at'=>gmdate('Y-m-d H:i:s'),'snapshot_json'=>'{}'));
  $id=$wpdb->insert_id;verify_search($id>0,'Fixture order');
  $wpdb->insert('wp_mi_participants',array('registration_id'=>$id,'first_name'=>'Persona','last_name'=>'Seconda '.$i,'extra_json'=>'{}'));
 }
 $first=SearchPaymentsTest::search('ricerca@example.invalid',1,42);
 $second=SearchPaymentsTest::search('ricerca@example.invalid',2,42);
 $last=SearchPaymentsTest::search('ricerca@example.invalid',3,42);
 verify_search(count($first['prenotazioni'])===30 && $first['has_more'] && count($last['prenotazioni'])===5 && !$last['has_more'],'Payment search reaches all results');
 $ids=array_column(array_merge($first['prenotazioni'],$second['prenotazioni'],$last['prenotazioni']),'id');verify_search(count(array_unique($ids))===65,'No duplicate bookings');
 verify_search(count(SearchPaymentsTest::search('Seconda Persona 65',1,42)['prenotazioni'])===1,'Individual name in either order');
 verify_search(count(SearchPaymentsTest::search('12345678',1,42)['prenotazioni'])===30,'Buyer telephone');
 verify_search(count(SearchPaymentsTest::search('ricerca@example.invalid',1,43)['prenotazioni'])===0,'Event scope');
 verify_search(!empty($first['prenotazioni'][0]['partecipanti']),'Result identifies participants');
 verify_search(MI_Booking_Search::matches(array('Persona Seconda 65','Referente Famiglia'),'Seconda Persona 65'),'List uses same word matching');
 echo "Ricerca MySQL: contatti, nominativi invertiti, pagine complete, assenza duplicati e ambito verificati.\n";
}finally{$wpdb->query('ROLLBACK');}
