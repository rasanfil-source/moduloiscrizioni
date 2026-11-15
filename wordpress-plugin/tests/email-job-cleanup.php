<?php
// Riepiloghi e coda sintetici: nessuna connessione a WordPress o invio reale.
define('ABSPATH',__DIR__); define('ARRAY_A','ARRAY_A');
$options=[]; $optionIds=[]; $nextId=0; $scheduled=[];
function absint($v){return abs((int)$v);}
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function add_option($k,$v,...$args){if(array_key_exists($k,$GLOBALS['options']))return false;return update_option($k,$v);}
function update_option($k,$v,...$args){if(!isset($GLOBALS['optionIds'][$k]))$GLOBALS['optionIds'][$k]=++$GLOBALS['nextId'];$GLOBALS['options'][$k]=$v;return true;}
function delete_option($k){unset($GLOBALS['options'][$k],$GLOBALS['optionIds'][$k]);}
function wp_next_scheduled($hook){return false;}
function wp_schedule_single_event(...$args){$GLOBALS['scheduled'][]=$args;}
function sanitize_text_field($v){return $v;}
class CleanupDatabase {
    public $prefix='wp_', $options='wp_options', $last_error='', $queue=[], $fail=false, $busy=false, $reads=0;
    function esc_like($v){return $v;}
    function prepare($sql,...$args){return [$sql,$args];}
    function get_var($q){return str_contains($q[0],'GET_LOCK')&&$this->busy?0:1;}
    function get_results($q,$mode){
        $this->last_error=''; [$sql,$args]=$q;
        if(str_contains($sql,'FROM wp_options')) {
            $this->reads++; $rows=[];
            foreach($GLOBALS['optionIds'] as $k=>$id)if(str_starts_with($k,'mi_email_job_')&&$id>$args[1])$rows[]=['option_id'=>$id,'option_name'=>$k];
            usort($rows,fn($a,$b)=>$a['option_id']<=>$b['option_id']);return array_slice($rows,0,100);
        }
        if($this->fail){$this->last_error='Synthetic read failure';return [];}
        $ids=count($args)===1&&is_array($args[0])?$args[0]:$args;$counts=[];
        foreach($ids as $id)if(isset($this->queue[$id]))$counts[$this->queue[$id]]=($counts[$this->queue[$id]]??0)+1;
        return array_map(fn($s,$n)=>['status'=>$s,'total'=>$n,'last_error'=>''],array_keys($counts),array_values($counts));
    }
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-email-jobs.php';
function expect_cleanup($ok,$why){if(!$ok)throw new RuntimeException($why);}
function job_cleanup($label,$state='prepared',$status='SENT',$age=700000){
    global $wpdb;$id=hash('sha256',$label);
    update_option('mi_email_job_'.$id,['state'=>$state,'owner'=>7,'origin_keys'=>[$id],'created_at'=>time()-900000]);
    if($status!==null)$wpdb->queue[$id]=$status;
    if($age!==null)update_option('mi_email_job_completed_'.$id,time()-$age);
    return $id;
}
$wpdb=new CleanupDatabase();
$expired=job_cleanup('expired');$recent=job_cleanup('recent','prepared','FAILED',60);
$unseen=job_cleanup('unobserved','prepared','TEST_SENT',null);
$failed=job_cleanup('preparation-failed','failed','FAILED');
$active=job_cleanup('reopened','prepared','PENDING');$sending=job_cleanup('uncertain','prepared','SENDING');
$pending=job_cleanup('still-preparing','pending');$unknown=job_cleanup('unknown','prepared','FUTURE_STATUS');$missing=job_cleanup('missing','prepared',null);
$orphan=hash('sha256','orphan');update_option('mi_email_job_completed_'.$orphan,time()-800000);
update_option('mi_email_last_job_7',$expired);update_option('mi_email_last_test_job_7',$recent);
$queue=$wpdb->queue;MI_Email_Jobs::cleanup();
foreach([$expired,$failed]as$id)expect_cleanup(!get_option('mi_email_job_'.$id)&&!get_option('mi_email_job_completed_'.$id),'Expired job retained');
foreach([$recent,$unseen,$active,$sending,$pending,$unknown,$missing]as$id)expect_cleanup(is_array(get_option('mi_email_job_'.$id)),'Active/recent/unknown job deleted');
expect_cleanup(get_option('mi_email_job_completed_'.$unseen)>=time()-2,'Unobserved completion not detected');
expect_cleanup(!get_option('mi_email_job_completed_'.$active)&&!get_option('mi_email_job_completed_'.$sending),'Reopened completion not reset');
expect_cleanup(!get_option('mi_email_last_job_7')&&get_option('mi_email_last_test_job_7')===$recent,'User pointer cleanup wrong');
expect_cleanup(!get_option('mi_email_job_completed_'.$orphan)&&$queue===$wpdb->queue,'Orphan marker or authoritative queue changed');
$wpdb->fail=true;$protected=job_cleanup('read-failure');MI_Email_Jobs::cleanup();expect_cleanup(is_array(get_option('mi_email_job_'.$protected)),'Read error interpreted as done');$wpdb->fail=false;
$wpdb->busy=true;$before=$options;MI_Email_Jobs::cleanup();expect_cleanup($before===$options,'Worker lock ignored');$wpdb->busy=false;
$pageJobs=[];for($i=0;$i<120;$i++)$pageJobs[]=job_cleanup('page-'.$i);
$reads=$wpdb->reads;MI_Email_Jobs::cleanup();expect_cleanup($wpdb->reads===$reads+1&&get_option('mi_email_cleanup_cursor')>0&&count($scheduled)>0,'Cleanup not bounded or continuation missing');
for($i=0;$i<4&&get_option('mi_email_cleanup_cursor');$i++)MI_Email_Jobs::cleanup();
expect_cleanup(get_option('mi_email_cleanup_cursor')===0,'Cursor did not wrap');
foreach($pageJobs as $id)expect_cleanup(!get_option('mi_email_job_'.$id),'Pagination left expired job');
echo "PASS: seven-day cleanup, unseen completions, active/reopened/uncertain queues, SQL failures, pointers, orphans, worker lock and bounded pagination.\n";
