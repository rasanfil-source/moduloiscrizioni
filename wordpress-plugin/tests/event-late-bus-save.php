<?php
// Due salvataggi dello stesso evento pubblicato: inizialmente nessun pullman, poi quattro tratte.
ob_start();require __DIR__.'/event-transport-save.php';ob_end_clean();
$GLOBALS['transport_post_status']='publish';
transport_save('CALCULATED',false,['bus_route_code'=>[],'bus_route_id'=>[],'bus_route_price'=>[],'economic_mode'=>'DEPOSIT_BALANCE','deposit_mode'=>'FIXED','deposit_fixed'=>'150']);
$initial=$GLOBALS['transport_meta'];
transport_check(!array_filter($initial['_mi_options'],fn($o)=>str_starts_with($o['code'],'pullman')),'Pullman presente al primo salvataggio');
transport_check($initial['_mi_bus_assignment_enabled']==='0','Assegnazioni pullman attive troppo presto');
$GLOBALS['transport_meta']['_mi_economic_mode']='DEPOSIT_BALANCE';
$_POST=array_replace($_POST,['bus_enabled'=>'1','bus_route_code'=>['Roma → Fiumicino','Porto → Tui','Santiago → A Coruña','Fiumicino → Roma'],'bus_route_id'=>['pullman-roma-fco','pullman-porto-tui','pullman-santiago-coruna','pullman-fco-roma'],'bus_route_price'=>['10','15','15','10']]);
try{MI_Portal::handle_actions();throw new RuntimeException('Salvataggio successivo non intercettato');}catch(TransportSaved $done){}
$saved=array_column($GLOBALS['transport_meta']['_mi_options'],null,'code');
foreach(['roma-fco'=>1000,'porto-tui'=>1500,'santiago-coruna'=>1500,'fco-roma'=>1000] as $code=>$price)transport_check(($saved['pullman-'.$code]['price_cents']??null)===$price,'Nuova tratta o quota mancante: '.$code);
transport_check(isset($saved['external'])&&$GLOBALS['transport_meta']['_mi_economic_mode']==='DEPOSIT_BALANCE','Modalità economica o opzione esistente perse');
transport_check($GLOBALS['transport_meta']['_mi_bus_assignment_enabled']==='1','Assegnazioni non abilitate dopo aggiunta');
if(in_array('--catalog',$argv,true))echo json_encode(array_values(array_filter($saved,fn($o)=>($o['category']??'')==='pullman')));
else echo "PASS wizard: stesso evento pubblicato, inizialmente nessun pullman, quattro tratte aggiunte dopo con prezzi 10/15/15/10 €, modalità caparra/saldo conservata.\n";
