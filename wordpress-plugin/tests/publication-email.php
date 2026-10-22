<?php
require __DIR__.'/php-behavior.php';
define('ARRAY_A','ARRAY_A');
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';}
class MI_Portal_Management {static function url($event,$order=''){return 'https://example.invalid/segreteria?mi_portal_view=management&mi_portal_event='.$event.'&mi_order='.$order;}}
class MI_Shortcode {static function url_iscrizione($id){return 'https://example.invalid/evento/'.$id;}}
class WP_User {public $user_email='manager@example.invalid';public $display_name='Gestore';}
function get_bloginfo($key){return 'Segreteria';}
function wp_unslash($value){return $value;}
function get_the_title($id){return 'Pellegrinaggio ad Assisi';}
function get_post_thumbnail_id($id){return 0;}
function get_the_post_thumbnail_url($id,$size){return $GLOBALS['event_cover']??'';}
function wp_get_attachment_image_url($id,$size){return '';}
function get_post_meta($id,$key,$single){return $GLOBALS['email_meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['email_meta'][$id][$key]=$value;}
function get_option($key,$default=null){return $GLOBALS['email_options'][$key]??['mi_modalita_spedizione_email'=>'PROVA','mi_destinatario_prova_email'=>'test@example.invalid','mi_email_segreteria_eventi'=>'secretariat@example.invalid'][$key]??$default;}
function get_post_status($id){return $GLOBALS['event_status']??'publish';}
function wp_is_post_revision($id){return false;}
function wp_is_post_autosave($id){return false;}
function wp_json_encode($v){return json_encode($v);}
function current_time($v,$utc=false){return gmdate('Y-m-d H:i:s');}
function wp_next_scheduled($key){return false;}
function wp_schedule_single_event(...$args){$GLOBALS['scheduled']=true;$GLOBALS['scheduled_events'][]=$args;}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
$GLOBALS['email_meta']=[42=>['_mi_activity_id'=>9,'_mi_event_starts_at'=>'2026-10-12T09:30','_mi_event_location'=>'Assisi'],9=>['_mi_email_style'=>['contact_email'=>'group@example.invalid','logo_url'=>'https://example.invalid/logo.png','logo_enabled'=>true,'banner_url'=>'https://example.invalid/banner.png']]];
$snapshot=MI_Modello_Email::crea_istantanea_pubblicazione_evento(42);$html=MI_Modello_Email::componi_html($snapshot);
expect($snapshot['oggetto']==='Congratulazioni! Il tuo evento è stato pubblicato.','subject');
expect(strpos($html,'logo.png')<strpos($html,'<h1')&&strpos($html,'banner.png')>strpos($html,'</h1>'),'logo e banner ordine');
expect(str_contains($html,'Assisi')&&str_contains($html,'09:30')&&str_contains($html,'Data di inizio'),'data e luogo');
expect($snapshot['publication_assets']['banner']==='https://example.invalid/banner.png','banner ereditato');
$GLOBALS['event_cover']='https://example.invalid/event.png';expect(MI_Modello_Email::crea_istantanea_pubblicazione_evento(42)['publication_assets']['banner']===$GLOBALS['event_cover'],'banner proprio');
$values=['{{evento.titolo}}'=>'Assisi','{{ordine.codice}}'=>'FAMILY','{{ordine.partecipanti}}'=>'3','{{ordine.riepilogo}}'=>'3 — Quota di partecipazione','{{sottoscrittore.nome_completo}}'=>'Famiglia Parente'];
$secretariat=MI_Modello_Email::crea_istantanea_nuova_iscrizione_segreteria(42,$values,1);$secretariat_html=MI_Modello_Email::componi_html($secretariat);
expect(str_contains($secretariat_html,'<strong>Partecipanti:</strong> 3')&&!str_contains($secretariat_html,'Quota di partecipazione'),'conteggio semplice');
expect(str_contains($secretariat_html,'Apri prenotazione')&&str_contains($secretariat_html,'mi_order=FAMILY'),'link prenotazione');
expect(str_contains($secretariat_html,'Apri elenco iscritti')&&str_contains($secretariat_html,esc_url(MI_Portal_Management::url(42))),'link elenco evento senza prenotazione selezionata');
expect(!str_contains($secretariat_html,'banner.png'),'altre email istituzionali invariate');
class EmailTestDB {
 public $prefix='wp_';public $db;public $last_error='';public $fail=false;
 function __construct(){mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$this->db=new mysqli('127.0.0.1','root','local-ledger-test-only','mi_ledger_test',33317);}
 function prepare($sql,...$args){$i=0;return preg_replace_callback('/%[ds]/',function($m)use(&$i,$args){$value=$args[$i++];return $m[0]==='%d'?(string)(int)$value:"'".$this->db->real_escape_string($value)."'";},$sql);}
 function query($sql){if($this->fail){$this->fail=false;return false;}$this->db->query($sql);return $this->db->affected_rows;}
 function get_results($sql,$mode){return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);}
 function get_var($sql){return $this->db->query($sql)->fetch_row()[0]??null;}
 function update($table,$data,$where,...$formats){$values=[];foreach($data as $key=>$value)$values[]=$key.'='.($value===null?'NULL':"'".$this->db->real_escape_string($value)."'");return $this->query('UPDATE '.$table.' SET '.implode(',',$values).' WHERE id='.(int)$where['id']);}
}
$wpdb=new EmailTestDB();$schema=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php');preg_match('/CREATE TABLE \{\$outbox\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);$wpdb->query('CREATE TEMPORARY TABLE wp_mi_email_outbox ('.$m[1].') ENGINE=InnoDB');
$post=(object)['ID'=>42,'post_status'=>'publish','post_type'=>'mi_event'];
MI_Spedizione_Email::notifica_pubblicazione(42,$post);expect($wpdb->db->query('SELECT COUNT(*) n FROM wp_mi_email_outbox')->fetch_assoc()['n']==0,'vecchio evento notificato');
MI_Spedizione_Email::rileva_pubblicazione('publish','draft',$post);MI_Spedizione_Email::notifica_pubblicazione(42,$post);MI_Spedizione_Email::notifica_pubblicazione(42,$post);
$rows=$wpdb->db->query('SELECT * FROM wp_mi_email_outbox')->fetch_all(MYSQLI_ASSOC);expect(count($rows)===2&&$rows[0]['recipient']==='secretariat@example.invalid'&&$rows[1]['recipient']==='group@example.invalid'&&$rows[0]['status']==='TEST_PENDING','gruppo e segreteria, test e deduplicazione');
$again=MI_Spedizione_Email::accoda_notifiche_attivazione_evento(42,null,'https://docs.google.com/spreadsheets/d/demo','group@example.invalid');expect($again['count']===0,'attivazione duplica pubblicazione');
unset($GLOBALS['email_meta'][9]['_mi_email_style']['contact_email']);expect(MI_Spedizione_Email::destinatario_evento(42)==='secretariat@example.invalid','fallback segreteria');
$interrupted=(object)['ID'=>43,'post_status'=>'publish','post_type'=>'mi_event'];
MI_Spedizione_Email::rileva_pubblicazione('publish','draft',$interrupted);
expect(end($GLOBALS['scheduled_events'])[1]==='mi_riprova_notifica_pubblicazione','fallback persisted before save hooks');
$wpdb->fail=true;MI_Spedizione_Email::riprova_pubblicazione(43);
expect(!empty($GLOBALS['email_meta'][43]['_mi_publication_notification_error'])&&!empty($GLOBALS['email_meta'][43]['_mi_publication_notification_pending']),'failure retained for retry');
MI_Spedizione_Email::riprova_pubblicazione(43,2);
expect($GLOBALS['email_meta'][43]['_mi_publication_notification_pending']===''&&$GLOBALS['email_meta'][43]['_mi_publication_notification_error']==='','interrupted publication recovered');
MI_Spedizione_Email::riprova_pubblicazione(43,3);
expect((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_email_outbox')===3,'recovery does not duplicate');
$GLOBALS['email_meta'][44]['_mi_publication_notification_pending']='1';$GLOBALS['event_status']='draft';
MI_Spedizione_Email::riprova_pubblicazione(44);
expect((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_email_outbox')===3,'cancelled publication not recovered');
unset($GLOBALS['event_status']);
$wpdb->query("DELETE FROM wp_mi_email_outbox WHERE origin_key='".hash('sha256','event-published|43|secretariat@example.invalid')."'");
file_put_contents(__DIR__.'/../../.tmp/publication-email.html',$html);file_put_contents(__DIR__.'/../../.tmp/secretariat-email.html',$secretariat_html);
echo "PASS: pubblicazione, grafica ereditata/propria, destinatario gruppo/fallback, coda test, deduplicazione, partecipanti e link personale. Nessuna email spedita.\n";
