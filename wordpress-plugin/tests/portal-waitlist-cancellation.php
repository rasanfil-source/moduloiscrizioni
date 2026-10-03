<?php
// The real AJAX handler and real cancellation/promotion service, synthetic WordPress/DB.
$testDir = __DIR__;
$fixture = explode('$failures=[];', file_get_contents($testDir . '/registration-failures.php'), 2)[0];
$fixture = str_replace('__DIR__', var_export($testDir, true), $fixture);
$fixture = str_replace('class MI_Portal {', 'class MI_Portal { static function waitlist_offer_url(...$args) { return "https://example.invalid/offer"; }', $fixture);
$fixture = str_replace('class MI_Modello_Email {', 'class MI_Modello_Email { static function crea_istantanea_offerta_lista_attesa(...$args) { return []; }', $fixture);
eval('?>' . $fixture);
function is_user_logged_in() { return true; }
function nocache_headers() {}
function current_user_can($cap) { return in_array($cap, ['mi_portal_access','mi_manage_events'], true); }
function check_ajax_referer(...$args) { return true; }
function wp_unslash($value) { return $value; }
function get_current_user_id() { return 7; }
function wp_get_current_user() { return (object)['display_name'=>'Operatore sintetico']; }
function wp_date($format, $timestamp, $timezone) { return (new DateTimeImmutable('@'.$timestamp))->setTimezone($timezone)->format($format); }
class AuditJsonResponse extends RuntimeException { public function __construct(public $success, public $payload) { parent::__construct('JSON'); } }
function wp_send_json_success($payload) { throw new AuditJsonResponse(true, $payload); }
function wp_send_json_error($payload, ...$args) { throw new AuditJsonResponse(false, $payload); }
class MI_Access { static function is_suspended() { return false; } static function can_access_event($id) { return $id===42; } }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-portal-management.php';
class WaitlistAuditDatabase extends FaultDatabase {
    public $orders=[], $confirmed=1, $waiting=1, $outbox=[], $candidateReads=0, $transaction;
    function query($sql) {
        $this->last_error='';
        if ($sql==='START TRANSACTION') $this->transaction=[$this->orders,$this->confirmed,$this->waiting,$this->outbox];
        elseif ($sql==='ROLLBACK' && $this->transaction!==null) { [$this->orders,$this->confirmed,$this->waiting,$this->outbox]=$this->transaction; $this->transaction=null; }
        elseif ($sql==='COMMIT') { $this->commits++; $this->transaction=null; }
        elseif (str_starts_with($sql,'UPDATE wp_mi_event_counters')) {
            if (str_contains($sql,'confirmed_count = GREATEST(0, confirmed_count - 1)')) $this->confirmed--;
            if (str_contains($sql,'waitlisted_count = GREATEST')) { $this->confirmed++; $this->waiting--; }
        }
        return 1;
    }
    function get_row($sql,$mode) {
        if (str_contains($sql,'mi_registrations')) {
            if (str_contains($sql,"order_code='TEST1'")) return $this->orders[1];
            preg_match('/(?:WHERE\s+)?id\s*=\s*(\d+)/',$sql,$match);
            return $this->orders[(int)($match[1]??0)]??null;
        }
        if (str_contains($sql,'mi_event_counters') || str_contains($sql,'mi_ticket_counters')) return ['event_id'=>42,'confirmed_count'=>$this->confirmed,'waitlisted_count'=>$this->waiting];
        return parent::get_row($sql,$mode);
    }
    function get_results($sql,$mode) {
        if (str_contains($sql,'mi_ticket_counters')) return [['ticket_type_code'=>'a','confirmed_count'=>$this->confirmed,'waitlisted_count'=>$this->waiting]];
        if (str_contains($sql,'mi_registrations') && str_contains($sql,"status = 'WAITLISTED'")) { $this->candidateReads++; return array_values(array_filter($this->orders,fn($r)=>$r['status']==='WAITLISTED')); }
        if (str_contains($sql,'mi_participants') && str_contains($sql,'COUNT(*)')) return [['ticket_type_code'=>'a','quantity'=>1]];
        return parent::get_results($sql,$mode);
    }
    function update($table,$data,...$args) {
        $where=$args[0];
        if ($table==='wp_mi_registrations') $this->orders[$where['id']]=array_replace($this->orders[$where['id']],$data);
        return 1;
    }
    function insert($table,$data,...$args) { if ($table==='wp_mi_email_outbox') $this->outbox[]=$data; return parent::insert($table,$data,...$args); }
}
$event['capacity']=1; $event['waitlist_enabled']=true; $event['ticket_types'][0]['capacity']=1;
$GLOBALS['fail_schedule']=false; $GLOBALS['cancellation_pending']=false;
$outputs=[];
foreach (['portal_ajax','default_service','event_cancel'] as $mode) {
    $wpdb=new WaitlistAuditDatabase();
    $base=['event_id'=>42,'capacity_released_at'=>null,'total_qty'=>1,'total_cents'=>0,'economic_mode'=>'REGISTRATION_ONLY','buyer_first_name'=>'Persona','buyer_last_name'=>'Sintetica','buyer_email'=>'test@example.invalid','workspace_status'=>'PENDING'];
    $wpdb->orders=[1=>['id'=>1,'order_code'=>'TEST1','status'=>'CONFIRMED']+$base,2=>['id'=>2,'order_code'=>'TEST2','status'=>'WAITLISTED']+$base];
    if ($mode==='portal_ajax') {
        $_POST=['operation'=>'cancel_registration','event_id'=>42,'order_code'=>'TEST1'];
        try { MI_Portal_Management::ajax(); throw new RuntimeException('Missing response'); }
        catch (AuditJsonResponse $response) { if (!$response->success || empty($response->payload['saved'])) throw new RuntimeException(json_encode($response->payload)); }
    } else {
        $result=MI_Registration_Service::cancel_registration(1,'TEST',$mode!=='event_cancel',$mode!=='event_cancel');
        if ($result!=='CANCELLED') throw new RuntimeException('Cancellation failed: '.json_encode($result));
    }
    $outputs[$mode]=['cancelled_status'=>$wpdb->orders[1]['status'],'waiting_status'=>$wpdb->orders[2]['status'],'reserved_seats'=>$wpdb->confirmed,'candidate_reads'=>$wpdb->candidateReads,'offer_emails'=>count(array_filter($wpdb->outbox,fn($row)=>$row['template_type']==='WAITLIST_OFFER'))];
}
if ($outputs['portal_ajax'] !== $outputs['default_service']) throw new RuntimeException('Portal and service cancellations differ');
foreach (['portal_ajax', 'default_service'] as $mode) {
    $result = $outputs[$mode];
    if ($result['cancelled_status'] !== 'CANCELLED' || $result['waiting_status'] !== 'WAITLIST_OFFERED' || $result['reserved_seats'] !== 1 || $result['candidate_reads'] !== 1 || $result['offer_emails'] !== 1) throw new RuntimeException('Missing waitlist offer: ' . $mode);
}
if ($outputs['event_cancel']['waiting_status'] !== 'WAITLISTED' || $outputs['event_cancel']['reserved_seats'] !== 0 || $outputs['event_cancel']['candidate_reads'] !== 0 || $outputs['event_cancel']['offer_emails'] !== 0) throw new RuntimeException('Explicit promotion suppression ignored');
echo "PASS: portal cancellation offers freed capacity; explicit event-cancellation suppression remains available.\n";
