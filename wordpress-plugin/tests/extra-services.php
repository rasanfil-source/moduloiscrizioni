<?php
require __DIR__.'/php-behavior.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-extra-services.php';
if(!function_exists('wp_generate_uuid4')){function wp_generate_uuid4(){return '12345678-1234-4234-8234-123456789abc';}}
$input=['extra_service_label'=>['Notte aggiuntiva'],'extra_service_code'=>['extra-notte'],'extra_service_price'=>['12,50'],'extra_service_category'=>['alloggio'],'extra_service_group'=>['']];
$options=MI_Extra_Services::parse($input);
expect(!is_wp_error($options)&&$options[0]['price_cents']===1250,'Centesimi servizio errati');
$method=new ReflectionMethod(MI_Registration_Service::class,'validate_options');$method->setAccessible(true);
$extra=$options[0];$extra['code']='extra-alternativa';$extra['choice_group']='notte';$options[0]['choice_group']='notte';$options[]=$extra;
expect(is_wp_error($method->invoke(null,['extra-notte'=>1,'extra-alternativa'=>1],$options,'TICKET')),'Alternative multiple accettate');
$options[0]['choice_group']='';$options[1]['choice_group']='';
expect(count($method->invoke(null,['extra-notte'=>1,'extra-alternativa'=>1],$options,'TICKET'))===2,'Supplementi cumulabili rifiutati');
$input['extra_service_price']=['12,345'];expect(is_wp_error(MI_Extra_Services::parse($input)),'Decimali eccessivi accettati');
echo "Servizi: quote esatte, alternative, supplementi cumulabili e input invalido verificati.\n";
