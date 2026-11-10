<?php
define('ABSPATH',__DIR__);
function absint($v){return abs((int)$v);}function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function get_post($id){return $id===42?(object)['ID'=>42,'post_type'=>'mi_event','post_status'=>'publish','post_title'=>'Modello','post_content'=>'']:(in_array($id,[12,13,14],true)?(object)['ID'=>$id,'post_type'=>'mi_activity','post_title'=>'Gruppo '.$id]:null);}
function get_posts($args){return $args['post_type']==='mi_event'?[get_post(42)]:[get_post(12),get_post(13),get_post(14)];}
function get_post_meta($id,$key,$single){return $GLOBALS['meta'][$id][$key]??'';}
function metadata_exists($type,$id,$key){return array_key_exists($key,$GLOBALS['meta'][$id]??[]);}
function current_user_can($cap){return false;}function current_time($format,...$args){return $format==='timestamp'?time():date($format);}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}function wp_nonce_field(...$args){}
function wp_date($format,$timestamp=null,$zone=null){return (new DateTimeImmutable('@'.($timestamp??time())))->setTimezone($zone??wp_timezone())->format($format);}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function esc_html($v){return esc_attr($v);}function esc_url($v){return esc_attr($v);}function esc_textarea($v){return esc_attr($v);}
function selected($a,$b,$echo=true){$s=(string)$a===(string)$b?'selected':'';if($echo)echo $s;return $s;}function checked($a,$b=true,$echo=true){$s=$a==$b?'checked':'';if($echo)echo $s;return $s;}function disabled($a,$b=true,$echo=true){$s=$a==$b?'disabled':'';if($echo)echo $s;return $s;}
function home_url($path=''){return 'https://example.test'.$path;}function get_the_title($id){return 'Gruppo';}function get_post_thumbnail_id($id){return 0;}
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';const ACTIVITY_TYPE='mi_activity';}
class MI_Access {static function is_suspended(){return false;}static function can_access_event($id){return $id===42;}static function activity_ids(){return 'ALL';}}
class MI_Field_Schema {static function catalog(){return ['email'=>['label'=>'Email']];}}
class MI_Extra_Services {static function render($options){}}
class MI_Modello_Email {static function impostazioni($id){return ['subject'=>$id?'Oggetto copiato':'Oggetto nuovo','text'=>'Testo'];}static function stile_risolto($id){return self::stile_default();}static function stile_default(){return ['primary_color'=>'#123456','secondary_color'=>'#654321'];}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-travel.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-group-links.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
$GLOBALS['meta']=[42=>['_mi_reopened_payment_hours'=>72,'_mi_waitlist_offer_hours'=>2,'_mi_capacity'=>77,'_mi_ticket_types'=>[['max_per_order'=>8,'price_cents'=>4200]],'_mi_custom_participant_fields'=>[['key'=>'custom_allergie','label'=>'Allergie','type'=>'text']], '_mi_pricing_mode'=>'CALCULATED','_mi_registration_closes_at'=>'2027-01-01T12:00','_mi_participant_extra_scope'=>'ALL','_mi_accommodation_management'=>'PREBOOKED']];
$GLOBALS['meta'][12]['_mi_group_links']=['incontri'=>['label'=>'Incontri','enabled'=>true],'gite'=>['label'=>'Gite','enabled'=>true]];
$GLOBALS['meta'][13]['_mi_group_links']=['unico'=>['label'=>'Unica categoria','enabled'=>true]];
if (in_array('--transport-model',$argv,true)) {
 $GLOBALS['meta'][42]['_mi_activity_id']=12;
 $GLOBALS['meta'][42]['_mi_group_link']='gite';
 $GLOBALS['meta'][42]['_mi_options']=[['code'=>'pullman-roma','name'=>'Pullman - Roma','price_cents'=>1500]];
 $GLOBALS['meta'][42]['_mi_travel']=['enabled'=>true,'price_mode'=>'DRIVER','instructions'=>'Ritrovo in piazza','deadline'=>'2027-11-01T10:00'];
}
$_GET['mi_copy_from']=isset($argv[1])?(int)$argv[1]:42;
$render=new ReflectionMethod(MI_Portal::class,'create_view');ob_start();$render->invoke(null);$html=ob_get_clean();
if(in_array('--html',$argv,true)){echo $html;exit;}
function check_wizard($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
check_wizard(str_contains($html,'name="capacity" value="77"'),'Capacity not copied');
check_wizard(str_contains($html,'name="max_per_order" min="2" max="20" value="8"'),'Multiple limit not copied');
check_wizard(str_contains($html,'value="Allergie"')&&str_contains($html,'value="42,00"'),'Questions or price not copied');
check_wizard(str_contains($html,'>Iscrizione senza quote nel modulo</option>')&&str_contains($html,'>Quota uguale per tutti</option>')&&str_contains($html,'>Quote in base ai servizi scelti</option>'),'Pricing modes not rendered with the requested labels');
check_wizard(str_contains($html,'name="participation_price"')&&str_contains($html,'name="fixed_price"'),'Per-person pricing fields are missing');
check_wizard(str_contains($html,'name="travel_enabled"')&&str_contains($html,'name="travel_price_mode"')&&str_contains($html,'name="travel_deadline"'),'Travel configuration is missing from event wizard');
preg_match_all('/<span class="mi-portal-eyebrow">(\d+) di 9<\/span>/', $html, $numbers);
check_wizard($numbers[1]===['1','2','3','4','5','6','7','8','9'],'Wizard numbering is inconsistent');
check_wizard(strpos($html,'data-mi-transport-step')<strpos($html,'name="capacity"')&&strpos($html,'name="bus_route_code[]"')<strpos($html,'name="capacity"'),'Transport configuration must precede event capacity');
check_wizard(strpos($html,'name="group_link"')>strpos($html,'9 di 9')&&strpos($html,'name="group_link"')<strpos($html,'name="public_slug"'),'Group link must be beside the individual event link');
check_wizard(substr_count($html,'name="travel_enabled"')===1&&substr_count($html,'data-mi-bus-routes>')===1,'Transport controls duplicated');
check_wizard(str_contains($html,'value="Oggetto copiato"'),'Email not copied');
check_wizard(str_contains($html,'name="closes_at" value=""'),'Old dates copied');
check_wizard(str_contains($html,'name="event_id" value="0"'),'Source event edited');
check_wizard(str_contains($html,'data-mi-accommodation-management-mode="PREBOOKED"'),'Accommodation mode not copied');
check_wizard(str_contains($html,'name="reopened_payment_hours" min="1" max="168" value="72"'),'Dedicated payment window not copied from model');
$GLOBALS['meta'][42]['_mi_pricing_mode']='FIXED';$GLOBALS['meta'][42]['_mi_fixed_price_cents']=4200;ob_start();$render->invoke(null);$fixed_html=ob_get_clean();
check_wizard(str_contains($fixed_html,'name="fixed_price" data-mi-fixed-price-input value="42,00"'),'Fixed per-person quota not loaded');
check_wizard(str_contains($fixed_html,'value="FIXED" selected'),'Fixed pricing mode not selected');
$_GET['mi_copy_from']=99;ob_start();$render->invoke(null);$denied=ob_get_clean();
check_wizard(!str_contains($denied,'value="Allergie"')&&str_contains($denied,'data-mi-participant-scope hidden disabled'),'Foreign model read or single scope visible');
check_wizard(str_contains($denied,'name="reopened_payment_hours" min="1" max="168" value="48"'),'Default payment window is not 48 hours');
echo "Event wizard: model prefill, new identity, blank dates, questions, prices, email, multiple limit and access isolation passed.\n";
