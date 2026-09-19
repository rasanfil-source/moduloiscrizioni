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
echo "PASS: current event profile, empty event, explicit choice and document fields.\n";
