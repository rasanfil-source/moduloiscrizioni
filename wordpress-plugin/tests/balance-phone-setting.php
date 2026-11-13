<?php
ob_start();require __DIR__.'/event-transport-save.php';ob_end_clean();
$GLOBALS['transport_stop_meta']='_mi_balance_phone_check';
transport_save('CALCULATED',false,['balance_phone_settings_present'=>'1','balance_phone_check'=>'1']);
transport_check($GLOBALS['transport_meta']['_mi_balance_phone_check']==='1','Opzione non attivata dal wizard');
unset($_POST['balance_phone_check']);try{MI_Portal::handle_actions();}catch(TransportSaved $done){}
transport_check($GLOBALS['transport_meta']['_mi_balance_phone_check']==='0','Opzione non disattivata dal wizard');
$GLOBALS['transport_meta']['_mi_balance_phone_check']='1';unset($_POST['balance_phone_settings_present']);$GLOBALS['transport_stop_meta']='_mi_deposit_mode';
try{MI_Portal::handle_actions();}catch(TransportSaved $done){}
transport_check($GLOBALS['transport_meta']['_mi_balance_phone_check']==='1','Salvataggio senza impostazione cancella la scelta esistente');
echo "PASS wizard telefono: attivazione, disattivazione e conservazione della scelta nei salvataggi senza campo.\n";
