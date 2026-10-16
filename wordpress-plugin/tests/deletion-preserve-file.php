<?php
define('ABSPATH',__DIR__);
class WP_Error {public $code;function __construct($code,$message){$this->code=$code;}}
class MI_Access {static function is_suspended(){return false;}static function is_global_manager(){return true;}}
class TestDB {public $prefix='wp_';function prepare($sql,...$args){return $sql;}function get_var($sql){return '1';}}
$wpdb=new TestDB();$options=[];$admin=true;
function is_user_logged_in(){return true;}
function current_user_can($cap){return $cap!=='manage_options'||$GLOBALS['admin'];}
function get_current_user_id(){return 7;}
function absint($v){return abs((int)$v);}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
function sanitize_text_field($v){return trim(strip_tags($v));}
function wp_unslash($v){return $v;}
function wp_verify_nonce($v,$a){return $v==='valid'&&$a==='mi_delete_event_42';}
function get_option($key,$default=[]){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,$autoload=false){$GLOBALS['options'][$key]=$value;}
function wp_cache_delete(...$args){}
function is_wp_error($v){return $v instanceof WP_Error;}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-event-deletion.php';
function expect_preserve($ok,$message){if(!$ok)throw new RuntimeException($message);}
$initial=['request'=>'12345678-1234-4234-8234-123456789abc','stage'=>'google','mode'=>'trash','error'=>'Drive not accessible','sheet_id'=>'file-42','sheet_url'=>'https://example.invalid/file'];
$options['mi_delete_event_42']=$initial;
$admin=false;expect_preserve(is_wp_error(MI_Event_Deletion::preserve_file(42,$initial['request'],'reason',true)),'Non-admin accepted');$admin=true;
foreach([['reason',false],['',true]] as [$reason,$confirmed])expect_preserve(is_wp_error(MI_Event_Deletion::preserve_file(42,$initial['request'],$reason,$confirmed)),'Missing confirmation/reason accepted');
expect_preserve(is_wp_error(MI_Event_Deletion::preserve_file(42,'wrong','reason',true)),'Wrong operation accepted');
$options['mi_delete_event_42']['stage']='sql';expect_preserve(is_wp_error(MI_Event_Deletion::preserve_file(42,$initial['request'],'reason',true)),'SQL stage changed');
$options['mi_delete_event_42']=$initial;$options['mi_delete_event_42']['error']='';expect_preserve(is_wp_error(MI_Event_Deletion::preserve_file(42,$initial['request'],'reason',true)),'No failure accepted');
$options['mi_delete_event_42']=$initial;
$_POST=['event_id'=>42,'_wpnonce'=>'bad','preserve_file'=>1,'confirm_preserve'=>1,'preserve_reason'=>'reason','request_id'=>$initial['request']];
$step=new ReflectionMethod(MI_Event_Deletion::class,'submitted_step');expect_preserve($step->invoke(null)->code==='mi_delete_nonce','Nonce not enforced');
$result=MI_Event_Deletion::preserve_file(42,$initial['request'],'Verified manually',true);
expect_preserve($result['mode']==='keep'&&$result['stage']==='google','Google stage bypassed');
expect_preserve($result['preserve_file']['actor']===7&&$result['preserve_file']['sheet_id']==='file-42'&&$result['preserve_file']['previous_error']===$initial['error'],'Audit lost');
expect_preserve(MI_Event_Deletion::preserve_file(42,$initial['request'],'Retry',true)===$result,'Retry changed audit');
echo "PASS administrative override: permissions, nonce, confirmation, reason, request identity, stage, durable audit and idempotency.\n";
