<?php
define('ABSPATH', __DIR__);
function sanitize_key($v){return $v;}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-admin.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-service.php';
function verify_demo($ok){if(!$ok)throw new RuntimeException('Scenario demo non valido');}
$event=['ticket_types'=>[['code'=>'base','max_per_order'=>5]],'participant_extra_scope'=>'ALL','participant_fields'=>[['key'=>'participant_phone','type'=>'tel']], 'options'=>[]];
foreach(MI_Management_Service::room_types() as $code=>$type)$event['options'][]=['code'=>$code,'scope'=>'TICKET'];
$base=['tickets'=>['base'=>1],'participants'=>[['ticket_type_code'=>'base','ticket_index'=>1,'first_name'=>'Demo','last_name'=>'Prova','fields'=>[],'options'=>[]]],'buyer'=>[],'order_options'=>[]];
$single=MI_Admin::demo_services_payload($event,$base,0);verify_demo(count($single['participants'])===1&&isset($single['participants'][0]['options']['alloggio-singola']));
$double=MI_Admin::demo_services_payload($event,$base,1);verify_demo(count($double['participants'])===2&&$double['participants'][0]['fields']!==$double['participants'][1]['fields']);
$separate=MI_Admin::demo_services_payload($event,$base,2);verify_demo(count($separate['participants'])===1&&isset($separate['participants'][0]['options']['alloggio-doppia-separati']));
$triple=MI_Admin::demo_services_payload($event,$base,3);verify_demo(count($triple['participants'])===3);
verify_demo(MI_Admin::demo_services_payload($event,$base,1)['participants'][0]['fields']===$double['participants'][0]['fields']);
$event['ticket_types'][0]['max_per_order']=1;verify_demo(count(MI_Admin::demo_services_payload($event,$base,1)['participants'])===1);
$event['options']=[];verify_demo(MI_Admin::demo_services_payload($event,$base,0)['participants'][0]['options']===[]);
echo "Scenari demo: singola, matrimoniale, separati, tripla, cellulari stabili e limiti verificati.\n";
