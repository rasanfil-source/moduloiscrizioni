<?php
// Scenario cammino: usa il calcolatore e il salvataggio applicativi, con archivio locale sintetico.
$fixture = explode('foreach([[0,15000', file_get_contents(__DIR__.'/public-balance-model.php'), 2)[0];
$fixture = str_replace("if(\$key==='_mi_options')return [['code'=>'pullman-a','name'=>'Roma → Fiumicino','category'=>'pullman','scope'=>'TICKET','price_cents'=>1000,'max_quantity'=>1]];", "if(\$key==='_mi_options')return \$GLOBALS['late_routes']??[];", $fixture);
$fixture = str_replace('static function mark_workspace_changed_locked($id){}static function accoda_iscrizione_workspace($id){}', 'static function mark_workspace_changed_locked($id){global $wpdb;$wpdb->reg["workspace_revision"]=($wpdb->reg["workspace_revision"]??0)+1;$wpdb->reg["workspace_status"]="PENDING";}static function accoda_iscrizione_workspace($id){$GLOBALS["late_sync"][]=$id;}', $fixture);
$fixture = str_replace('function get_post_meta($id,$key,$single=true){', 'function get_post_meta($id,$key,$single=true){if(array_key_exists($key,$GLOBALS["late_meta"]??[]))return $GLOBALS["late_meta"][$key];', $fixture);
eval('?>'.str_replace('__DIR__', var_export(__DIR__, true), $fixture));

