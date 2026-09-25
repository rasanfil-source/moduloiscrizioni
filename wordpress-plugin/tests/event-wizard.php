<?php
define('ABSPATH',__DIR__);
function absint($v){return abs((int)$v);}function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function get_post($id){return $id===42?(object)['ID'=>42,'post_type'=>'mi_event','post_status'=>'publish','post_title'=>'Modello','post_content'=>'']:null;}
function get_posts($args){return $args['post_type']==='mi_event'?[get_post(42)]:[];}
function get_post_meta($id,$key,$single){return $GLOBALS['meta'][$id][$key]??'';}
function metadata_exists($type,$id,$key){return array_key_exists($key,$GLOBALS['meta'][$id]??[]);}
function current_user_can($cap){return false;}function current_time($format,...$args){return $format==='timestamp'?time():date($format);}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}function wp_nonce_field(...$args){}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function esc_html($v){return esc_attr($v);}function esc_url($v){return esc_attr($v);}function esc_textarea($v){return esc_attr($v);}
function selected($a,$b,$echo=true){$s=(string)$a===(string)$b?'selected':'';if($echo)echo $s;return $s;}function checked($a,$b=true,$echo=true){$s=$a==$b?'checked':'';if($echo)echo $s;return $s;}function disabled($a,$b=true,$echo=true){$s=$a==$b?'disabled':'';if($echo)echo $s;return $s;}
function home_url($path=''){return 'https://example.test'.$path;}function get_the_title($id){return 'Gruppo';}function get_post_thumbnail_id($id){return 0;}
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';const ACTIVITY_TYPE='mi_activity';}
class MI_Access {static function can_access_event($id){return $id===42;}static function activity_ids(){return 'ALL';}}
class MI_Field_Schema {static function catalog(){return ['email'=>['label'=>'Email']];}}
class MI_Extra_Services {static function render($options){}}
class MI_Modello_Email {static function impostazioni($id){return ['subject'=>$id?'Oggetto copiato':'Oggetto nuovo','text'=>'Testo'];}static function stile_risolto($id){return self::stile_default();}static function stile_default(){return ['primary_color'=>'#123456','secondary_color'=>'#654321'];}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
$GLOBALS['meta']=[42=>['_mi_reopened_payment_hours'=>72,'_mi_waitlist_offer_hours'=>2,'_mi_capacity'=>77,'_mi_ticket_types'=>[['max_per_order'=>8]],'_mi_custom_participant_fields'=>[['key'=>'custom_allergie','label'=>'Allergie','type'=>'text']], '_mi_pricing_mode'=>'FIXED','_mi_fixed_price_cents'=>4200,'_mi_registration_closes_at'=>'2027-01-01T12:00','_mi_participant_extra_scope'=>'ALL','_mi_accommodation_management'=>'PREBOOKED']];
$_GET['mi_copy_from']=isset($argv[1])?(int)$argv[1]:42;
$render=new ReflectionMethod(MI_Portal::class,'create_view');ob_start();$render->invoke(null);$html=ob_get_clean();
if(in_array('--html',$argv,true)){echo $html;exit;}
function check_wizard($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
check_wizard(str_contains($html,'name="capacity" value="77"'),'Capacity not copied');
check_wizard(str_contains($html,'name="max_per_order" min="2" max="20" value="8"'),'Multiple limit not copied');
check_wizard(str_contains($html,'value="Allergie"')&&str_contains($html,'value="42,00"'),'Questions or price not copied');
check_wizard(str_contains($html,'value="Oggetto copiato"'),'Email not copied');
check_wizard(str_contains($html,'name="closes_at" value=""'),'Old dates copied');
check_wizard(str_contains($html,'name="event_id" value="0"'),'Source event edited');
check_wizard(str_contains($html,'data-mi-accommodation-management-mode="PREBOOKED"'),'Accommodation mode not copied');
check_wizard(str_contains($html,'name="reopened_payment_hours" min="1" max="168" value="72"'),'Dedicated payment window not copied from model');
$_GET['mi_copy_from']=99;ob_start();$render->invoke(null);$denied=ob_get_clean();
check_wizard(!str_contains($denied,'value="Allergie"')&&str_contains($denied,'data-mi-participant-scope hidden disabled'),'Foreign model read or single scope visible');
check_wizard(str_contains($denied,'name="reopened_payment_hours" min="1" max="168" value="48"'),'Default payment window is not 48 hours');
echo "Event wizard: model prefill, new identity, blank dates, questions, prices, email, multiple limit and access isolation passed.\n";
