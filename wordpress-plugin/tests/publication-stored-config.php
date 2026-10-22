<?php
define('ABSPATH',__DIR__);define('MINUTE_IN_SECONDS',60);
class WP_Error {function __construct(...$args){}}
function is_wp_error($v){return $v instanceof WP_Error;}function absint($v){return abs((int)$v);}
function get_post_status($id){return $GLOBALS['status']??'draft';}
function get_post_meta($id,$key,$single){return 9;}function get_post_type($id){return 'activity';}
function get_current_user_id(){return 1;}function set_transient(...$args){}
class MI_Event_Post_Type {const EVENT_TYPE='event',ACTIVITY_TYPE='activity';}
class MI_Registration_Service {
 static function public_event(...$args){return $GLOBALS['config'];}
 static function validate_event_dates($config){return empty($config['dates_valid'])?new WP_Error():true;}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-admin.php';
$_POST=[];$data=['post_type'=>'event','post_status'=>'publish'];
$valid=['dates_valid'=>true,'privacy_url'=>'https://example.invalid/privacy','ticket_types'=>[['price_cents'=>0]],'economic_mode'=>'REGISTRATION_ONLY','pricing_mode'=>'ZERO'];
function expect_publication($expected){global $data;$result=MI_Admin::guard_publication($data,['ID'=>42]);if($result['post_status']!==$expected)throw new RuntimeException('Unexpected publication result');}
$config=$valid;expect_publication('publish');
$config=new WP_Error();expect_publication('draft');
$config=$valid;$config['dates_valid']=false;expect_publication('draft');
$config=$valid;$config['privacy_url']='';expect_publication('draft');
$config=$valid+['fixed_price_cents'=>100];$config['economic_mode']='FULL_PAYMENT';$config['pricing_mode']='FIXED';expect_publication('draft');
$config['payment_methods']=['BANK_TRANSFER'];expect_publication('publish');
$config['fixed_price_cents']=0;expect_publication('draft');
$status='publish';$config=new WP_Error();expect_publication('publish');
echo "PASS: stored publication validates dates, privacy, price and payment methods without editor nonce.\n";
