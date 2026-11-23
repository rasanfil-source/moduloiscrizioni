<?php
define('ABSPATH', __DIR__); define('ARRAY_A','ARRAY_A');
class WP_Error { function __construct(public $code, public $message, public $data=[]){} }
class WP_REST_Response { public $headers=[]; function __construct(public $data){} function header($key,$value){$this->headers[$key]=$value;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);}
function wp_salt($v){return 'synthetic';}
function wp_json_encode($v){return json_encode($v);}
function wp_timezone(){return new DateTimeZone('UTC');}
function wp_date($format,$stamp,$zone){return gmdate($format,$stamp);}
function get_post_meta(...$args){return '';}
$transients=[];
function get_transient($key){return $GLOBALS['transients'][$key]??false;}
function set_transient($key,$value,$ttl){$GLOBALS['transients'][$key]=$value;}
class MI_Registration_Service {
 static $ip='192.0.2.1', $enabled=true, $reads=0;
 static function client_ip(){return self::$ip;}
 static function public_event($id){self::$reads++;return ['id'=>$id,'travel'=>['enabled'=>self::$enabled,'deadline'=>gmdate('Y-m-d\TH:i',time()+86400*4)]];}
}
class AvailabilityDB {
 public $prefix='wp_', $last_error='', $queries=[], $lock=true, $releases=0;
 function prepare($sql,...$args){return $sql;}
 function get_var($sql){if(str_contains($sql,'RELEASE_LOCK')){$this->releases++;return '1';}return $this->lock?'1':'0';}
 function get_results($sql,$mode){$this->queries[]=$sql;return [
  ['participant_id'=>1,'driver_id'=>null,'mode'=>'DRIVER','seats'=>3,'state'=>'NONE','offer_expires_at'=>null,'origin'=>'Roma','departure_at'=>gmdate('Y-m-d H:i:s',time()+86400*5),'price_cents'=>500,'person_status'=>'ACTIVE','booking_status'=>'CONFIRMED','capacity_released_at'=>null],
  ['participant_id'=>2,'driver_id'=>1,'mode'=>'NEED','seats'=>0,'state'=>'ASSIGNED','offer_expires_at'=>null,'person_status'=>'ACTIVE','booking_status'=>'CONFIRMED','capacity_released_at'=>null],
 ];}
}
$wpdb=new AvailabilityDB();
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-travel.php';
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);}
for($i=0;$i<120;$i++){
 $response=MI_Travel::availability(['id'=>42]);
 verify($response instanceof WP_REST_Response,'Allowed request failed');
 verify($response->data['rides'][0]['seats']===2,'Occupied seat calculation changed');
 verify(array_keys($response->data['rides'][0])===['route_key','id','origin','departure','seats','price_cents'],'Public response changed');
}
$blocked=MI_Travel::availability(['id'=>43]);
verify($blocked->data['status']===429 && MI_Registration_Service::$reads===120,'Limit bypassed across events or applied too late');
verify($wpdb->releases===121,'Lock leaked on limit');
foreach($wpdb->queries as $sql) foreach(['buyer_email','buyer_phone','first_name','last_name','access_token','t.*'] as $private) verify(!str_contains($sql,$private),'Private data selected');
foreach($transients as &$window) $window['until']=time()-1; unset($window);
verify(MI_Travel::availability(['id'=>42]) instanceof WP_REST_Response,'Expired window not reset');
MI_Registration_Service::$ip='192.0.2.2';MI_Registration_Service::$enabled=false;$before=count($wpdb->queries);
verify(MI_Travel::availability(['id'=>42])->data['rides']===[] && count($wpdb->queries)===$before,'Disabled event reads rides');
$wpdb->lock=false;verify(MI_Travel::availability(['id'=>42])->data['status']===503,'Busy limiter accepted');
echo "PASS: travel availability, seat counts, fixed-window rate limit, lock release, expiry, reduced query and disabled events.\n";
