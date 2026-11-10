<?php
define('ABSPATH',__DIR__); define('ARRAY_A','ARRAY_A');
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php';
function check_index($ok,$message){if(!$ok)throw new RuntimeException($message);}
class IndexAuditDatabase {
    public $last_error='', $columns=['actor_label'], $queries=[], $fail=false;
    function get_results($sql,$format){
        // SHOW INDEX non è obbligato a restituire le righe nell'ordine desiderato.
        return array_reverse(array_map(fn($column,$i)=>['Column_name'=>$column,'Seq_in_index'=>$i+1],$this->columns,array_keys($this->columns)));
    }
    function query($sql){
        $this->queries[]=$sql;
        if($this->fail){$this->last_error='synthetic';return false;}
        $this->columns=['event_type','actor_label'];return 0;
    }
}
$repair=new ReflectionMethod(MI_Activator::class,'repair_audit_index'); $repair->setAccessible(true);
$wpdb=new IndexAuditDatabase();
check_index($repair->invoke(null,'wp_mi_registration_events'),'Indice storico non aggiornato');
check_index(count($wpdb->queries)===1 && str_contains($wpdb->queries[0],'DROP INDEX type_actor, ADD INDEX'),'Sostituzione non atomica');
check_index($repair->invoke(null,'wp_mi_registration_events') && count($wpdb->queries)===1,'Indice corretto ricreato');
$wpdb=new IndexAuditDatabase();$wpdb->columns=[];
check_index($repair->invoke(null,'wp_mi_registration_events') && !str_contains($wpdb->queries[0],'DROP INDEX'),'Indice mancante non creato');
$wpdb=new IndexAuditDatabase();$wpdb->fail=true;
check_index(!$repair->invoke(null,'wp_mi_registration_events') && $wpdb->columns===['actor_label'],'Errore ALTER interpretato come successo');
$wpdb=new IndexAuditDatabase();$wpdb->last_error='read failed';
check_index(!$repair->invoke(null,'wp_mi_registration_events') && !$wpdb->queries,'Errore lettura causa ALTER');
$hooks=[];function wp_unschedule_hook($hook){$GLOBALS['hooks'][]=$hook;}
MI_Activator::deactivate();
check_index(in_array('mi_web_push_registration',$hooks,true) && in_array('mi_riprova_notifica_pubblicazione',$hooks,true),'Hook cron non rimossi');
echo "PASS: indice storico/mancante/corretto, errore DDL e pulizia cron.\n";
