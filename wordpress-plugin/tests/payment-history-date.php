<?php
require __DIR__.'/payment-ledger.php';
function wp_date($format){return (new DateTimeImmutable('now',wp_timezone()))->format($format);}
function get_the_title($id){return 'Evento sintetico';}
$wpdb=new LedgerDatabase();
$wpdb->registration+=['order_code'=>'DEMO','buyer_first_name'=>'Persona','buyer_last_name'=>'Demo'];
foreach(['2026-09-09','2026-01-09','2026-03-29','2026-10-25'] as $day){
 $p=MI_Payment_Ledger::normalize(array_replace($base,['data'=>$day]));
 $wpdb->payments=[array_replace($p,['id'=>1,'operator_label'=>'Operatore','participant_allocations_json'=>'[]'])];
 $detail=MI_Payment_Ledger::detail(1);
 check(is_array($detail)&&$detail['ok'],'Detail unavailable');
 check($detail['saldo']['movimenti'][0]['data_locale']===(new DateTimeImmutable($day))->format('d/m/Y'),'Accounting date changed: '.$day);
}
echo "PASS: payment detail preserves calendar date in summer, winter and DST transitions.\n";
