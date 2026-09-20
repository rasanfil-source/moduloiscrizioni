<?php
define('ABSPATH',__DIR__);
function sanitize_key($v){return strtolower((string)$v);}
function get_post_meta($id,$key,$single){return $GLOBALS['meta'][$key]??'';}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-field-schema.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([
 [[], 'MINIMO'],
 [['_mi_pricing_mode'=>'FIXED'], 'QUOTA_UNICA'],
 [['_mi_pricing_mode'=>'FIXED','_mi_options'=>[]], 'QUOTA_UNICA'],
 [['_mi_pricing_mode'=>'FIXED','_mi_options'=>[['code'=>'extra-demo']]], 'SERVIZI_MULTIPLI'],
 [['_mi_overnight_enabled'=>'1'], 'VIAGGIO_COMPLESSO'],
 [['_mi_participant_fields'=>['document_expiry']], 'VIAGGIO_COMPLESSO'],
 [['_mi_participant_fields'=>['document_country']], 'VIAGGIO_COMPLESSO'],
 [['_mi_operational_profile'=>'MINIMO','_mi_overnight_enabled'=>'1'], 'MINIMO'],
] as [$meta,$expected]){ $GLOBALS['meta']=$meta;check(MI_Field_Schema::resolved_operational_profile(42)===$expected,'Wrong resolved profile: '.$expected); }
$GLOBALS['meta']=['_mi_pricing_mode'=>'ZERO','_mi_participant_fields'=>['birth_date'],'_mi_custom_participant_fields'=>[],'_mi_options'=>[]];
$schema=MI_Field_Schema::workspace_event_schema(42);
check(array_column($schema['fields'],'key')===['birth_date'],'Schema added documents not requested by event');
check($schema['options']===[] && !$schema['room'] && $schema['pricing']==='ZERO','Free event gained services or payments');
$GLOBALS['meta']['_mi_data_profile']='TRAVEL';$GLOBALS['meta']['_mi_participant_fields']=['document_expiry','document_country'];
check(array_column(MI_Field_Schema::workspace_event_schema(42)['fields'],'key')===['document_expiry','document_country'],'Configured field keys not preserved');
echo "PASS: current event profile, exact field schema, empty event, explicit choice and document fields.\n";
