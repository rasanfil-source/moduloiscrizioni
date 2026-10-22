<?php
define('ABSPATH', __DIR__);
function is_user_logged_in(){return true;}
function current_user_can($cap){return true;}
function absint($v){return abs((int)$v);}
function wp_unslash($v){return $v;}
function sanitize_text_field($v){return $v;}
function esc_url($v){return $v;}
function esc_attr($v){return htmlspecialchars((string)$v);}
function esc_html($v){return esc_attr($v);}
function admin_url($v){return $v;}
function wp_create_nonce($v){return 'test';}
function selected($a,$b){if((string)$a===(string)$b)echo 'selected';}
class MI_Event_Post_Type {const EVENT_TYPE='event';}
class MI_Access {static $scope='ALL';static function is_suspended(){return false;}static function event_ids(){return self::$scope;}static function can_access_event($id){return self::$scope==='ALL'||in_array($id,self::$scope,true);}}
class MI_Portal {static function is_past_event($end){return $end==='past';}}
function get_post_meta($id,$key,$single){return $key==='_mi_event_starts_at'&&$id===30?'past':'';}
function get_posts($query){if($query['orderby']!==array('date'=>'DESC','ID'=>'DESC'))throw new RuntimeException('Missing creation order');return array_values(array_filter(array_map(fn($id)=>(object)array('ID'=>$id,'post_title'=>'Evento '.$id),array(30,20,10)),fn($e)=>!isset($query['post__in'])||in_array($e->ID,$query['post__in'],true)));}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal-management.php';
foreach(array(array(array(),'ALL',20),array(array('mi_portal_event'=>10),'ALL',10),array(array('mi_portal_event'=>0),'ALL',0),array(array('mi_portal_event'=>''),'ALL',0),array(array('mi_portal_period'=>'past'),'ALL',30),array(array(),array(10),10),array(array(),array(),0)) as [$get,$scope,$expected]){
 $_GET=$get;MI_Access::$scope=$scope;ob_start();MI_Portal_Management::render();$html=ob_get_clean();
 if(!str_contains($html,'data-event="'.$expected.'"'))throw new RuntimeException('Wrong default or explicit selection');
 if($expected&&!preg_match('/value="'.$expected.'"\s+selected/',$html))throw new RuntimeException('Selector and request context disagree');
}
echo "PASS management default, another event, explicit all, period, restricted scope and empty list.\n";
