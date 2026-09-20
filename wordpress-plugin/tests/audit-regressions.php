<?php
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
function absint($v){return abs((int)$v);}
class MI_Access { static $scope=[42];static function event_ids(){return self::$scope;}static function can_access_event($id){return in_array($id,(array)self::$scope,true);} }
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-admin.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-rest-controller.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-option-rules.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-people.php';
function verify($ok,$message){if(!$ok)throw new Exception($message);}
$method=new ReflectionMethod('MI_Admin','payment_where');
[$where]=$method->invoke(null,0,'','','','');verify(str_contains($where,'IN (42)'),'Assegnazione singolo evento non rispettata');
MI_Access::$scope=[];[$where]=$method->invoke(null,0,'','','','');verify(str_contains($where,'IN (0)'),'Ambito vuoto espone eventi');
[$where]=$method->invoke(null,43,'','','','');verify($where==='WHERE 1 = 0','Evento non autorizzato esposto');
$csv=new ReflectionMethod('MI_Admin','safe_csv_value');foreach(["\t=1","\r=1","\n=1",'=1','+1','@x'] as $value)verify(str_starts_with($csv->invoke(null,$value),"'"),'Cella CSV non neutralizzata');
$options=(new ReflectionMethod('MI_REST_Controller','workspace_service_options'))->invoke(null,['PULLMAN','PRANZO'],['SINGOLA','DOPPIA_SEPARATI']);
$by=array_column($options,null,'code');verify(isset($by['alloggio-singola'],$by['alloggio-doppia-separati']),'Codici camera non canonici');
verify(MI_Option_Rules::is_bus($by['pullman-standard'])&&MI_Option_Rules::is_accommodation($by['alloggio-singola']),'Semantica servizi non riconosciuta');
$registration=['total_cents'=>100,'economic_mode'=>'FULL_PAYMENT','order_options_json'=>'[]','common_allocations_json'=>'{"1":99}'];
$people=[['id'=>1,'ticket_type_code'=>'A','first_name'=>'Test','last_name'=>'Sintetico','status'=>'ACTIVE','options_json'=>'[]']];$items=[['ticket_type_code'=>'A','unit_price_cents'=>100]];
$position=MI_Payment_People::calculate_for_display($registration,$people,$items,[]);
verify(!$position['ready']&&!$position['quotes_known']&&$position['message']!=='','Corruzione non isolata nella lettura');
try{MI_Payment_People::calculate($registration,$people,$items,[]);throw new Exception('Scrittura permissiva');}catch(InvalidArgumentException $expected){}
$registration['common_allocations_json']='{"1":0}';verify(MI_Payment_People::calculate_for_display($registration,$people,$items,[])['ready'],'Prenotazione sana compromessa');
echo "PASS: ambiti evento, CSV, codici servizi, isolamento quote e rifiuto scritture incoerenti.\n";
