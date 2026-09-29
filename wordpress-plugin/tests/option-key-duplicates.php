<?php
// Real create/option validation with the existing synthetic registration adapter.
$testDir = __DIR__;
$fixture = explode('$failures=[];', file_get_contents($testDir . '/registration-failures.php'), 2)[0];
$fixture = str_replace('__DIR__', var_export($testDir, true), $fixture);
$fixture = str_replace('function sanitize_key($v){return strtolower((string)$v);}', "function sanitize_key(\$v){return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)\$v));}", $fixture);
$fixture = str_replace('class MI_Portal {', 'class MI_Portal { static function status_url(...$args){return "https://example.invalid/status";}', $fixture);
eval('?>' . $fixture);
class AuditOptionsDB extends FaultDatabase {
    public $people=[];
    function insert($table,$data,...$args){
        $result=parent::insert($table,$data,...$args);
        if($table==='wp_mi_participants')$this->people[]=$data;
        return $result;
    }
}
$GLOBALS['fail_schedule']=false;
$event['pricing_mode']='FIXED'; $event['fixed_price_cents']=10000; $event['economic_mode']='FULL_PAYMENT';
$event['options']=[['code'=>'pullman-a','name'=>'Transfer','scope'=>'TICKET','category'=>'pullman','max_quantity'=>1,'price_cents'=>1000]];
$over=MI_Registration_Service::validate_options(['pullman-a'=>2],$event['options'],'TICKET');
$payload['participants'][0]['options']=['pullman-a'=>1,'PULLMAN-A'=>1];
$wpdb=new AuditOptionsDB();
$result=MI_Registration_Service::create(42,$payload,'audit-2026-09-29-options',false,'TEST',true);
$saved=json_decode($wpdb->people[0]['options_json']??'[]',true);
$confirmed=is_wp_error($over)&&$over->code==='mi_option_limit'&&is_wp_error($result)&&$result->code==='mi_option_duplicate'&&!$wpdb->people;
echo json_encode(['case'=>'duplicate_normalized_option_keys_bypass_maximum','regression_passed'=>$confirmed,'single_quantity_2_error'=>is_wp_error($over)?$over->code:null,'configured_maximum'=>1,'saved_options'=>$saved,'saved_total_cents'=>$wpdb->registration['total_cents']??null,'create_result'=>$result],JSON_UNESCAPED_SLASHES)."\n";
if(!$confirmed)throw new RuntimeException('Duplicate option accepted');

$payload['participants'][0]['options']=['pullman-a'=>1];
$wpdb=new AuditOptionsDB();
$result=MI_Registration_Service::create(42,$payload,'audit-valid-options',false,'TEST',true);
if(is_wp_error($result)||$wpdb->registration['total_cents']!==11000)throw new RuntimeException('Valid single option rejected');
