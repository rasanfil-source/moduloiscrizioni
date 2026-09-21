<?php
define('ABSPATH',__DIR__);
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-attendance-report.php';
function check_report($ok,$message){if(!$ok)throw new RuntimeException($message);}
$people=[];foreach([1=>10,2=>11,3=>11,4=>12] as $id=>$event)$people[]=['id'=>$id,'registration_id'=>$id,'event_id'=>$event,'order_code'=>'T'.$id,'first_name'=>'Mario','last_name'=>'Rossi'];
$events=[10=>['date'=>'2026-01-10','title'=>'Uno'],11=>['date'=>'2026-02-10','title'=>'Due'],12=>['date'=>'2025-01-10','title'=>'Vecchio']];
$audit=[];foreach([1,2,3,4] as $id)$audit[]=['registration_id'=>$id,'event_type'=>'MANAGEMENT_attendance','detail_json'=>json_encode(['participant_id'=>$id,'attendance'=>'PRESENT'])];
$report=MI_Attendance_Report::aggregate($people,$audit,$events,2026,2);check_report(count($report['items'])===0,'Omonimi uniti automaticamente');
$audit[]=['registration_id'=>2,'event_type'=>'MANAGEMENT_identity_link','detail_json'=>json_encode(['participant_id'=>2,'target_id'=>1])];
$audit[]=['registration_id'=>3,'event_type'=>'MANAGEMENT_identity_link','detail_json'=>json_encode(['participant_id'=>3,'target_id'=>2])];
$report=MI_Attendance_Report::aggregate($people,$audit,$events,2026,2);check_report(count($report['items'])===1&&$report['items'][0]['count']===2,'Collegamenti espliciti o deduplica evento errati');
$audit[]=['registration_id'=>2,'event_type'=>'MANAGEMENT_identity_link','detail_json'=>json_encode(['participant_id'=>2,'target_id'=>0])];
check_report(count(MI_Attendance_Report::aggregate($people,$audit,$events,2026,2)['items'])===0,'Rimozione collegamento non rispettata');
$audit[]=['registration_id'=>1,'event_type'=>'MANAGEMENT_attendance','detail_json'=>json_encode(['participant_id'=>1,'attendance'=>'ABSENT'])];
check_report(count(MI_Attendance_Report::aggregate($people,$audit,$events,2026,1)['items'])===1,'Ultima rilevazione o anno ignorati');
$audit[]=['registration_id'=>1,'event_type'=>'MANAGEMENT_identity_link','detail_json'=>json_encode(['participant_id'=>2,'target_id'=>1])];
check_report(count(MI_Attendance_Report::aggregate($people,$audit,$events,2026,2)['items'])===0,'Audit riferito a un’altra prenotazione accettato');
echo "Rapporto annuale: omonimi separati, collegamenti confermati, rimozioni, anno, presenza effettiva ed eventi unici verificati.\n";
$mobile_people=$people;
$mobile_people[0]['extra_json']=json_encode(['participant_phone'=>'+39 312 345 6789']);
$mobile_people[1]['extra_json']=json_encode(['participant_phone'=>'0039 3123456789']);
$mobile_people[2]['extra_json']=json_encode(['participant_phone'=>'3123456789']);
$mobile_audit=[];foreach([1,2,3] as $id)$mobile_audit[]=['registration_id'=>$id,'event_type'=>'MANAGEMENT_attendance','detail_json'=>json_encode(['participant_id'=>$id,'attendance'=>'PRESENT'])];
$mobile_report=MI_Attendance_Report::aggregate($mobile_people,$mobile_audit,$events,2026,2);
check_report(count($mobile_report['items'])===1&&$mobile_report['items'][0]['count']===2,'Cellulare normalizzato o deduplica evento errati');
$mobile_people[1]['extra_json']=json_encode(['participant_phone'=>'3123456780']);$mobile_people[2]['extra_json']='{}';
check_report(count(MI_Attendance_Report::aggregate($mobile_people,$mobile_audit,$events,2026,2)['items'])===0,'Cellulari diversi o assenti uniti');
define('ARRAY_A','ARRAY_A');
class WP_Error { function __construct(public $code,public $message){} }
class MI_Portal_Management {static function allowed(){return true;}}
class MI_Access {static function can_access_activity($id){return $id===5;}static function can_access_event($id){return $id===10;}}
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';}
function get_posts($args){return [(object)['ID'=>10,'post_title'=>'Autorizzato'],(object)['ID'=>11,'post_title'=>'Escluso']];}
function get_post_meta($id,$key,$single){return $key==='_mi_activity_id'?5:'2026-01-01';}
class ReportDatabase {
 public $prefix='wp_',$last_error='', $queries=[];
 function prepare($sql,...$args){return $sql;}
 function get_results($sql,$format){$this->queries[]=$sql;return [];}
 function get_row($sql,$format){return ['id'=>2,'event_id'=>11];}
}
$wpdb=new ReportDatabase();
check_report(MI_Attendance_Report::read(6,2026,2) instanceof WP_Error,'Gruppo estraneo accessibile');check_report(count($wpdb->queries)===0,'Lettura eseguita prima della verifica ambito');
MI_Attendance_Report::read(5,2026,2);foreach($wpdb->queries as $query)check_report(str_contains($query,'IN (10)'),'Report include eventi non autorizzati');
try{MI_Attendance_Report::target(10,'ALTRO',1);throw new LogicException('Target fuori ambito accettato');}catch(InvalidArgumentException $expected){}
echo "Rapporto annuale: gruppo, eventi e collegamenti rispettano gli ambiti autorizzati.\n";

