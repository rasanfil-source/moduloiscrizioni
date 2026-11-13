<?php
$argv[]='--library';require __DIR__.'/public-balance-late-bus.php';
function get_post($id){return (object)['post_type'=>'mi_event','post_title'=>'Cammino sintetico'];}
function wp_cache_delete(...$args){}
function update_post_meta($id,$key,$value){$GLOBALS['late_meta'][$key]=$value;}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
class MI_Workspace_Client{static function stable_json($v){return json_encode($v);}}
class MI_Field_Schema{
 static function workspace_event_schema($id){return ['pricing'=>'CALCULATED','options'=>$GLOBALS['late_routes'],'fields'=>[]];}
 static function resolved_operational_profile($id){return 'CUSTOM';}
}
class LateProjectionDB extends LateBusDB{
 function get_results($query,$format){
  $sql=$query[0];
  if(str_contains($sql,'SELECT * FROM wp_mi_registrations'))return [$this->reg];
  if(str_contains($sql,'SELECT p.* FROM wp_mi_participants'))return $this->persons;
  if(str_contains($sql,'SELECT i.* FROM wp_mi_registration_items'))return [['registration_id'=>1,'ticket_type_code'=>'base','unit_price_cents'=>30000]];
  if(str_contains($sql,'SELECT p.* FROM wp_mi_payments'))return $this->payments;
  if(str_contains($sql,'mi_registration_events')||str_contains($sql,'mi_rooms'))return [];
  return parent::get_results($query,$format);
 }
 function get_var($query){return str_contains($query[0],'mi_management_state')?1:parent::get_var($query);}
}
late_reset();$old=$wpdb;$wpdb=new LateProjectionDB();foreach(get_object_vars($old) as $k=>$v)$wpdb->$k=$v;
$wpdb->reg+=['order_code'=>'CAMMINO-1','buyer_first_name'=>'Marco','buyer_last_name'=>'D’Ecclesia','buyer_phone'=>'','total_qty'=>1,'order_options_json'=>'[]','created_at'=>'2026-11-13 10:00:00'];
$wpdb->persons[0]['ticket_index']=1;
$wpdb->payments[0]+=['registration_id'=>1,'effective_at'=>'2026-12-15 10:00:00','payment_source'=>'BANK_TRANSFER','external_reference'=>'CAPARRA','operator_label'=>'Test','administrative_note'=>'Caparra registrata'];
$GLOBALS['late_routes']=late_routes();$data=late_data(['pullman-roma-fco'=>1,'pullman-porto-tui'=>1]);
$preview=MI_Public_Balance::save(42,$data,true);$data['fingerprint']=$preview['fingerprint'];MI_Public_Balance::save(42,$data);
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-event-projection.php';
$projection=MI_Event_Projection::snapshot(42)['projection'];
check_balance($projection['registrations'][0]['totale_centesimi']===57500&&$projection['registrations'][0]['saldo_centesimi']===42500,'Totale/saldo errati nella replica');
check_balance($projection['participants'][0]['versato_centesimi']===15000&&$projection['participants'][0]['saldo_centesimi']===42500,'Posizione personale errata');
$options=json_decode($projection['participants'][0]['opzioni_json'],true);
check_balance(count($options)===5&&array_slice(array_column($options,'code'),-2)===['pullman-roma-fco','pullman-porto-tui'],'Scelte nuove assenti nella replica');
check_balance(count(json_decode($projection['event']['servizi_json'],true))===4&&count($projection['payments'])===1,'Catalogo corrente o incasso caparra persi');
echo "PASS replica Google: quattro nuove tratte nel catalogo, due scelte personali, totale 575 €, versato 150 €, saldo 425 €, un solo incasso.\n";
