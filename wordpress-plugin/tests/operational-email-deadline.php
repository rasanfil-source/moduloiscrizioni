<?php
// Diagnostica locale: WordPress e database simulati; nessun invio email.
$tests = __DIR__;
$base = file_get_contents($tests . '/php-behavior.php');
$base = substr($base, 5, strpos($base, '$event = array(') - 5);
$base = str_replace("require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';", '', $base);
$base = str_replace('__DIR__', var_export($tests, true), $base);
eval($base);
$email = file_get_contents($tests . '/email-newlines.php');
$email = substr($email, strpos($email, '// Riproduce'), strpos($email, '$text = ') - strpos($email, '// Riproduce'));
eval(str_replace('__DIR__', var_export($tests, true), $email));
define('ARRAY_A', 'ARRAY_A'); define('DAY_IN_SECONDS', 86400);
function get_post_status($id) { return 'publish'; }
function get_transient($key) { return false; }
function set_transient(...$args) {}
function current_time(...$args) { return '2026-10-05 10:00:00'; }
class MI_Registration_Service {
    static function public_event(...$args) { return ['title'=>'Evento sintetico', 'payment_deadline_at'=>'2026-10-01T18:00']; }
}
class MI_Payment_Ledger {
    static function positions($rows) { return array_fill_keys(array_column($rows, 'id'), ['effective_paid'=>0,'effective_balance'=>10000,'managed'=>true,'effective_total'=>10000,'individual_known'=>false,'individual_people_count'=>1,'individual_active_count'=>1]); }
}
class MI_Portal {
    static function status_url(...$args) { return 'https://example.invalid/status'; }
    static function balance_url(...$args) { return 'https://example.invalid/balance'; }
}
class AuditReminderDatabase {
    public $prefix='wp_', $last_error='', $payloads=[];
    function prepare($sql,...$args) {
        if (str_starts_with($sql, 'INSERT')) $this->payloads[$args[0]]=json_decode($args[4], true);
        return $sql;
    }
    function get_var($sql) { return 1; }
    function get_results($sql,...$args) {
        expect(str_contains($sql, 'payment_deadline_at'), 'scadenza non caricata dal database');
        $rows=[];
        foreach (['2026-10-07 16:00:00','2026-12-07 17:00:00',null,'','2026-02-30 10:00:00'] as $index=>$deadline) {
            $rows[]=['id'=>$index+1,'order_code'=>'AUDIT'.($index+1),'status'=>'PENDING_PAYMENT','economic_mode'=>'FULL_PAYMENT','buyer_first_name'=>'Persona','buyer_last_name'=>'Test','buyer_email'=>'test@example.invalid','total_qty'=>1,'total_cents'=>10000,'initial_due_cents'=>10000,'balance_cents'=>10000,'payment_methods_json'=>'["BANK_TRANSFER"]','payment_deadline_at'=>$deadline];
        }
        return $rows;
    }
    function query($sql) { return 1; }
}
foreach (['BALANCE_REMINDER','DEPOSIT_REMINDER'] as $template) {
    $wpdb = new AuditReminderDatabase();
    $recipients=array_map(static fn($id)=>['order_code'=>'AUDIT'.$id], range(1,5));
    $result = MI_Spedizione_Email::accoda_comunicazione_operativa(['event_id'=>42,'communication_id'=>'audit-deadline','template_type'=>$template,'recipients'=>$recipients]);
    expect(!is_wp_error($result) && $result['count']===5, 'preparazione promemoria non riuscita');
    foreach ([1=>'07/10/2026, 18:00',2=>'07/12/2026, 18:00',3=>'Contatta l’organizzazione',4=>'Contatta l’organizzazione',5=>'Contatta l’organizzazione'] as $id=>$expected) {
        foreach (['testo','html'] as $format) {
            $body=$wpdb->payloads[$id]['email_preview'][$format];
            expect(str_contains($body, $expected), "$template $format: scadenza errata per $id");
            expect(!str_contains($body, '01/10/2026'), 'scadenza evento riutilizzata');
            if ($id!==1) expect(!str_contains($body, '07/10/2026'), 'scadenza propagata da altra prenotazione');
        }
    }
}
echo "PASS: operational email uses each booking deadline, UTC conversion in summer/winter and explicit missing/invalid fallback.\n";
