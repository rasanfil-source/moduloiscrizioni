<?php
// Salvataggio reale del wizard su metadati sintetici; nessun servizio o database esterno.
define('ABSPATH',__DIR__);
class WP_Error { function __construct(public $code,public $message){} function get_error_message(){return $this->message;} }
class TransportSaved extends RuntimeException {}
class TransportRejected extends RuntimeException {}
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_textarea_field($v){return sanitize_text_field($v);}
function wp_unslash($v){return $v;}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function current_time($format){return (new DateTimeImmutable('now',wp_timezone()))->format($format);}
function wp_date($format,$timestamp=null,$zone=null){return (new DateTimeImmutable('@'.($timestamp??time())))->setTimezone($zone??wp_timezone())->format($format);}
function current_user_can($cap){return true;}
function is_user_logged_in(){return true;}
function check_admin_referer(...$args){}
function wp_die($message,...$args){throw new RuntimeException($message);}
function get_post($id){return (object)['ID'=>$id,'post_type'=>'mi_event','post_status'=>'draft','post_title'=>'Prova'];}
function get_post_type($id){return 'mi_activity';}
function get_post_meta($id,$key,$single=true){return $GLOBALS['transport_meta'][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['transport_meta'][$key]=$value;if($key==='_mi_options')throw new TransportSaved();}
function delete_post_meta($id,$key){unset($GLOBALS['transport_meta'][$key]);}
function wp_update_post($data,...$args){$GLOBALS['transport_writes']++;return $data['ID'];}
function get_post_field(...$args){return 0;}
function get_users(...$args){return [];}
function wp_generate_uuid4(){static $counter=0;return sprintf('12345678-1234-4234-8234-%012d',++$counter);}
function get_posts(...$args){return [];}
function home_url($path=''){return 'https://example.invalid'.$path;}
function add_query_arg($args,$url=null,$base=null){return is_array($args)?$url.'?'.http_build_query($args):$base.'?'.$args.'='.$url;}
function wp_safe_redirect($url){parse_str(parse_url($url,PHP_URL_QUERY),$query);throw new TransportRejected($query['mi_portal_message']??$url);}
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';const GROUP_TYPE='mi_activity';}
class MI_Access {static function can_access_event(...$args){return true;}static function can_access_activity(...$args){return true;}}
class MI_Extra_Services {static function parse($raw){return [];}}
class MI_Shortcode {static function validate_public_slug(...$args){return '';}static function save_public_slug(...$args){return true;}}
class MI_Field_Schema {
 static function sanitize_operational_profile($v){return $v;}
 static function sanitize_configuration(...$args){return ['profile'=>'CUSTOM','enabled'=>[],'required'=>[]];}
 static function has_high_impact_fields(...$args){return false;}
 static function sanitize_custom_fields($v){return $v;}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-amount.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-group-links.php';
function transport_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function transport_save($mode,$enabled=true,$overrides=[]){
 $GLOBALS['transport_writes']=0;
 $GLOBALS['transport_meta']=['_mi_activity_id'=>12,'_mi_custom_participant_fields'=>[],'_mi_options'=>[
  ['code'=>'pullman-old','name'=>'Pullman precedente','category'=>'pullman','scope'=>'TICKET','price_cents'=>9900],
  ['code'=>'external','name'=>'Opzione avanzata','scope'=>'TICKET','price_cents'=>300]
 ]];
 $start=(new DateTimeImmutable('+4 days',wp_timezone()))->format('d/m/Y H:i');
 $close=(new DateTimeImmutable('+3 days',wp_timezone()))->format('d/m/Y H:i');
 $_SERVER['REQUEST_METHOD']='POST';
 $_POST=array_replace(['mi_portal_action'=>'create_event','event_id'=>42,'activity_id'=>12,'title'=>'Prova trasporti','starts_at'=>$start,'closes_at'=>$close,'pricing_mode'=>$mode,'fixed_price'=>'40','transport_settings_present'=>'1','bus_enabled'=>$enabled?'1':'','bus_route_code'=>['Roma'],'bus_route_id'=>['pullman-roma'],'bus_route_price'=>['15,00'],'bus_assignment_enabled'=>'1'], $overrides);
 if(isset($overrides['event_link_mode']))$GLOBALS['transport_meta']['_mi_group_links']=['corso'=>['enabled'=>true,'label'=>'Corso']];
 try {MI_Portal::handle_actions();throw new RuntimeException('Salvataggio non intercettato');}catch(TransportSaved $done){}
 return array_column($GLOBALS['transport_meta']['_mi_options'],null,'code');
}
foreach(['ZERO','FIXED','CALCULATED'] as $mode){
 $saved=transport_save($mode);
 transport_check(isset($saved['pullman-roma'],$saved['external'])&&!isset($saved['pullman-old']),'Tratte rimosse o opzioni avanzate gestite male: '.$mode);
 transport_check($saved['pullman-roma']['price_cents']===($mode==='CALCULATED'?1500:0),'Supplemento pullman errato: '.$mode);
 transport_check($GLOBALS['transport_meta']['_mi_pricing_mode']===$mode,'Modalità quote modificata dal pullman: '.$mode);
 $saved=transport_save($mode,false);
 transport_check(array_keys($saved)===['external']&&$GLOBALS['transport_meta']['_mi_bus_assignment_enabled']==='0','Pullman disattivato ancora presente: '.$mode);
}
$saved=transport_save('ZERO',true,['bus_route_code'=>[''],'bus_route_id'=>[''],'bus_route_price'=>[]]);
transport_check(count($saved)===2&&count(array_filter($saved,fn($o)=>($o['name']??'')==='Pullman'))===1,'Tratta gratuita senza descrizione non salvata');
foreach(['','-1','abc'] as $price){
 try{transport_save('CALCULATED',true,['bus_route_price'=>[$price]]);throw new RuntimeException('Quota non valida accettata');}
 catch(TransportRejected $e){transport_check($GLOBALS['transport_writes']===0&&str_contains($e->getMessage(),'quota valida'),'Errore quote dopo una scrittura');}
}
transport_save('ZERO',true,['event_link_mode'=>'INDIVIDUAL','group_link'=>'corso']);
transport_check($GLOBALS['transport_meta']['_mi_group_link']==='','Link individuale deve consentire di non associare categorie');
transport_save('ZERO',true,['event_link_mode'=>'PERMANENT','group_link'=>'corso']);
transport_check($GLOBALS['transport_meta']['_mi_group_link']==='corso','Link permanente esistente non associato');
try{transport_save('ZERO',true,['event_link_mode'=>'PERMANENT','group_link'=>'']);throw new RuntimeException('Categoria obbligatoria assente accettata');}
catch(TransportRejected $e){transport_check($GLOBALS['transport_writes']===0,'Categoria respinta dopo una scrittura');}
$midnight=(new DateTimeImmutable('+4 days',wp_timezone()))->setTime(0,15);
transport_save('ZERO',true,['closes_at'=>'','starts_at'=>$midnight->format('d/m/Y H:i')]);
transport_check($GLOBALS['transport_meta']['_mi_registration_closes_at']===$midnight->modify('-30 minutes')->format('Y-m-d\TH:i')&&$GLOBALS['transport_meta']['_mi_registration_closes_auto']==='1','Chiusura automatica errata nel cambio di giorno');
transport_save('ZERO',true,['closes_at'=>'','starts_at'=>$midnight->modify('+1 day')->format('d/m/Y H:i')]);
transport_check($GLOBALS['transport_meta']['_mi_registration_closes_at']===$midnight->modify('+1 day -30 minutes')->format('Y-m-d\TH:i'),'Chiusura non segue la nuova data');
transport_save('ZERO');transport_check($GLOBALS['transport_meta']['_mi_registration_closes_auto']==='0','Scadenza esplicita trattata come automatica');
$resume=new ReflectionMethod(MI_Portal::class,'draft_initial_step');
$GLOBALS['transport_meta']=['_mi_capacity'=>30];transport_check($resume->invoke(null,42)===4,'Bozza incompleta non ripresa da Date e posti');
$GLOBALS['transport_meta']+=['_mi_event_starts_at'=>'2027-11-02T12:00','_mi_registration_closes_at'=>'2027-11-01T12:00'];
transport_check($resume->invoke(null,42)===8,'Bozza completa non ripresa dal controllo finale');
echo "PASS transport save: free/fixed/calculated bus pricing, explicit removal, preserved advanced options, anonymous free route, validation before writes and draft resume.\n";
