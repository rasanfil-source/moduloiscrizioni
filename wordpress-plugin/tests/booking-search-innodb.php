<?php
require __DIR__.'/innodb-fixture.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-booking-search.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
function wp_date($format){return date($format);}
function get_the_title($id){return 'L’uomo nel progetto di Dio &#8211; Chi Sono?';}
function remove_accents($value){return $value;}
$source=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-portal-payments.php');
$source=preg_replace('/^<\?php/', '', $source);
$source=str_replace(array("require_once __DIR__ . '/class-mi-booking-search.php';",'final class MI_Portal_Payments'),array('','final class SearchPaymentsTest'),$source);
eval($source);
$wpdb=new DatabaseAdapter();$wpdb->db->select_db('mi_ledger_test');
$wpdb->query('CREATE TEMPORARY TABLE wp_postmeta (post_id BIGINT, meta_key VARCHAR(255), meta_value LONGTEXT)');
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
 $personResult=SearchPaymentsTest::search('Seconda Persona 65',1,42)['prenotazioni'][0];
 verify_search($personResult['nome']==='Persona Seconda 65','Search foregrounds the matched participant instead of the buyer');
 verify_search(count($personResult['matched_participant_ids'])===1,'Matched participant identity is retained for payment selection');
 verify_search(count(SearchPaymentsTest::search('12345678',1,42)['prenotazioni'])===30,'Buyer telephone');
 verify_search(count(SearchPaymentsTest::search('ricerca@example.invalid',1,43)['prenotazioni'])===0,'Event scope');
 verify_search(!empty($first['prenotazioni'][0]['partecipanti']),'Result identifies participants');
 verify_search($first['prenotazioni'][0]['evento']==='L’uomo nel progetto di Dio – Chi Sono?','Event title is plain text, not HTML entities');
 verify_search(MI_Payment_Ledger::detail($first['prenotazioni'][0]['id'])['saldo']['evento']==='L’uomo nel progetto di Dio – Chi Sono?','Opened payment detail decodes the event title');
 $wpdb->insert('wp_postmeta',array('post_id'=>42,'meta_key'=>'_mi_pricing_mode','meta_value'=>'ZERO'));
 verify_search(SearchPaymentsTest::search('ricerca@example.invalid',1,42)===array('prenotazioni'=>array(),'has_more'=>false),'Free events excluded from scoped search');
 verify_search(SearchPaymentsTest::search('SEARCH-1')===array('prenotazioni'=>array(),'has_more'=>false),'Free events excluded from code search');
 verify_search(MI_Booking_Search::matches(array('Persona Seconda 65','Referente Famiglia'),'Seconda Persona 65'),'List uses same word matching');
 echo "Ricerca MySQL: contatti, paginazione, permessi, esclusione gratuiti e titoli decodificati in ricerca e dettaglio verificati.\n";
}finally{$wpdb->query('ROLLBACK');}
