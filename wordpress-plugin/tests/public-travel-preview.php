<?php
// Rendering del modulo pubblico reale su un evento dimostrativo, senza WordPress o servizi esterni.
define('ABSPATH',__DIR__);define('MI_VERSION','preview');define('MI_PLUGIN_URL','https://example.invalid/plugin/');
function nocache_headers(){}function shortcode_atts($defaults,$args,...$rest){return array_merge($defaults,$args);}
function sanitize_hex_color($v){return preg_match('/^#[0-9a-f]{6}$/i',$v)?$v:'';}
function absint($v){return abs((int)$v);}function current_user_can(...$args){return true;}function is_wp_error($v){return false;}
function wp_enqueue_style(...$args){}function wp_enqueue_script(...$args){}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function esc_html($v){return esc_attr($v);}function esc_url($v){return esc_attr($v);}function esc_url_raw($v){return $v;}
function rest_url($v){return 'https://demo.invalid/'.$v;}function home_url($v){return 'https://demo.invalid'.$v;}function wp_json_encode($v){return json_encode($v);}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}function wp_date($format,$timestamp,$zone){return (new DateTimeImmutable('@'.$timestamp))->setTimezone($zone)->format('d/m/Y H:i');}
class MI_Access{static function can_access_event($id){return true;}}
class MI_REST_Controller{const NAMESPACE='modulo-iscrizioni/v1';}
class MI_Registration_Service{
 static function public_event(...$args){return [
 'id'=>42,'title'=>'Gita ad Assisi','activity'=>'Gruppo parrocchiale','description'=>'Una giornata insieme ad Assisi. Puoi organizzarti autonomamente, offrire posti in auto oppure cercare un passaggio.',
 'accent_color'=>'#17224a','cover_image'=>'','cover_image_alt'=>'','activity_logo'=>'','activity_logo_alt'=>'',
 'event_starts_at'=>'2027-11-02T10:00','event_location'=>'Assisi','opens_at'=>'','closes_at'=>'2027-11-02T09:30',
 'availability'=>['remaining'=>30,'capacity'=>30,'full'=>false,'ticket_types'=>[]],'waitlist_enabled'=>true,
 'pricing_mode'=>'FIXED','fixed_price_cents'=>2000,'economic_mode'=>'FULL_PAYMENT','payment_methods'=>['CASH'],
 'ticket_types'=>[['code'=>'standard','name'=>'Partecipazione','max_per_order'=>4,'price_cents'=>2000]],'options'=>[],
 'participant_fields'=>[],'participant_required_fields'=>[],'participant_extra_scope'=>'ALL','custom_participant_fields'=>[],
 'privacy_url'=>'','privacy_policy_version'=>'dimostrativa','marketing_enabled'=>false,
 'travel'=>['enabled'=>true,'price_mode'=>'DRIVER','price_cents'=>0,'instructions'=>'Partenza da Roma.','deadline'=>'2027-11-01T18:00']
 ];}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-shortcode.php';
echo MI_Shortcode::render(['event'=>42,'anteprima'=>1]);
