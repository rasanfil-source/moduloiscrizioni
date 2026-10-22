<?php
define('ABSPATH',__DIR__);
class MI_Event_Post_Type { const EVENT_TYPE='mi_event'; const ACTIVITY_TYPE='mi_activity'; }
function get_current_user_id(){return 7;}
function get_user_by($key,$id){return (object)['ID'=>$id,'roles'=>['mi_group_manager']];}
function wp_get_current_user(){return get_user_by('id',7);}
function user_can($user,$cap){return in_array($cap,$GLOBALS['caps'],true);}
function current_user_can($cap){return user_can(wp_get_current_user(),$cap);}
function get_user_meta($id,$key,$single){return $key==='_mi_access_suspended'?$GLOBALS['suspended']: [9];}
function get_post_meta($id,$key,$single){return $GLOBALS['group'];}
function update_post_meta($id,$key,$value){$GLOBALS['group']=$value;}
function wp_is_post_autosave($id){return $GLOBALS['autosave'];}
function wp_is_post_revision($id){return false;}
function wp_verify_nonce($nonce,$action){return $nonce==='valid'&&$action==='mi_save_event';}
function sanitize_text_field($v){return $v;}
function wp_unslash($v){return $v;}
function absint($v){return abs((int)$v);}
function get_post_type($id){return $id===42?'mi_event':($id===9||$id===10?'mi_activity':'page');}
function get_post($id){return $GLOBALS['post'];}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-access.php';
function setup_first_save(){
 $GLOBALS['caps']=['mi_create_events','mi_manage_events'];$GLOBALS['group']=0;$GLOBALS['suspended']=false;$GLOBALS['autosave']=false;
 $GLOBALS['post']=(object)['ID'=>42,'post_author'=>7,'post_type'=>'mi_event','post_status'=>'draft'];
 $_POST=['mi_event_nonce'=>'valid','mi_activity_id'=>9];
}
function expect_first($value,$message){if(!$value)throw new RuntimeException($message);}
foreach(['draft','publish','pending','private','future'] as $status){
 setup_first_save();$post->post_status=$status;
 expect_first(!MI_Access::can_access_event(42),'Uninitialized event accessible');
 MI_Access::initialize_event_group($status,'auto-draft',$post);
 expect_first($group===9&&MI_Access::can_access_event(42),'Initial group not assigned');
 expect_first(MI_Access::map_event_meta_cap([],'edit_post',7,[42])===['mi_manage_events'],'save_post still blocked');
}
foreach(['nonce','author','scope','type','suspended','cap','autosave','existing','old_status'] as $case){
 setup_first_save();$old='auto-draft';
 if($case==='nonce')$_POST['mi_event_nonce']='bad';
 if($case==='author')$post->post_author=8;
 if($case==='scope')$_POST['mi_activity_id']=10;
 if($case==='type')$_POST['mi_activity_id']=11;
 if($case==='suspended')$suspended=true;
 if($case==='cap')$caps=['mi_manage_events'];
 if($case==='autosave')$autosave=true;
 if($case==='existing')$group=10;
 if($case==='old_status')$old='draft';
 $before=$group;MI_Access::initialize_event_group('draft',$old,$post);
 expect_first($group===$before,'Unauthorized initialization: '.$case);
}
echo "PASS first event save: draft/publication, scope, author, nonce, capabilities, suspension, autosave and existing events.\n";
