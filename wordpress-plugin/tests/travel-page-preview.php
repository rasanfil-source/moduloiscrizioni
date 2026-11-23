<?php
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
function absint($v){return abs((int)$v);}function sanitize_text_field($v){return $v;}function wp_unslash($v){return $v;}
function is_wp_error($v){return false;}function get_transient($v){return true;}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function esc_attr($v){return esc_html($v);}function esc_url($v){return esc_html($v);}
function get_the_title($id){return 'Gita al Greccio';}function wp_timezone(){return new DateTimeZone('Europe/Rome');}function wp_date($format,$time,$zone){return (new DateTimeImmutable('@'.$time))->setTimezone($zone)->format($format);}
function get_post_meta($id,$key,$single){return match($key){
    '_mi_travel'=>['enabled'=>true,'instructions'=>'Ritrovo nel piazzale della parrocchia.','price_mode'=>'DRIVER','deadline'=>'2030-12-20T12:00'],
    '_mi_pricing_mode'=>getenv('MI_TRAVEL_PREVIEW_PRICING')?:'ZERO',
    '_mi_economic_mode'=>getenv('MI_TRAVEL_PREVIEW_ECONOMIC')?:'REGISTRATION_ONLY',
    default=>'',
};}
function wp_nonce_field($key){echo '<input type="hidden" name="_wpnonce" value="synthetic">';}
function wp_die($message,...$args){throw new RuntimeException($message);}
function nocache_headers(){}
class MI_Portal_Management{static function allowed(){return true;}}
class MI_Access{static function can_access_event($id){return true;}}
class MI_Portal{static function balance_url(...$args){return 'https://example.invalid/saldo';}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-travel.php';
$base=['participant_id'=>1,'registration_id'=>1,'event_id'=>42,'driver_id'=>null,'companion_of'=>null,'mode'=>'DRIVER','state'=>'NONE','seats'=>3,'origin'=>'Roma, piazzale Sant’Eugenio','departure_at'=>'2030-12-21 07:00:00','price_cents'=>1200,'person_status'=>'ACTIVE','booking_status'=>'CONFIRMED','capacity_released_at'=>null,'first_name'=>'Raimondo','last_name'=>'Esempio','buyer_email'=>'referente@example.invalid','buyer_phone'=>'3331234567','offer_expires_at'=>null,'decision_deadline_at'=>null,'required_ride'=>0,'order_code'=>'TEST'];
$base['booking_total_cents'] = (int) getenv('MI_TRAVEL_PREVIEW_TOTAL_CENTS');
$base['booking_quantity'] = 2;
$fixtures=[$base,array_replace($base,['participant_id'=>2,'mode'=>'NEED','state'=>'ASSIGNED','driver_id'=>1,'first_name'=>'Anna']),array_replace($base,['participant_id'=>3,'mode'=>'NEED','state'=>'OFFERED','driver_id'=>1,'first_name'=>'Bruno','offer_expires_at'=>'2030-12-19 12:00:00']),array_replace($base,['participant_id'=>4,'mode'=>'OWN','companion_of'=>1,'first_name'=>'Carla']),array_replace($base,['participant_id'=>5,'mode'=>'NEED','state'=>'ASSIGNED','driver_id'=>1,'first_name'=>'Annullato','person_status'=>'CANCELLED'])];
$wpdb=new class {public $prefix='wp_',$last_error='';function prepare($q,...$args){return $q;}function get_var($q){return 42;}function get_results(...$args){return $GLOBALS['fixtures'];}function get_row(...$args){return ['access_token'=>str_repeat('a',64)];}};
$crews=MI_Travel::management_crews(42);
if(count($crews)!==1||$crews[0]['booking_quantity']!==2||$crews[0]['assigned']!==1||$crews[0]['reserved']!==1||$crews[0]['available']!==1||count($crews[0]['members'])!==3)throw new RuntimeException('Crew counts or membership incorrect');
if (getenv('MI_TRAVEL_PREVIEW_UNCOMMITTED')) $fixtures = [$base, $fixtures[3]];
if (getenv('MI_TRAVEL_PREVIEW_PROPOSAL_ONLY')) unset($fixtures[1]);
if (getenv('MI_TRAVEL_PREVIEW_EXTERNAL')) {
    foreach ($fixtures as &$fixture) {
        if ($fixture['mode'] === 'NEED') $fixture['registration_id'] = 2;
    }
    unset($fixture);
}
$_GET=['mi_travel'=>1,'travel_token'=>str_repeat('a',64)];$_SERVER['REQUEST_METHOD']='GET';
MI_Travel::route();
