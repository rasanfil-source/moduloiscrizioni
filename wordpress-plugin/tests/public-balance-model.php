<?php
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
define('MI_PLUGIN_DIR',__DIR__.'/../modulo-iscrizioni/');function get_option($key,$default=false){return $default;}function update_option($k,$v,$a=false){}
function sanitize_text_field($s){return strip_tags($s);} function remove_accents($s){return strtr($s,['è'=>'e','é'=>'e','È'=>'E']);} function absint($n){return abs((int)$n);} function wp_salt($s){return 'test-'.$s;} function wp_json_encode($v){return json_encode($v);} function sanitize_email($s){return $s;} function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL);} function get_post_status($id){return $id===42?'publish':'draft';} function get_post_type($id){return 'mi_event';} function esc_url_raw($s,$p=[]){return $s;} function get_the_title($id){return 'Evento prova';} function esc_html($s){return htmlspecialchars($s);} function current_time($a,$b=false){return '2026-09-11 12:00:00';}
function get_post_meta($id,$key,$single=true){if($key==='_mi_economic_mode')return 'DEPOSIT_BALANCE';if($key==='_mi_payment_methods')return ['BANK_TRANSFER','CARD'];if($key==='_mi_options')return [['code'=>'pullman-a','name'=>'Roma → Fiumicino','category'=>'pullman','scope'=>'TICKET','price_cents'=>1000,'max_quantity'=>1]];return '';}
class MI_Event_Post_Type{const EVENT_TYPE='mi_event';}
class MI_Management_Service{static function lock_room_event($id){}}
class MI_Modello_Email{static function crea_istantanea($id,$values){return [];}}
class MI_Spedizione_Email{static function destinatario_evento($id){return 'segreteria@example.invalid';}static function destinatario_segreteria(){return 'segreteria@example.invalid';}static function stato_nuova_email($s){return 'PREVIEW';}static function email_da_spedire($status){return in_array($status,['PENDING','TEST_PENDING'],true);}static function pianifica_spedizione(){}}
class MI_Registration_Service{static function reopened_payment_deadline($registration,$now=null){$now=$now??time();$deadline=trim((string)($registration['payment_deadline_at']??''));$timestamp=$deadline===''?false:strtotime($deadline.' UTC');return false!==$timestamp&&$timestamp>$now?$deadline:gmdate('Y-m-d H:i:s',$now+48*3600);}static function mark_workspace_changed_locked($id){}static function accoda_iscrizione_workspace($id){}static function append_registration_event($id,$type,$from,$to,$key,$data){global $wpdb;$wpdb->events[$key]=$data;return true;}}
class BalanceDB{
 public $prefix='wp_',$last_error='',$events=[],$writes=0,$reg,$persons,$payments=[],$backup;
	function __construct(){$this->reg=['id'=>1,'event_id'=>42,'economic_mode'=>'DEPOSIT_BALANCE','status'=>'PENDING_PAYMENT','total_cents'=>50000,'initial_due_cents'=>15000,'balance_cents'=>35000,'buyer_email'=>'demo@example.invalid','snapshot_json'=>json_encode(['event'=>['participant_extra_scope'=>'ALL','options'=>[],'deposit_mode'=>'FIXED','deposit_fixed_cents'=>15000]]),'payment_deadline_at'=>'2026-10-01'];$this->persons=[['id'=>1,'registration_id'=>1,'ticket_type_code'=>'base','first_name'=>'Marco','last_name'=>"D’Ecclesia",'status'=>'ACTIVE','room_code'=>'S1','options_json'=>'[]','extra_json'=>'{}','deposit_due_cents'=>15000]];}
 function prepare($sql,...$args){if(count($args)===1&&is_array($args[0]))$args=$args[0];return [$sql,$args];}
 function get_row($query,$format){[$sql,$args]=$query;return $args[0]===42?$this->reg:null;}
	function get_results($query,$format){[$sql,$args]=$query;if(strpos($sql,'mi_payments')!==false)return $this->payments;if(strpos($sql,'mi_registration_items')!==false)return [['ticket_type_code'=>'base','unit_price_cents'=>50000]];return array_map(static function($p){$p['buyer_phone']='+390001234567';return $p;},$this->persons);}
 function get_var($query){[$sql,$args]=$query;return isset($this->events[$args[0]])?json_encode($this->events[$args[0]]):null;}
 function query($query){$sql=is_array($query)?$query[0]:$query;if($sql==='START TRANSACTION')$this->backup=[$this->reg,$this->persons,$this->events,$this->writes];if($sql==='ROLLBACK')[$this->reg,$this->persons,$this->events,$this->writes]=$this->backup;return 1;}
 function update($table,$data,$where){$this->writes++;if(strpos($table,'participants')!==false){foreach($this->persons as &$p)if($p['id']===$where['id'])$p=array_merge($p,$data);}else $this->reg=array_merge($this->reg,$data);return 1;}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-public-balance.php';
function check_balance($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([[0,15000,35000,50000],[5000,10000,35000,45000],[15000,0,35000,35000],[20000,0,30000,30000],[50000,0,0,0],[60000,0,0,0]] as [$paid,$cap,$saldo,$due]){$p=MI_Public_Balance::payment_position(50000,15000,$paid);check_balance($p['depositDue']===$cap&&$p['saldoDue']===$saldo&&$p['balance']===$due,'Posizione pagamento '.$paid);}
$wpdb=new BalanceDB();
check_balance(MI_Public_Balance::normalize("  D’Ècclesia ")===MI_Public_Balance::normalize('decclesia'),'Normalizzazione');
$lookup=MI_Public_Balance::lookup(42,['action'=>'lookupByCognome','cognome'=>'decclesia']);check_balance($lookup['success']&&$lookup['row']===1,'Cognome normalizzato');
check_balance(!isset($lookup['persona']['email']) && $lookup['persona']['email_masked']==='d*****@e***.invalid','Email mascherata sul server senza indirizzo completo');
$wpdb->persons[] = array_merge($wpdb->persons[0],['id'=>2,'first_name'=>'Maria','room_code'=>'S2']);
$wpdb->reg['total_cents']=100000;$wpdb->reg['initial_due_cents']=30000;$wpdb->reg['balance_cents']=70000;
$duplicates=MI_Public_Balance::lookup(42,['action'=>'lookupByCognome','cognome'=>'decclesia']);check_balance($duplicates['error']==='duplicate'&&count($duplicates['candidates'])===2,'Disambiguazione');
$chosen=MI_Public_Balance::lookup(42,['action'=>'lookupPersona','cognome'=>'decclesia','nome'=>'Maria','candidate'=>2]);check_balance($chosen['row']===2,'Scelta omonimo');
array_pop($wpdb->persons);
$wpdb->reg['total_cents']=50000;$wpdb->reg['initial_due_cents']=15000;$wpdb->reg['balance_cents']=35000;
$lookup=MI_Public_Balance::lookup(42,['action'=>'lookupByCognome','cognome'=>'decclesia']);
$data=['persone'=>[['row'=>1,'token'=>$lookup['persona']['token'],'version'=>$lookup['persona']['version'],'services'=>['pullman-a'=>1]]],'email'=>'','requestId'=>'12345678-1234-4234-8234-123456789abc'];
$quote=MI_Public_Balance::save(42,$data,true);check_balance($quote['total']===51000&&$quote['depositDue']===15000&&$quote['saldoDue']===36000,'Caparra non presunta e transfer');check_balance($wpdb->writes===0,'Anteprima non modifica dati');
check_balance(!isset($quote['email']) && $quote['email_masked']==='d*****@e***.invalid','Anteprima senza email digitata e senza rivelare indirizzo');
$data['fingerprint']=$quote['fingerprint'];$saved=MI_Public_Balance::save(42,$data);check_balance($saved['balance']===51000&&!$saved['emailQueued']&&$wpdb->reg['total_cents']===51000,'Conferma e modalità email');
$writes=$wpdb->writes;$again=MI_Public_Balance::save(42,$data);check_balance($again===$saved&&$writes===$wpdb->writes,'Retry idempotente');
$data['requestId']='22345678-1234-4234-8234-123456789abc';
try{MI_Public_Balance::save(42,$data,true);throw new RuntimeException('Versione obsoleta accettata');}catch(InvalidArgumentException $e){check_balance(strpos($e->getMessage(),'aggiornati')!==false,'Conflitto versione');}
$wpdb->payments=[['id'=>1,'amount_cents'=>15000,'transaction_kind'=>'PAYMENT'],['id'=>2,'amount_cents'=>4000,'transaction_kind'=>'REFUND']];
$lookup=MI_Public_Balance::lookup(42,['action'=>'lookupByCognome','cognome'=>'decclesia']);check_balance($lookup['persona']['paid']===11000,'Rimborso sottratto al versato');
$data['persone'][0]['version']=$lookup['persona']['version'];$quote=MI_Public_Balance::save(42,$data,true);check_balance($quote['depositDue']===4000&&$quote['balance']===40000,'Caparra nuovamente da completare dopo rimborso');
$data['persone'][0]['services']=['alloggio-singola'=>1];
try{MI_Public_Balance::save(42,$data,true);throw new RuntimeException('Opzione bloccata accettata');}catch(InvalidArgumentException $e){}
$data['persone'][0]['token']='bad';try{MI_Public_Balance::save(42,$data,true);throw new RuntimeException('Token estraneo accettato');}catch(InvalidArgumentException $e){}
echo "Saldo pubblico: ricerca, omonimi, caparra assente/parziale/versata, rimborsi, preventivo, salvataggio, replay e conflitti verificati.\n";
