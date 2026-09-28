<?php
define('ABSPATH', __DIR__);
function get_option($key,$default=false){return ['mi_modalita_spedizione_email'=>'PROVA','mi_destinatario_prova_email'=>'test@example.invalid'][$key]??$default;}
function sanitize_email($v){return $v;}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function wp_next_scheduled($hook){return false;}
function wp_schedule_single_event($when,$hook){$GLOBALS['scheduled'][]=[$when,$hook];return true;}
function wp_doing_cron(){return false;}
function add_action($hook,$callback){$GLOBALS['actions'][$hook][]=$callback;}
function spawn_cron(){$GLOBALS['spawned']++;}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
$scheduled=[];$actions=[];$spawned=0;
// No database or transport is available: any synchronous dispatch fails this test.
MI_Spedizione_Email::tenta_spedizione_immediata();
MI_Spedizione_Email::tenta_spedizione_immediata();
if(count($scheduled)!==1||$scheduled[0][0]>time()||$scheduled[0][1]!=='mi_spedisci_email_in_coda'||$spawned!==0)throw new Exception('Dispatch must be scheduled once without blocking');
foreach($actions['shutdown'] as $callback)$callback();
if($spawned!==1)throw new Exception('Worker wakeup missing');
echo "Email background scheduling passed.\n";
