<?php
$phone_api=($argv[1]??'')==='--api';$phone_render=($argv[1]??'')==='--render';$phone_state=$argv[2]??'';$argv=['balance-phone.php','--library'];
require __DIR__.'/public-balance-late-bus.php';
function get_transient($key){$v=$GLOBALS['phone_transients'][$key]??null;return $v&&$v['expires']>time()?$v['value']:false;}
function set_transient($key,$value,$ttl){$GLOBALS['phone_transients'][$key]=['value'=>$value,'expires'=>time()+$ttl];}
function delete_transient($key){unset($GLOBALS['phone_transients'][$key]);}
class PhoneDB extends LateBusDB{
 function get_var($query){if(str_contains($query[0],'GET_LOCK')||str_contains($query[0],'RELEASE_LOCK'))return '1';return parent::get_var($query);}
}
function phone_reset(){global $wpdb;late_reset();$old=$wpdb;$wpdb=new PhoneDB();foreach(get_object_vars($old) as $k=>$v)$wpdb->$k=$v;$wpdb->reg['buyer_phone']='+39 338 711 1234';$GLOBALS['late_meta']=['_mi_balance_phone_check'=>'1'];$GLOBALS['phone_transients']=[];$GLOBALS['late_routes']=late_routes();}
if($phone_render){
 function nocache_headers(){}function wp_create_nonce($v){return 'test';}function home_url($v){return $v;}function add_query_arg($key,$value,$url){return $url.'?'.$key.'='.$value;}
 class MI_Assets{static function filter_url($v){return $v;}}
 define('MI_PLUGIN_URL','https://example.invalid/');define('MI_VERSION','3.27.62');phone_reset();$GLOBALS['late_meta']['_mi_balance_phone_check']=$phone_state==='off'?'0':'1';MI_Public_Balance::render(42);exit;
}
if($phone_api){
 phone_reset();$input=json_decode(stream_get_contents(STDIN),true);
 if(is_file($phone_state)){$stored=json_decode(file_get_contents($phone_state),true);foreach($stored['db'] as $k=>$v)$wpdb->$k=$v;$GLOBALS['late_meta']=$stored['meta'];$GLOBALS['phone_transients']=$stored['transients'];}
 try{
  if($input['action']==='reset'){phone_reset();$GLOBALS['late_meta']['_mi_balance_phone_check']=empty($input['disabled'])?'1':'0';$result=['success'=>true];}
  elseif(in_array($input['action'],['lookupByCognome','lookupPersona','verifyPhone'],true))$result=MI_Public_Balance::lookup(42,$input);
  elseif($input['action']==='inspect')$result=['total'=>$wpdb->reg['total_cents'],'writes'=>$wpdb->writes];
  else $result=MI_Public_Balance::save(42,$input,$input['action']==='preview');
 }catch(Throwable $error){$result=['success'=>false,'error'=>$error->getMessage()];}
 file_put_contents($phone_state,json_encode(['db'=>get_object_vars($wpdb),'meta'=>$GLOBALS['late_meta'],'transients'=>$GLOBALS['phone_transients']]));echo json_encode($result);exit;
}
late_reset();check_balance(late_lookup()['success']&&!class_exists('MI_Balance_Phone',false),'Classe verifica caricata anche quando disattivata');
phone_reset();$challenge=late_lookup();
check_balance($challenge['error']==='phone_required'&&!isset($challenge['persona'],$challenge['partner'])&&$challenge['phone_masked']==='+39338711 ••••','Dati esposti prima della verifica o maschera errata');
$data=['action'=>'verifyPhone','cognome'=>'decclesia','nome'=>'Marco','candidate'=>1,'challenge'=>$challenge['challenge'],'digits'=>'0000'];
for($i=1;$i<=5;$i++){
 $bad=MI_Public_Balance::lookup(42,$data);check_balance(!$bad['success']&&!isset($bad['persona']),'Cifre errate ammesse');
 check_balance(!preg_match('/tentativi|rimasti|disponibili|[0-9] tentativ/i',$bad['error']),'Contatore tentativi visibile');
 if($i===5)check_balance(str_contains($bad['error'],'5 minuti'),'Pausa non attivata');
}
$data['digits']='1234';check_balance(!MI_Public_Balance::lookup(42,$data)['success'],'Numero corretto ammesso durante pausa');
// Simula lo scadere della pausa nell'adattatore, senza attendere cinque minuti.
foreach($GLOBALS['phone_transients'] as &$entry)$entry['expires']=time()-1;unset($entry);
$view=MI_Public_Balance::lookup(42,$data);check_balance($view['success']&&$view['persona']['paid']===15000&&!$view['partner'],'Verifica valida non apre la prenotazione');
$legacy=hash_hmac('sha256','balance-person|42|1',wp_salt('auth'));
$payload=['persone'=>[['row'=>1,'token'=>$legacy,'version'=>$view['persona']['version'],'services'=>[]]],'email'=>'scelto@example.invalid','requestId'=>'62345678-1234-4234-8234-123456789abc'];
$blocked=false;try{MI_Public_Balance::save(42,$payload,true);}catch(InvalidArgumentException $e){$blocked=true;}check_balance($blocked,'API diretta aggira verifica');
$payload['persone'][0]['token']=$view['persona']['token'];$payload['persone'][0]['services']=['pullman-roma-fco'=>1];
$preview=MI_Public_Balance::save(42,$payload,true);check_balance($preview['balance']===41000,'Calcolo saldo dopo verifica errato');
$payload['fingerprint']=$preview['fingerprint'];MI_Public_Balance::save(42,$payload);
check_balance($wpdb->reg['total_cents']===56000,'Conferma dopo verifica non salvata');
$wpdb->reg['buyer_phone']='+393387115678';check_balance(!MI_Balance_Phone::allowed(42,1,'393387115678',$view['persona']['token']),'Cambio telefono non revoca accesso');
check_balance(!MI_Balance_Phone::allowed(43,1,'393387111234',$view['persona']['token']),'Accesso riutilizzabile fra eventi');
$wpdb->persons[0]['extra_json']='{"participant_phone":"+39 333 222 9876"}';check_balance(late_lookup()['phone_masked']==='+39333222 ••••','Telefono personale non prioritario');
$wpdb->persons[0]['extra_json']='{}';$wpdb->reg['buyer_phone']='';$missing=late_lookup();check_balance(!$missing['success']&&!isset($missing['persona']),'Telefono assente apre dati');
echo "PASS telefono saldo: caricamento condizionale, maschera, cinque errori/pausa, nessun contatore, verifica, API protetta, saldo e conferma, cambio numero/evento, numero personale e recapito mancante.\n";
