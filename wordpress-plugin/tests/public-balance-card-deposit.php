<?php
// Actual balance/receipt methods, synthetic database and WordPress adapters.
$fixture=explode('function check_balance(',file_get_contents(__DIR__.'/public-balance-model.php'),2)[0];
eval('?>'.str_replace('__DIR__',var_export(__DIR__,true),$fixture));
function verify_receipt($ok,$message){if(!$ok)throw new RuntimeException($message);}
$wpdb=new BalanceDB();
$snapshot=json_decode($wpdb->reg['snapshot_json'],true);
$snapshot['event']['deposit_mode']='PERCENTAGE';$snapshot['event']['deposit_percentage']=30;
$wpdb->reg['snapshot_json']=json_encode($snapshot);
$view=MI_Public_Balance::lookup(42,['action'=>'lookupByCognome','cognome'=>'decclesia']);
$data=['persone'=>[['row'=>1,'token'=>$view['persona']['token'],'version'=>$view['persona']['version'],'services'=>['pullman-a'=>1]]],'email'=>'','requestId'=>'12345678-1234-4234-8234-123456789c01'];
$receipt=MI_Public_Balance::save(42,$data,true);
verify_receipt($receipt['depositDue']===15300&&$receipt['saldoDue']===35700,'Percentage preview differs');
$body=new ReflectionMethod(MI_Public_Balance::class,'email_body');
foreach([['CARD'],['BANK_TRANSFER'],['CARD','BANK_TRANSFER'],[]] as $methods)foreach([51000,0] as $balance){
  $example=array_replace($receipt,['balance'=>$balance,'causale'=>'Saldo <prova>']);
  $html=$body->invoke(null,42,$example,['methods'=>$methods,'cardUrl'=>'https://example.invalid/pay','iban'=>'IBAN sintetico','holder'=>'Intestatario sintetico']);
  verify_receipt(str_contains($html,'href="https://example.invalid/pay"')===($balance>0&&in_array('CARD',$methods,true)),'Card link availability');
  verify_receipt(str_contains($html,'IBAN sintetico')===($balance>0&&in_array('BANK_TRANSFER',$methods,true)),'Bank instructions availability');
  verify_receipt(str_contains($html,'Saldo &lt;prova&gt;')===($balance>0),'Payment reference missing or unescaped');
}
$data['fingerprint']=$receipt['fingerprint'];$saved=MI_Public_Balance::save(42,$data);
verify_receipt($saved['people'][0]['deposit']===15300&&$wpdb->persons[0]['deposit_due_cents']===15300,'Confirmed deposit not returned/stored');
echo "PASS: card/bank/zero balance email instructions and authoritative percentage preview/save.\n";
