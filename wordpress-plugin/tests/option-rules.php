<?php
define('ABSPATH',__DIR__);
function sanitize_key($v){return strtolower((string)$v);}
function sanitize_text_field($v){return trim((string)$v);}
function absint($v){return abs((int)$v);}
class WP_Error {function __construct(public $code,public $message,$data=null){}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-registration-service.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$room=['code'=>'alloggio-s','scope'=>'TICKET','name'=>'Singola','max_quantity'=>1,'price_cents'=>1000,'choice_group'=>''];
$extra=['code'=>'extra-room','scope'=>'TICKET','name'=>'Altra sistemazione','max_quantity'=>1,'price_cents'=>900,'category'=>'alloggio','choice_group'=>'alloggio'];
$bus=['code'=>'extra-bus','scope'=>'TICKET','name'=>'Pullman','max_quantity'=>1,'price_cents'=>100,'category'=>'pullman'];
check(MI_Option_Rules::choice_group($room)==='alloggio','Empty legacy group bypasses alternatives');
check(MI_Option_Rules::is_accommodation($extra),'Custom accommodation not recognized');
check(!MI_Option_Rules::is_bus(['code'=>'pullman-room','category'=>'alloggio']),'Accommodation editable through public bus flow');
check(MI_Option_Rules::is_bus($bus),'Custom bus not recognized');
check(MI_Registration_Service::validate_options(['alloggio-s'=>1,'extra-room'=>1],[$room,$extra],'TICKET') instanceof WP_Error,'Legacy and explicit accommodation alternatives accepted together');
check(count(MI_Registration_Service::validate_options(['extra-room'=>1,'extra-bus'=>1],[$room,$extra,$bus],'TICKET'))===2,'Room and transport cannot coexist');
check(MI_Option_Rules::choice_group(['category'=>'supplemento','choice_group'=>null])==='','Cumulative supplement became exclusive');
check(MI_Option_Rules::choice_group(['code'=>'extra-night','category'=>'alloggio','choice_group'=>''])==='','Cumulative accommodation extra became exclusive');
check(MI_Option_Rules::choice_group(['category'=>'pullman','choice_group'=>'outbound'])==='outbound','Explicit bus alternatives lost');
echo "PASS: shared option semantics, legacy fallback, individual accommodation uniqueness and public restrictions.\n";
