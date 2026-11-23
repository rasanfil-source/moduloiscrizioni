<?php
// Solo dati sintetici: nessun database o invio.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
function absint($value) { return abs((int) $value); }
function wp_timezone() { return new DateTimeZone('Europe/Rome'); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-modello-email.php';
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function check_fast($condition, $message) { if (!$condition) throw new RuntimeException($message); }
foreach ([48=>'48 ore',24=>'24 ore',12=>'12 ore',1=>'1 ora'] as $hours=>$expected) check_fast(MI_Registration_Service::waitlist_deadline_label('18 novembre 2026, ore 14:37',$hours)==='18 Novembre 2026, ore 14:37 ('.$expected.')','Durata email errata');
check_fast(MI_Registration_Service::waitlist_deadline_label('18 novembre 2026, ore 14:37',1.5)==='18 Novembre 2026, ore 14:37 (1,5 ore)','Frazione errata');
$event = ['waitlist_fast'=>true, 'waitlist_offer_hours'=>48, 'event_starts_at'=>'2026-11-25T10:00'];
$start = (new DateTimeImmutable($event['event_starts_at'], wp_timezone()))->getTimestamp();
foreach ([8*86400=>48, 7*86400+1=>48, 7*86400=>24, 3*86400+1=>24, 3*86400=>12, 86400=>12] as $remaining=>$expected) {
    check_fast(MI_Registration_Service::waitlist_offer_hours($event, $start-$remaining)===$expected, 'Soglia errata: '.$remaining);
}
$event['waitlist_offer_hours']=1;
check_fast(MI_Registration_Service::waitlist_offer_hours($event, $start-86400)===0.25, 'Un’ora deve diventare 15 minuti');
$event['waitlist_fast']=false;
check_fast(MI_Registration_Service::waitlist_offer_hours($event, $start-86400)===1, 'Opzione disattivata');
$event['waitlist_fast']=true;
$event['event_starts_at']='data non valida';
check_fast(MI_Registration_Service::waitlist_offer_hours($event, $start-86400)===1, 'Data non valida');
foreach ([[24,'24'],[12,'12'],[0.25,'0,25'],[2.5,'2,5']] as [$hours,$label]) {
    $mail = MI_Modello_Email::avviso_coda_rapida(['html'=>'<p>Data esatta e collegamento</p>', 'testo'=>'Data esatta e collegamento', 'action_url'=>'https://example.invalid'], $hours);
    check_fast(str_contains($mail['html'], 'color:#b91c1c'), 'Colore rosso assente');
    foreach (['html','testo'] as $format) check_fast(str_contains($mail[$format], 'Hai solo '.$label.' ore per poter accettare il posto.') && str_contains($mail[$format], 'Data esatta e collegamento'), 'Avviso o scadenza assenti');
    check_fast($mail['action_url']==='https://example.invalid', 'Collegamento alterato');
}
echo "PASS: coda rapida, soglie esatte, frazioni, disattivazione, data assente e email rossa HTML/testo.\n";