$cycle_people = $people;
foreach ( $cycle_people as &$p ) $p['extra_json'] = json_encode(['participant_phone'=>'3123456789']);
unset($p);
$cycle_events = [10=>['date'=>'2025-09-01','title'=>'Inizio'],11=>['date'=>'2026-06-30','title'=>'Fine'],12=>['date'=>'2026-07-01','title'=>'Escluso']];
$cycle_audit=[];foreach([1,2,3,4] as $id)$cycle_audit[]=['registration_id'=>$id,'event_type'=>'MANAGEMENT_attendance','detail_json'=>json_encode(['participant_id'=>$id,'attendance'=>'PRESENT'])];
$cycle=MI_Attendance_Report::aggregate($cycle_people,$cycle_audit,$cycle_events,2026,2,'2025-09','2026-06');
check_report(count($cycle['items'])===1&&$cycle['items'][0]['count']===2,'Ciclo a cavallo di due anni o estremi inclusivi errati');
check_report($cycle['from_month']==='2025-09'&&$cycle['to_month']==='2026-06','Periodo non riportato');
foreach([['2026-07','2025-09'],['2026-00','2026-12'],['2026-01',''],['1999-12','2026-01']] as $range){
 try{MI_Attendance_Report::period(2026,...$range);throw new LogicException('Periodo invalido accettato');}catch(InvalidArgumentException $expected){}
}
echo "Presenze: ciclo settembre-giugno, estremi inclusivi, deduplica e periodi invalidi verificati.\n";

// Descending links form a long chain before any attendance lookup compresses it.
$chain_people=[];$chain_audit=[];
for($id=800;$id>=1;$id--){
 $chain_people[]=['id'=>$id,'registration_id'=>$id,'event_id'=>$id%2?10:11,'order_code'=>'CHAIN'.$id,'first_name'=>'Test','last_name'=>'Persona'];
 $chain_audit[]=['registration_id'=>$id,'event_type'=>'MANAGEMENT_attendance','detail_json'=>json_encode(['participant_id'=>$id,'attendance'=>'PRESENT'])];
 if($id>1)$chain_audit[]=['registration_id'=>$id,'event_type'=>'MANAGEMENT_identity_link','detail_json'=>json_encode(['participant_id'=>$id,'target_id'=>$id-1])];
}
$chain=MI_Attendance_Report::aggregate($chain_people,$chain_audit,$events,2026,2);
check_report(count($chain['items'])===1 && $chain['items'][0]['identity']===1 && $chain['items'][0]['count']===2 && count($chain['items'][0]['records'])===800,'Long identity chain changed grouping or attendance count');
