<?php
// Diagnostics only: execute real handlers against synthetic WordPress metadata.
$root=dirname(__DIR__,2);$fixture=$root.'/wordpress-plugin/tests/php-behavior.php';
$prefix=explode('$event = array(',file_get_contents($fixture),2)[0];
$prefix=str_replace('$format, $timestamp, $timezone = null','$format, $timestamp = null, $timezone = null',$prefix);
$prefix=str_replace("new DateTimeImmutable( '@' . \$timestamp )","new DateTimeImmutable( '@' . (\$timestamp ?? time()) )",$prefix);
eval('?>'.str_replace('__DIR__',var_export(dirname($fixture),true),$prefix));
define('MINUTE_IN_SECONDS',60);define('ARRAY_A','ARRAY_A');
function wp_unslash($v){return $v;}function wp_verify_nonce(...$v){return true;}
function wp_is_post_autosave($id){return false;}function wp_is_post_revision($id){return false;}
function current_user_can(...$v){return true;}function get_current_user_id(){return 1;}
function get_privacy_policy_url(){return 'https://example.invalid/privacy';}
function get_post_type($id){return MI_Event_Post_Type::ACTIVITY_TYPE;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$v){$GLOBALS['meta'][$id][$key]=$v;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function set_transient(...$v){}function wp_json_encode($v){return json_encode($v);}
function sanitize_title($v){return sanitize_key($v);}function get_posts(...$v){return [];}
function wp_die($v){throw new RuntimeException($v);}
class MI_Access{static function can_access_activity($id){return true;}}
foreach(['event-post-type','admin','extra-services','attendance-report','payment-ledger']as $name)require_once $root.'/wordpress-plugin/modulo-iscrizioni/includes/class-mi-'.$name.'.php';
function result($name,$data){echo json_encode(['case'=>$name]+$data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";}
$options=MI_Extra_Services::parse(['extra_service_code'=>['extra-a','extra-b'],'extra_service_label'=>['Trasferimento A','Trasferimento B'],'extra_service_price'=>['10','20'],'extra_service_category'=>['trasferimento','trasferimento'],'extra_service_group'=>['trasporto','trasporto']]);
expect(!is_wp_error($options),'Fixture options');$GLOBALS['meta'][42]['_mi_options']=$options;
$_POST=['mi_event_nonce'=>'synthetic','mi_activity_id'=>5,'mi_registration_closes_at'=>'2099-01-01T10:00','mi_pricing_mode'=>'CALCULATED','mi_economic_mode'=>'PRICE_ONLY','mi_ticket_code'=>['standard'],'mi_ticket_name'=>['Standard'],'mi_ticket_price'=>['100'],'mi_option_code'=>array_column($options,'code'),'mi_option_name'=>array_column($options,'name'),'mi_option_scope'=>['TICKET','TICKET'],'mi_option_price'=>['10','20'],'mi_option_max'=>[1,1]];
$before=MI_Registration_Service::validate_options(['extra-a'=>1,'extra-b'=>1],$options,'TICKET');
MI_Event_Post_Type::save_event(42,(object)['post_status'=>'publish']);
$saved=get_post_meta(42,'_mi_options');$after=MI_Registration_Service::validate_options(['extra-a'=>1,'extra-b'=>1],$saved,'TICKET');
expect(is_wp_error($before)&&is_wp_error($after),'Exclusive group was lost');
result('2_metadata_loss',['passed'=>true,'before'=>$options,'saved'=>$saved,'exclusive_combination_rejected_before'=>is_wp_error($before),'rejected_after'=>is_wp_error($after)]);
$_POST['mi_ticket_price']=['1.200,00'];
foreach(['mi_option_code','mi_option_name','mi_option_scope','mi_option_price','mi_option_max']as $key)$_POST[$key]=[];
$parsed=(new ReflectionMethod(MI_Admin::class,'parse_importo_centesimi'))->invoke(null,'1.200,00');
$guard=MI_Admin::guard_publication(['post_type'=>MI_Event_Post_Type::EVENT_TYPE,'post_status'=>'publish'],['ID'=>42]);
MI_Event_Post_Type::save_event(42,(object)$guard);$stored=get_post_meta(42,'_mi_ticket_types')[0]['price_cents'];
expect($parsed===120000&&$guard['post_status']==='publish'&&$stored===120000,'Price mismatch not reproduced');
result('3_price_raw_post',['passed'=>true,'input'=>'1.200,00','guard_cents'=>$parsed,'guard_status'=>$guard['post_status'],'stored_cents'=>$stored,'scope'=>'raw POST; normal UI uses type=number']);
$_POST=['mi_activity_nonce'=>'synthetic','mi_attendance_from_month'=>'','mi_attendance_to_month'=>''];
MI_Event_Post_Type::save_activity(5,(object)[]);
$defaults=[get_post_meta(5,'_mi_attendance_from_month'),get_post_meta(5,'_mi_attendance_to_month')];
$_POST['mi_attendance_from_month']='2026-09';$_POST['mi_attendance_to_month']='2026-06';
$blocked=false;try{MI_Event_Post_Type::save_activity(5,(object)[]);}catch(RuntimeException $e){$blocked=$e->getMessage();}
expect($blocked===false,'Disabled period not blocked');
result('5_period_disabled',['both_empty_default'=>$defaults,'invalid_period_blocks_even_disabled'=>$blocked]);
$normalized=MI_Field_Schema::sanitize_custom_fields([['key'=>'custom_Maiuscola','label'=>'Test','type'=>'text']]);
result('10_uppercase_custom',['saved_key'=>$normalized[0]['key']]);
$normalize=new ReflectionMethod(MI_Payment_Ledger::class,'normalize');
$movement=$normalize->invoke(null,['importo'=>'10','tipo'=>'STORNO','metodo'=>'CONTANTE','data'=>'2026-09-27','request_id'=>'wp_1_12345678-1234-4234-8234-123456789012']);
result('7_canonical_storno',['transaction_kind'=>$movement['transaction_kind'],'movement_kind'=>$movement['movement_kind']]);

expect($saved===$options,'Option metadata changed');
$_POST['mi_annual_attendance_report']='1';
try{MI_Event_Post_Type::save_activity(5,(object)[]);throw new LogicException('Enabled invalid period accepted');}catch(RuntimeException $e){}
foreach(['0'=>0,'12,50'=>1250,'1.200,00'=>120000,'1,200.00'=>120000,'1000000'=>100000000,'1000000.01'=>null,'12oops'=>null,'-1'=>null,'1e3'=>null]as $input=>$expected)expect(MI_Amount::cents($input)===$expected,'Amount '.$input);
expect(MI_Amount::cents([])===null,'Array amount');
echo "PASS metadata preservation, consistent amounts and optional attendance period.\n";
