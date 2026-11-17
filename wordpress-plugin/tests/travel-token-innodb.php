<?php
// Riutilizza esclusivamente mi_deletion_test su localhost:33317.
require __DIR__.'/event-deletion-innodb.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-travel.php';
class TokenDatabase extends DeletionDatabase {
    function update($table,$data,$where) {
        if (!isset($where['participant_id'])) return parent::update($table,$data,$where);
        $set=[];foreach($data as $key=>$value)$set[]=$this->prepare("`$key`=%s",(string)$value);
        return $this->query('UPDATE '.$table.' SET '.implode(',',$set).' WHERE participant_id='.(int)$where['participant_id']);
    }
}
class MI_Portal_Management {static $allowed=true;static function allowed(){return self::$allowed;}}
class MI_Management_Service {static function lock_room_event($id){global $wpdb;$wpdb->get_var('SELECT event_id FROM wp_mi_management_state WHERE event_id='.(int)$id.' FOR UPDATE');}}
class MI_Registration_Service {
    static function append_registration_event($id,$type,$from,$to,$actor,$data){global $wpdb;return $wpdb->insert('wp_mi_registration_events',['registration_id'=>$id,'event_type'=>$type,'actor_label'=>$actor,'detail_json'=>json_encode($data),'created_at'=>gmdate('Y-m-d H:i:s')])!==false;}
    static function mark_workspace_changed_locked($id){global $wpdb;if(false===$wpdb->query('UPDATE wp_mi_registrations SET workspace_revision=workspace_revision+1 WHERE id='.(int)$id))throw new RuntimeException('Revision failed');}
}
function home_url($path=''){return 'https://example.invalid'.$path;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function wp_salt($kind){return 'synthetic-token-test-only';}
function esc_url($v){return htmlspecialchars($v,ENT_QUOTES);}
function token_from_url($url){parse_str(parse_url($url,PHP_URL_QUERY),$args);return $args['travel_token'];}
$wpdb=new TokenDatabase();$wpdb->db->select_db('mi_deletion_test');
$wpdb->query("INSERT INTO wp_mi_participants (id,registration_id,first_name,last_name,extra_json) VALUES (44,43,'Seconda','Persona','{}')");
$wpdb->query("INSERT INTO wp_mi_travel (participant_id,registration_id,event_id,mode,created_at,legacy_token_allowed) VALUES (44,43,43,'OWN',NOW(),0)");
$valid=new ReflectionMethod(MI_Travel::class,'valid_token');
$options['mi_travel_legacy_tokens_until']=time()+86400;
$legacy=hash_hmac('sha256','mi-travel|43',wp_salt('auth'));
check($valid->invoke(null,43,$legacy),'Existing link not accepted during transition');
$new=token_from_url(MI_Travel::url(43));$other=token_from_url(MI_Travel::url(44));
check(preg_match('/^[a-f0-9]{64}$/D',$new)&&$new!==$legacy&&$other!==$new,'Random participant token missing');
check(token_from_url(MI_Travel::url(43))===$new,'Ordinary reads rotate links');
check($valid->invoke(null,43,$new)&&!$valid->invoke(null,44,$new),'Token accepted for wrong participant');
check(!$valid->invoke(null,44,hash_hmac('sha256','mi-travel|44',wp_salt('auth'))),'New participant accepts legacy HMAC');
$options['mi_travel_legacy_tokens_until']=time()-1;
check(!$valid->invoke(null,43,$legacy)&&$valid->invoke(null,43,$new),'Legacy transition does not expire independently');
$options['mi_travel_legacy_tokens_until']=time()+86400;
MI_Portal_Management::$allowed=false;check(is_wp_error(MI_Travel::replace_link(43)),'Anonymous revocation accepted');MI_Portal_Management::$allowed=true;
$before=MI_Event_Deletion::preview(43)['fingerprint'];
$replacement=MI_Travel::replace_link(43);check(!is_wp_error($replacement),'Replacement failed');
$replaced=token_from_url($replacement);
check($replaced!==$new&&$valid->invoke(null,43,$replaced)&&!$valid->invoke(null,43,$new)&&!$valid->invoke(null,43,$legacy),'Revocation leaves old or legacy token valid');
check($valid->invoke(null,44,$other),'Other participant link revoked');
$notice=$wpdb->get_row("SELECT * FROM wp_mi_email_outbox WHERE template_type='TRAVEL_UPDATE' ORDER BY id DESC LIMIT 1",ARRAY_A);
check(str_contains($notice['payload_json'],$replaced)&&!str_contains($notice['payload_json'],$new)&&$notice['recipient']==='person@example.invalid','Replacement not sent to registered referent');
$audit=$wpdb->get_row("SELECT * FROM wp_mi_registration_events WHERE event_type='TRAVEL_LINK_REPLACED' ORDER BY id DESC LIMIT 1",ARRAY_A);
check($audit&&!str_contains($audit['detail_json'],$replaced),'Token leaked in audit');
// Nessun nuovo valore per i segreti modifica da solo la conferma dell'eliminazione.
$preview=MI_Event_Deletion::preview(43)['fingerprint'];$wpdb->query("UPDATE wp_mi_travel SET access_token=REPEAT('f',64),legacy_token_allowed=0 WHERE participant_id=44");
check(MI_Event_Deletion::preview(43)['fingerprint']===$preview,'Technical token rotation invalidates deletion');
$wpdb->fail='INSERT INTO wp_mi_email_outbox';
check(is_wp_error(MI_Travel::replace_link(43))&&$valid->invoke(null,43,$replaced),'Failed replacement did not roll back old link');
echo "PASS: random stable links, participant isolation, legacy grace/expiry, access control, individual revocation, registered recipient, audit redaction and rollback.\n";

// Contatori aggregati reali: prenotazione 43 attiva, evento 42 isolato.
$wpdb->query("UPDATE wp_mi_travel SET mode='DRIVER',seats=5 WHERE participant_id=43");
$wpdb->query("UPDATE wp_mi_travel SET mode='NEED',state='ASSIGNED',driver_id=43 WHERE participant_id=44");
foreach ([45=>['OFFERED',3600],46=>['OFFERED',-3600],47=>['WAITING',0],48=>['DECLINED',0],49=>['ASSIGNED',0]] as $id=>[$state,$seconds]) {
    check(false!==$wpdb->query("INSERT INTO wp_mi_participants (id,registration_id,first_name,last_name,extra_json,status) VALUES ($id,43,'Persona','Sintetica','{}','".($id===49?'CANCELLED':'ACTIVE')."')"),$wpdb->last_error);
    $expiry=gmdate('Y-m-d H:i:s',time()+$seconds);
    check(false!==$wpdb->query("INSERT INTO wp_mi_travel (participant_id,registration_id,event_id,mode,state,driver_id,offer_expires_at,created_at) VALUES ($id,43,43,'NEED','$state',43,'$expiry',UTC_TIMESTAMP())"),$wpdb->last_error);
}
$counts=MI_Travel::management_overview(43);
check($counts===['waiting'=>3,'assigned'=>1,'reserved'=>1,'available'=>3], 'Conteggi passaggi errati: '.json_encode($counts));
$wpdb->query("UPDATE wp_mi_travel SET seats=1 WHERE participant_id=43");
check(MI_Travel::management_overview(43)['available']===0,'Disponibilità negativa');
$wpdb->query("UPDATE wp_mi_registrations SET capacity_released_at=UTC_TIMESTAMP() WHERE id=43");
check(array_sum(MI_Travel::management_overview(43))===0,'Prenotazione rilasciata inclusa');
check(array_sum(MI_Travel::management_overview(999))===0,'Evento vuoto non azzerato');
echo "PASS: contatori SQL passaggi, proposte valide/scadute, assegnazioni, annullati, capienza rilasciata, eventi isolati e disponibilità non negativa.\n";
