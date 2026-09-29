<?php
// Real public balance and payment deadline calculation; database/email transport simulated.
$testDir=__DIR__;
$fixture=explode('function check_balance(',file_get_contents($testDir.'/public-balance-model.php'),2)[0];
$fixture=str_replace('__DIR__',var_export($testDir,true),$fixture);
$fixture=preg_replace('/static function reopened_payment_deadline\(.*?\}static function mark_workspace_changed_locked/s','static function reopened_payment_deadline($registration,$now=null){return AuditRealRegistration::reopened_payment_deadline($registration,$now??$GLOBALS["audit_now"]);}static function mark_workspace_changed_locked',$fixture,1);
eval('?>'.$fixture);
$source=file_get_contents(dirname($testDir).'/modulo-iscrizioni/includes/class-mi-registration-service.php');
$source=str_replace('__DIR__',var_export(dirname($testDir).'/modulo-iscrizioni/includes',true),$source);
$source=str_replace('final class MI_Registration_Service','final class AuditRealRegistration',$source);
eval('?>'.$source);
class AuditDeadlineDB extends BalanceDB {
    public $queued=[];
    function query($query){if(is_array($query)&&str_contains($query[0],'INSERT IGNORE INTO wp_mi_email_outbox'))$this->queued[]=json_decode($query[1][3],true);return parent::query($query);}
}
$passed=true;
foreach(['2020-01-01 12:00:00',null] as $old){
    $GLOBALS['audit_now']=time();
    $wpdb=new AuditDeadlineDB(); $wpdb->reg['expires_at']=$old; $wpdb->reg['payment_deadline_at']=$old;
    $view=MI_Public_Balance::lookup(42,['action'=>'lookupByCognome','cognome'=>'decclesia']);
    $data=['persone'=>[['row'=>1,'token'=>$view['persona']['token'],'version'=>$view['persona']['version'],'services'=>['pullman-a'=>1]]],'email'=>'','requestId'=>'12345678-1234-4234-8234-123456789d01'];
    $preview=MI_Public_Balance::save(42,$data,true); $data['fingerprint']=$preview['fingerprint'];
    $GLOBALS['audit_now']+=65;
    $saved=MI_Public_Balance::save(42,$data);
    $stored=$wpdb->reg['payment_deadline_at']; $shown=$saved['people'][0]['deadline'];
    $confirmed=$saved['success']&&strtotime($stored.' UTC')>time()&&$shown===$stored&&str_contains($wpdb->queued[0]['email_preview']['html']??'', $stored)&&$preview['people'][0]['deadline']!==($old??'');
    $passed=$passed&&$confirmed;
    if(strtotime($stored.' UTC')-strtotime($preview['people'][0]['deadline'].' UTC')!==65)throw new RuntimeException('Delayed confirmation not covered');
    $again=MI_Public_Balance::save(42,$data);
    if($again!==$saved||count($wpdb->queued)!==1)throw new RuntimeException('Idempotent receipt changed or email duplicated');
    echo json_encode(['case'=>'public_balance_receipt_has_obsolete_deadline','regression_passed'=>$confirmed,'old_deadline'=>$old,'actual_deadline'=>$stored,'receipt_deadline'=>$shown,'email_contains_actual_deadline'=>str_contains($wpdb->queued[0]['email_preview']['html']??'', $stored),'email_contains_obsolete_deadline'=>$old!==null&&str_contains($wpdb->queued[0]['email_preview']['html']??'',$old),'total_cents'=>$wpdb->reg['total_cents']],JSON_UNESCAPED_SLASHES)."\n";
}
$wpdb=new AuditDeadlineDB();$GLOBALS['audit_now']=time();
$view=MI_Public_Balance::lookup(42,['action'=>'lookupByCognome','cognome'=>'decclesia']);
$data['persone'][0]['version']=$view['persona']['version'];
$preview=MI_Public_Balance::save(42,$data,true);$data['fingerprint']=$preview['fingerprint'];
$wpdb->reg['payment_deadline_at']='2099-01-01 12:00:00';
try{MI_Public_Balance::save(42,$data);throw new RuntimeException('Staff deadline change ignored');}
catch(InvalidArgumentException $expected){if(!str_contains($expected->getMessage(),'riepilogo'))throw $expected;}
exit($passed?0:1);