class LateBusDB extends BalanceDB {
 public $outbox=[];
 function get_results($query,$format){
  if(str_contains($query[0],'mi_registration_items')) return [['ticket_type_code'=>'base','unit_price_cents'=>30000]];
  return parent::get_results($query,$format);
 }
 function query($query){
  if(is_array($query)&&str_contains($query[0],'INSERT IGNORE INTO wp_mi_email_outbox'))$this->outbox[]=$query[1];
  return parent::query($query);
 }
}
function late_routes(){
 $rows=[['roma-fco','Roma → Fiumicino',1000],['porto-tui','Porto → Tui',1500],['santiago-coruna','Santiago → A Coruña',1500],['fco-roma','Fiumicino → Roma',1000]];
 return array_map(fn($r)=>['code'=>'pullman-'.$r[0],'name'=>'Pullman - '.$r[1],'scope'=>'TICKET','category'=>'pullman','price_cents'=>$r[2],'max_quantity'=>1],$rows);
}
function late_reset(){
 global $wpdb;$wpdb=new LateBusDB();$GLOBALS['late_routes']=[];$GLOBALS['late_sync']=[];
 $options=[['code'=>'alloggio-singola','name'=>'Alloggio: Singola','quantity'=>1,'unit_price_cents'=>20000],['code'=>'colazione','name'=>'Colazione','quantity'=>1,'unit_price_cents'=>2000],['code'=>'assicurazione','name'=>'Assicurazione','quantity'=>1,'unit_price_cents'=>3000]];
 $definitions=array_map(fn($o)=>['code'=>$o['code'],'name'=>$o['name'],'scope'=>'TICKET','price_cents'=>$o['unit_price_cents'],'max_quantity'=>1,'category'=>$o['code']==='alloggio-singola'?'alloggio':'supplemento'],$options);
 $wpdb->persons[0]['options_json']=json_encode($options);
 $snapshot=json_decode($wpdb->reg['snapshot_json'],true);$snapshot['event']['options']=$definitions;
 $wpdb->reg=array_merge($wpdb->reg,['status'=>'CONFIRMED','total_cents'=>55000,'initial_due_cents'=>15000,'balance_cents'=>40000,'snapshot_json'=>json_encode($snapshot),'payment_deadline_at'=>'2027-05-20 21:59:00','workspace_revision'=>1]);
 $wpdb->payments=[['id'=>1,'transaction_kind'=>'PAYMENT','amount_cents'=>15000,'participant_allocations_json'=>'[{"participant_id":1,"amount_cents":15000}]']];
}
function late_lookup(){return MI_Public_Balance::lookup(42,['action'=>'lookupByCognome','cognome'=>'decclesia']);}
function late_data($services,$request='12345678-1234-4234-8234-123456789abc'){
 $p=late_lookup()['persona'];return ['persone'=>[['row'=>1,'token'=>$p['token'],'version'=>$p['version'],'services'=>$services]],'email'=>'scelto@example.invalid','requestId'=>$request];
}
function late_run(){
 global $wpdb;late_reset();
 check_balance(late_lookup()['persona']['services']===[],'Il pullman non deve esistere all’apertura');
 $historical=$wpdb->reg['snapshot_json'];$payments=$wpdb->payments;$locked=$wpdb->persons[0]['options_json'];
 $GLOBALS['late_routes']=late_routes();
 $person=late_lookup()['persona'];
 check_balance(count($person['services'])===4&&$person['paid']===15000,'Le quattro nuove tratte e la caparra non sono disponibili');
 check_balance($person['fixed']===55000&&count($person['locked'])===3,'Alloggio e servizi originari alterati');
 $data=late_data(['pullman-roma-fco'=>1,'pullman-porto-tui'=>1]);
 $before=[$wpdb->reg,$wpdb->persons,$wpdb->writes];$preview=MI_Public_Balance::save(42,$data,true);
 check_balance($preview['total']===57500&&$preview['paid']===15000&&$preview['balance']===42500&&$preview['depositDue']===0,'Prima scelta: attesi 575 €, versato 150 €, saldo 425 €');
 check_balance($before===[$wpdb->reg,$wpdb->persons,$wpdb->writes],'Anteprima ha scritto dati');
 $data['fingerprint']=$preview['fingerprint'];$saved=MI_Public_Balance::save(42,$data);
 check_balance($saved['balance']===42500&&$wpdb->reg['total_cents']===57500,'Saldo non salvato');
 check_balance($wpdb->payments===$payments&&$wpdb->reg['initial_due_cents']===15000,'Caparra o movimenti cambiati');
 check_balance($wpdb->reg['workspace_status']==='PENDING'&&$GLOBALS['late_sync']===[1],'Modifica non accodata alla replica Google');
 check_balance($wpdb->reg['snapshot_json']===$historical,'Condizioni iniziali riscritte');
 $options=json_decode($wpdb->persons[0]['options_json'],true);
 check_balance(array_slice($options,0,3)===json_decode($locked,true)&&count($options)===5,'Servizi originali o tratte perse');
 check_balance($wpdb->outbox[0][1]==='scelto@example.invalid','Riepilogo non indirizzato al recapito scelto');
 $mail=json_decode($wpdb->outbox[0][3],true)['email_preview'];
 check_balance(str_contains($mail['testo'],'425,00')&&str_contains($mail['testo'],'Porto')&&str_contains($mail['html'],'425,00'),'Email non allineata alle scelte');
 $writes=$wpdb->writes;$again=MI_Public_Balance::save(42,$data);
 check_balance($again===$saved&&$wpdb->writes===$writes&&count($wpdb->outbox)===1,'Retry duplica costi o riepilogo');
 $data=late_data(['pullman-porto-tui'=>1,'pullman-santiago-coruna'=>1],'22345678-1234-4234-8234-123456789abc');
 $preview=MI_Public_Balance::save(42,$data,true);$data['fingerprint']=$preview['fingerprint'];MI_Public_Balance::save(42,$data);
 check_balance($wpdb->reg['total_cents']===58000&&$preview['balance']===43000,'Cambio scelta somma invece di sostituire');
 $data=late_data([],'32345678-1234-4234-8234-123456789abc');$preview=MI_Public_Balance::save(42,$data,true);$data['fingerprint']=$preview['fingerprint'];MI_Public_Balance::save(42,$data);
 check_balance($wpdb->reg['total_cents']===55000&&$preview['balance']===40000,'Rinuncia ai trasferimenti non ripristina il saldo');
 // Una tariffa cambiata dopo il preventivo non può essere accettata silenziosamente.
 $data=late_data(['pullman-roma-fco'=>1],'42345678-1234-4234-8234-123456789abc');$preview=MI_Public_Balance::save(42,$data,true);$data['fingerprint']=$preview['fingerprint'];
 $GLOBALS['late_routes'][0]['price_cents']=1200;$rejected=false;
 try{MI_Public_Balance::save(42,$data);}catch(InvalidArgumentException $e){$rejected=true;}
 check_balance($rejected&&$wpdb->reg['total_cents']===55000,'Variazione tariffa dopo anteprima non bloccata');
 // Due persone nella stessa prenotazione scelgono tratte diverse; quote e caparre restano individuali.
 late_reset();$GLOBALS['late_routes']=late_routes();
 $wpdb->persons[]=array_merge($wpdb->persons[0],['id'=>2,'first_name'=>'Maria']);
 $wpdb->reg['total_cents']=110000;$wpdb->reg['initial_due_cents']=30000;$wpdb->reg['balance_cents']=80000;
 $wpdb->payments[0]['amount_cents']=30000;$wpdb->payments[0]['participant_allocations_json']='[{"participant_id":1,"amount_cents":15000},{"participant_id":2,"amount_cents":15000}]';
 $first=MI_Public_Balance::lookup(42,['action'=>'lookupPersona','cognome'=>'decclesia','nome'=>'Marco','candidate'=>1]);
 $second=MI_Public_Balance::lookup(42,['action'=>'lookupPersona','cognome'=>'decclesia','nome'=>'Maria','candidate'=>2]);
 $data=['persone'=>[],'email'=>'famiglia@example.invalid','requestId'=>'52345678-1234-4234-8234-123456789abc'];
 foreach([[$first,['pullman-roma-fco'=>1,'pullman-porto-tui'=>1]],[$second,['pullman-santiago-coruna'=>1,'pullman-fco-roma'=>1]]] as [$view,$services])$data['persone'][]=['row'=>$view['row'],'token'=>$view['persona']['token'],'version'=>$view['persona']['version'],'services'=>$services];
 $preview=MI_Public_Balance::save(42,$data,true);$data['fingerprint']=$preview['fingerprint'];MI_Public_Balance::save(42,$data);
 check_balance($preview['total']===115000&&$preview['paid']===30000&&$preview['balance']===85000,'Quote familiari non corrette');
 check_balance(count(json_decode($wpdb->persons[0]['options_json'],true))===5&&count(json_decode($wpdb->persons[1]['options_json'],true))===5,'Scelte personali perse nella prenotazione multipla');
 echo "PASS cammino: iscrizione senza pullman, caparra 150 €, quattro tratte successive, saldo 425 €, conferma, email archiviata, replica accodata, retry, cambio e rinuncia, conflitto tariffa.\n";
 echo "PASS due persone: tratte diverse, totale 1.150 €, caparre versate 300 €, saldo 850 €, selezioni distinte.\n";
}

