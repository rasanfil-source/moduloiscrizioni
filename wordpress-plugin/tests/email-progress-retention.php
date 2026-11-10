<?php
// Dati sintetici e database simulato: nessuna email o modifica a WordPress.
require __DIR__ . '/email-jobs-audit.php';
class MI_Access { public static $allowed = true; static function can_access_event($id) { return self::$allowed; } }
function current_user_can($cap) { return false; }
function sanitize_text_field($value) { return (string) $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url($value) { return (string) $value; }
function wp_create_nonce($action) { return 'synthetic'; }
class ProgressRetentionDatabase extends AuditJobsDatabase {
    public $prefix = 'wp_', $last_error = '', $rows = [];
    public function get_results($args, $format) { return $this->rows; }
}
function assert_progress($ok, $message) { if (!$ok) throw new RuntimeException($message); }
reset_jobs(); MI_Spedizione_Email::$mode = 'OPERATIVO'; MI_Spedizione_Email::$ready = true;
$wpdb = new ProgressRetentionDatabase();
$id = hash('sha256', 'retention'); $key = 'mi_email_job_completed_' . $id;
$job = ['owner'=>7,'event_id'=>42,'test_only'=>false,'state'=>'prepared','mode'=>'OPERATIVO','offset'=>127,'total'=>127,'origin_keys'=>['synthetic'],'error'=>'','created_at'=>time()-86400];
update_option('mi_email_job_' . $id, $job);
$wpdb->rows = [['status'=>'SENT','total'=>120,'last_error'=>''],['status'=>'FAILED','total'=>7,'last_error'=>'Contenuto email non valido o inattivo.']];
$state = MI_Email_Jobs::status($id);
assert_progress($state['done'] && $state['sent']===120 && $state['failed']===7 && $state['pending']===0, 'Esito storico alterato');
assert_progress($state['dismiss_after']>1795 && $state['dismiss_after']<=1800, 'Scadenza iniziale errata');
update_option($key, time()-600);
$state = MI_Email_Jobs::status($id);
assert_progress($state['dismiss_after']>1195 && $state['dismiss_after']<=1200, 'Un refresh prolunga la scadenza');
update_option($key, time()-1801);
assert_progress(MI_Email_Jobs::status($id)['dismiss_after']===0, 'Esito con errori non scaduto');
$_GET['mi_comm_job'] = $id;
ob_start(); MI_Email_Jobs::render_progress($id); $html = ob_get_clean();
assert_progress($html==='', 'URL esplicito ripresenta un esito scaduto');
assert_progress(get_option('mi_email_job_' . $id)===$job && count($wpdb->rows)===2, 'Registro o job cancellati');
$wpdb->rows = [['status'=>'SENT','total'=>120,'last_error'=>''],['status'=>'PENDING','total'=>7,'last_error'=>'']];
$state = MI_Email_Jobs::status($id);
assert_progress(!$state['done'] && $state['dismiss_after']===null && !get_option($key), 'Coda ripresa nascosta');
ob_start(); MI_Email_Jobs::render_progress($id); $html = ob_get_clean();
assert_progress(str_contains($html,'data-mi-email-progress'), 'Avanzamento attivo mancante');
$wpdb->rows = [['status'=>'SENT','total'=>127,'last_error'=>'']];
assert_progress(MI_Email_Jobs::status($id)['dismiss_after']>1795, 'Coda conclusa di nuovo senza nuova scadenza');
$wpdb->last_error = 'synthetic read error';
assert_progress(MI_Email_Jobs::status($id)===false, 'Errore SQL interpretato come conclusione');
$wpdb->last_error = ''; delete_option($key); MI_Access::$allowed=false;
assert_progress(MI_Email_Jobs::status($id)===false && !get_option($key), 'Accesso non autorizzato modifica scadenza');
MI_Access::$allowed=true;
update_option('mi_email_job_' . $id, array_replace($job,['state'=>'pending']));
$wpdb->rows=[];
assert_progress(MI_Email_Jobs::status($id)['dismiss_after']===null, 'Preparazione lunga nascosta');
update_option('mi_email_job_' . $id, array_replace($job,['state'=>'failed','error'=>'Preparazione interrotta']));
assert_progress(MI_Email_Jobs::status($id)['dismiss_after']>1795, 'Errore preparazione senza scadenza');
echo "PASS: progress expires after 30 minutes, refresh is stable, legacy/error/active/reopened jobs and access checks preserve records.\n";
