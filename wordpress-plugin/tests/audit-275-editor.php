<?php
function get_post_status($id){return $GLOBALS['audit_status']??'publish';}
require __DIR__.'/audit-confirmed-admin.php';
$GLOBALS['meta'][42]['_mi_capacity']=50;
$_POST=['mi_event_nonce'=>'synthetic','mi_activity_id'=>5,'mi_capacity'=>77,'mi_registration_closes_at'=>'2099-01-01T10:00','mi_pricing_mode'=>'CALCULATED','mi_economic_mode'=>'PRICE_ONLY','mi_ticket_code'=>['standard'],'mi_ticket_name'=>['Standard'],'mi_ticket_price'=>['100'],'mi_option_code'=>['pranzo'],'mi_option_name'=>['Pranzo'],'mi_option_scope'=>['TICKET'],'mi_option_price'=>[''],'mi_option_max'=>[1]];
$guard=MI_Admin::guard_publication(['post_type'=>MI_Event_Post_Type::EVENT_TYPE,'post_status'=>'publish'],['ID'=>42]);
MI_Event_Post_Type::save_event(42,(object)$guard);
expect($guard['post_status']==='publish','Published event demoted after invalid metadata');
expect(get_post_meta(42,'_mi_capacity')===50,'Rifiuto metadati non riprodotto');
echo json_encode(['case'=>'2.1-empty-option-price','requested_capacity'=>77,'stored_capacity'=>get_post_meta(42,'_mi_capacity'),'post_status'=>$guard['post_status']])."\n";

$GLOBALS['audit_status']='draft';
$guard=MI_Admin::guard_publication(['post_type'=>MI_Event_Post_Type::EVENT_TYPE,'post_status'=>'publish'],['ID'=>42]);
expect($guard['post_status']==='draft','Invalid new event published');
foreach(['','wrong','-1','1.001x']as $price){$_POST['mi_option_price']=[$price];expect(!MI_Amount::valid_event_prices($_POST),'Invalid price accepted');}
$_POST['mi_option_price']=['0'];expect(MI_Amount::valid_event_prices($_POST),'Explicit free option rejected');
$_POST['mi_pricing_mode']='FIXED';$_POST['mi_fixed_price']='';expect(!MI_Amount::valid_event_prices($_POST),'Empty fixed price accepted');
echo "PASS rejected metadata preserves published state and prior capacity; draft remains protected.\n";
