<?php
define('ABSPATH', __DIR__);
class MI_Event_Post_Type { const EVENT_TYPE='mi_event'; }
class WP_Error {}
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);}
function current_time($format,...$args){return $format==='timestamp'?strtotime('2026-11-09 12:00:00 UTC'):'2026-11-09 12:00:00';}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function get_posts($args){return [1,2,3,4,5];}
$meta=[];$options=[];$scheduled=[];
foreach(range(1,5) as $id)$meta[$id]=['_mi_operational_sheet_id'=>'sheet'.$id,'_mi_event_archived_at'=>'yes'];
function get_post_meta($id,$key,$single){global $meta;return $meta[$id][$key]??'';}
function update_post_meta($id,$key,$value){global $meta;$meta[$id][$key]=$value;}
function delete_post_meta($id,$key){global $meta;unset($meta[$id][$key]);}
function get_option($key,$default=false){global $options;return $options[$key]??$default;}
function update_option($key,$value,...$args){global $options;$options[$key]=$value;}
function delete_option($key){global $options;unset($options[$key]);}
function wp_next_scheduled($key){return false;}
function wp_schedule_single_event($time,$key){global $scheduled;$scheduled[]=$key;}
class MI_Workspace_Client {
 static $calls=[],$verification=null;
 static function request($action,$payload){
  self::$calls[]=[$action,$payload];
  if($action==='VERIFICA_FOGLI_EVENTO')return self::$verification??['stati'=>array_map(fn($sheet)=>['id_evento'=>$sheet['id_evento'],'esiste'=>true],$payload['fogli'])];
  return ['ok'=>true,'risultati'=>array_map(fn($sheet)=>['id_evento'=>$sheet['id_evento'],'ok'=>$sheet['id_evento']!=='2'],$payload['fogli_passati'])];
 }
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
function verify($ok,$message){if(!$ok)throw new Exception($message);}
MI_Portal::archive_completed_event_sheets();
verify(isset($meta[1]['_mi_sheet_archived_at'])&&!isset($meta[2]['_mi_sheet_archived_at']),'Certificato uno spostamento fallito');
verify($options['mi_sheet_organization_cursor']===3&&count($scheduled)===1,'Lotto non limitato o continuazione assente');
MI_Portal::archive_completed_event_sheets();
verify(isset($meta[5]['_mi_sheet_archived_at'])&&!isset($options['mi_sheet_organization_cursor']),'Ultimo lotto non completato');
MI_Workspace_Client::$calls=[];
MI_Portal::archive_completed_event_sheets();
$moves=array_values(array_filter(MI_Workspace_Client::$calls,fn($c)=>$c[0]==='ORGANIZZA_FOGLI_EVENTO'));
verify(count($moves)===1&&$moves[0][1]['fogli_passati']===[['id_evento'=>'2','id_foglio'=>'sheet2']]&&$moves[0][1]['direct_projection']===true,'Ripetuti spostamenti già confermati o perso retry');
echo "PASS: lotti, continuazione, errori per evento, retry e spostamenti invariati.\n";
unset($options['mi_sheet_organization_cursor']);
MI_Workspace_Client::$verification=['stati'=>[['id_evento'=>'1','ok'=>false,'error'=>'SHEET_UNAVAILABLE'],['id_evento'=>'2','ok'=>true,'esiste'=>false]]];
MI_Portal::archive_completed_event_sheets();
verify(!isset($meta[1]['_mi_sheet_missing'])&&isset($meta[1]['_mi_sheet_unavailable']),'Indisponibilità scambiata per assenza');
verify(isset($meta[2]['_mi_sheet_missing']),'Assenza accertata ignorata');
unset($options['mi_sheet_organization_cursor']);MI_Workspace_Client::$verification=null;
MI_Portal::archive_completed_event_sheets();
verify(!isset($meta[1]['_mi_sheet_unavailable'])&&!isset($meta[2]['_mi_sheet_missing']),'Ripristino disponibilità non registrato');
echo "PASS: indisponibilità distinta dall’assenza e ripristino dello stato.\n";
unset($options['mi_sheet_organization_cursor'],$meta[1]['_mi_event_archived_at']);
$meta[1]['_mi_event_starts_at']='2026-11-08T08:00';
$meta[1]['_mi_event_ends_at']='2026-11-11T18:00';
MI_Workspace_Client::$calls=[];
MI_Portal::archive_completed_event_sheets();
$move=end(MI_Workspace_Client::$calls);
verify($move[0]==='ORGANIZZA_FOGLI_EVENTO' && $move[1]['fogli_correnti']===[['id_evento'=>'1','id_foglio'=>'sheet1']], 'Evento in corso lasciato in archivio');
