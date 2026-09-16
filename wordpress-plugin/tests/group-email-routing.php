<?php
require __DIR__ . '/publication-email.php';
$wpdb->query('CREATE TEMPORARY TABLE wp_mi_registrations (event_id BIGINT, buyer_email VARCHAR(254), status VARCHAR(32))');
$GLOBALS['email_meta'][9]['_mi_email_style']['contact_email']='group@example.invalid';
$GLOBALS['email_meta'][10]['_mi_email_style']['contact_email']='SECRETARIAT@example.invalid';
$GLOBALS['email_meta'][11]['_mi_email_style']['contact_email']='invalid';
foreach ([51=>9,52=>0,53=>10,54=>11] as $eventId=>$groupId) {
 $GLOBALS['email_meta'][$eventId]['_mi_activity_id']=$groupId;
 $GLOBALS['email_meta'][$eventId]['_mi_email_style']['contact_email']='ignored-event@example.invalid';
 $expected=$eventId===51?'group@example.invalid':'secretariat@example.invalid';
 expect(strtolower(MI_Spedizione_Email::destinatario_evento($eventId))===$expected,'exclusive organizer or fallback');
 $style=MI_Modello_Email::stile_risolto($eventId);
 expect(strtolower($style['contact_email'])===$expected,'public contact obeys group, ignores event override');
 $snapshot=MI_Modello_Email::crea_istantanea_annullamento_iscrizione_iscritto($eventId,'Persona','ORD');
 expect(strtolower($snapshot['identita_email']['indirizzo_risposte'])===$expected,'participant cancellation Reply-To');
 $new=MI_Modello_Email::crea_istantanea_nuova_iscrizione_segreteria($eventId,[],1);
 expect(strtolower($new['identita_email']['indirizzo_risposte'])===$expected,'registration Reply-To');
 $n=$eventId===51?2:1;
 expect(MI_Spedizione_Email::accoda_notifiche_attivazione_evento($eventId,new WP_User(),'','')['count']===$n,'lifecycle publication recipients');
 expect(MI_Spedizione_Email::accoda_avviso_annullamento_segreteria($eventId,'Motivo')['count']===$n,'lifecycle cancellation recipients');
 MI_Spedizione_Email::accoda_avviso_eliminazione($eventId);
 MI_Spedizione_Email::accoda_avviso_eliminazione($eventId);
 expect(MI_Spedizione_Email::accoda_avviso_annullamento_segreteria($eventId,'Motivo')['count']===0,'cancellation deduplication');
 $rows=$wpdb->get_results("SELECT recipient,template_type FROM wp_mi_email_outbox WHERE JSON_EXTRACT(payload_json,'$.event_id')=".$eventId,ARRAY_A);
 expect(count($rows)===$n*3,'all lifecycle notices deduplicated');
 foreach($rows as $row) expect(in_array(strtolower($row['recipient']),array_unique([$expected,'secretariat@example.invalid']),true),'no unrelated manager receives notices');
}
echo "PASS: exclusive group/fallback, contact and Reply-To, publication/cancellation/deletion pairs, same-address deduplication, invalid email fallback. No real email sent.\n";