// Ponte locale per il collaudo browser: stessa logica PHP, stato sintetico persistente fra richieste.
if(($argv[1]??'')==='--api'){
 $state=$argv[2];$input=json_decode(stream_get_contents(STDIN),true);late_reset();
 if(is_file($state)){$saved=json_decode(file_get_contents($state),true);foreach($saved['db'] as $k=>$v)$wpdb->$k=$v;$GLOBALS['late_routes']=$saved['routes'];}
 try{
  $action=$input['action'];
  if($action==='reset'){late_reset();$result=['success'=>true];}
  elseif($action==='add_routes'){$GLOBALS['late_routes']=$input['catalog']??late_routes();$result=['success'=>true];}
  elseif($action==='inspect'){$result=['success'=>true,'registration'=>$wpdb->reg,'people'=>$wpdb->persons,'payments'=>$wpdb->payments,'outbox'=>$wpdb->outbox];}
  elseif(in_array($action,['lookupByCognome','lookupPersona'],true))$result=MI_Public_Balance::lookup(42,$input);
  else $result=MI_Public_Balance::save(42,$input,$action==='preview');
 }catch(Throwable $e){$result=['success'=>false,'error'=>$e->getMessage()];}
 file_put_contents($state,json_encode(['db'=>get_object_vars($wpdb),'routes'=>$GLOBALS['late_routes']]));echo json_encode($result);exit;
}
if(!in_array('--library',$argv,true))late_run();
