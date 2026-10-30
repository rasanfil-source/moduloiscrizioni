<?php
// Dati sintetici; il resolver legge date pubblicate diverse dalle bozze correnti.
define('ABSPATH',__DIR__); define('OBJECT','OBJECT');
class WP_Error { public function __construct(public $code,public $message){} public function get_error_message(){return $this->message;} }
class RedirectResult extends RuntimeException { public function __construct(public $url,public $status){parent::__construct($url);} }
class MI_Event_Post_Type { const EVENT_TYPE='mi_event'; const GROUP_TYPE='mi_activity'; }
class MI_Registration_Service { static function public_event($id){return $GLOBALS['published'][$id]??new WP_Error('unavailable','Unavailable');} }
class MI_Shortcode { static function url_iscrizione($id){return home_url('/?mi_iscrizione='.$id);} }
class DB { public $prefix='wp_'; function prepare($query,...$args){return $query;} function get_var($query){return $GLOBALS['lock_ok']?1:0;} }
$wpdb=new DB(); $lock_ok=true; $meta=[]; $options=[]; $posts=[]; $published=[]; $pages=[]; $no_cache=false; $base='https://example.invalid';
function absint($value){return abs((int)$value);} function is_wp_error($value){return $value instanceof WP_Error;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=wp_unslash($value);}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function add_option($key,$value,...$args){if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$value;return true;}
function get_post($id){return $GLOBALS['posts'][$id]??null;}
function get_posts($args){
 $out=[]; foreach($GLOBALS['posts'] as $id=>$post){
  if($post->post_type!==$args['post_type']||$post->post_status!==$args['post_status'])continue;
  foreach($args['meta_query'] as $filter)if((string)get_post_meta($id,$filter['key'])!==(string)$filter['value'])continue 2;
  $out[]=$id;
 } sort($out);return $out;
}
function get_post_types($args){return ['page','post'];}function get_page_by_path($path,...$args){return $GLOBALS['pages'][$path]??null;}
function sanitize_text_field($value){return trim(strip_tags($value));}function sanitize_textarea_field($value){return trim(strip_tags($value));}
function wp_slash($value){return is_array($value)?array_map('wp_slash',$value):addslashes((string)$value);}
function wp_unslash($value){return is_array($value)?array_map('wp_unslash',$value):(is_string($value)?stripslashes($value):$value);}
function current_time($format,...$args){return strtotime('2026-10-29T15:00:00Z');}function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function wp_parse_url($value,$part){return parse_url($value,$part);}function home_url($path=''){return $GLOBALS['base'].$path;}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function esc_html($v){return esc_attr($v);}function esc_textarea($v){return esc_attr($v);}function esc_url($v){return esc_attr($v);}function esc_url_raw($v,...$args){return $v;}
function checked($a,$b=true){echo $a==$b?'checked':'';}function disabled($a,$b=true){echo $a==$b?'disabled':'';}function selected($a,$b){echo (string)$a===(string)$b?'selected':'';}
function get_the_title($id){return $GLOBALS['posts'][$id]->post_title??'Gruppo dimostrativo';}
function get_post_thumbnail_id($id){return 0;}function wp_get_attachment_image_url($id,$size){return $id?'/fixture-image.svg':false;}
function nocache_headers(){$GLOBALS['no_cache']=true;}function wp_safe_redirect($url,$status){throw new RedirectResult($url,$status);}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-group-links.php';
function check_links($ok,$message){if(!$ok)throw new RuntimeException($message);}
function row_link($slug,$label='Catechesi'){return ['slug'=>$slug,'label'=>$label,'enabled'=>1,'title'=>'Ci ritroviamo presto','text'=>'Torna qui per il prossimo incontro.'];}
function add_event($id,$group,$slug,$date,$status='publish'){
 $GLOBALS['posts'][$id]=(object)['ID'=>$id,'post_type'=>'mi_event','post_status'=>$status,'post_title'=>'Evento '.$id];
 $GLOBALS['meta'][$id]=['_mi_activity_id'=>$group,'_mi_group_link'=>$slug,'_mi_event_starts_at'=>'2099-01-01T12:00'];
 $GLOBALS['published'][$id]=['event_starts_at'=>$date,'opens_at'=>'2099-01-01T12:00','closes_at'=>'2099-01-02T12:00'];
}
$posts[12]=(object)['ID'=>12,'post_type'=>'mi_activity','post_status'=>'publish','post_title'=>'Gruppo 12'];
$posts[13]=(object)['ID'=>13,'post_type'=>'mi_activity','post_status'=>'publish','post_title'=>'Altro gruppo'];
$links=MI_Group_Links::validate(12,[row_link('12CESTEROMA'),row_link('12cesteeventi','Gite')]);
check_links(!is_wp_error($links)&&isset($links['12cesteroma']),'Normalizzazione e due categorie');
check_links(MI_Group_Links::save(12,$links)===true,'Salvataggio link');
$options['mi_group_link_altragita']=13;$meta[13]['_mi_group_links']=['altragita'=>array_merge($links['12cesteeventi'],['label'=>'Altro gruppo'])];
if(in_array('--html',$argv,true)){
 echo '<div class="mi-portal"><h1>Gruppi</h1><h2>Crea gruppo</h2><form id="create-group">';MI_Group_Links::group_fields();echo '<button type="submit">Crea gruppo</button></form><h2>Gestisci Gruppo 12</h2><form id="edit-group">';MI_Group_Links::group_fields(12);echo '<button type="submit">Salva gruppo</button></form><h2>Evento</h2><form id="event-form"><label>Gruppo<select name="activity_id"><option value="12">Gruppo 12</option><option value="13">Altro gruppo</option></select></label>';MI_Group_Links::event_field([$posts[12],$posts[13]],'12cesteroma');echo '</form></div>';exit;
}
if(in_array('--waiting',$argv,true)){$meta[12]['_mi_group_cover_image_id']=99;echo MI_Group_Links::waiting_content(12,$links['12cesteroma']);exit;}
check_links(!isset(MI_Group_Links::links(12)['12cesteroma']['upload_index']),'Nessun indice upload persistito');
$pages['existing-page']=(object)['ID'=>1];
foreach(['wp-admin','v','123','a/b','https://example.invalid','bad space','-a','a--b',str_repeat('a',61),'existing-page','altragita'] as $slug)check_links(is_wp_error(MI_Group_Links::validate(12,[row_link($slug)])),'Collisione o nome non valido: '.$slug);
check_links(is_wp_error(MI_Group_Links::validate(13,[row_link('12cesteroma')])),'Indirizzo non assegnabile ad altro gruppo');
check_links(is_wp_error(MI_Group_Links::validate(12,[row_link('new'),row_link('new')])),'Duplicati nello stesso invio');
check_links(is_wp_error(MI_Group_Links::validate(12,[array_merge(row_link('new'),['original'=>'12cesteroma'])])),'Indirizzo permanente immutabile');
check_links(is_wp_error(MI_Group_Links::validate(12,[['slug'=>['bad'],'label'=>'X']])),'Payload array respinto');
check_links(is_wp_error(MI_Group_Links::validate(12,array_fill(0,21,row_link('many')))),'Limite righe');
$lock_ok=false;check_links(is_wp_error(MI_Group_Links::validate(12,[row_link('new')])),'Errore lock');$lock_ok=true;
$race=MI_Group_Links::validate(12,[row_link('race')]);$options['mi_group_link_race']=13;
check_links(is_wp_error(MI_Group_Links::save(12,$race))&&!isset(MI_Group_Links::links(12)['race']),'Conflitto concorrente non sovrascritto');
$edit=row_link('12cesteroma');$edit['text']="L'incontro del gruppo \\ prosegue";$edit['enabled']=0;$edit['original']='12cesteroma';
MI_Group_Links::save(12,MI_Group_Links::validate(12,[$edit]));
check_links(MI_Group_Links::links(12)['12cesteroma']['text']===$edit['text'],'Apostrofi e barre conservati');
check_links(isset(MI_Group_Links::links(12)['12cesteeventi']),'Invio parziale conserva altre categorie');
check_links(is_wp_error(MI_Group_Links::validate_event_link(12,'12cesteroma')),'Categoria disabilitata non assegnabile');
check_links(MI_Group_Links::validate_event_link(12,'12cesteroma','12cesteroma')==='12cesteroma','Evento esistente conserva categoria disattivata');
check_links(is_wp_error(MI_Group_Links::validate_event_link(13,'12cesteeventi')),'Categoria di altro gruppo respinta');
check_links(MI_Group_Links::validate_event_link(12,'')==='','Nessuna categoria ammessa');
MI_Group_Links::save(12,MI_Group_Links::validate(12,[row_link('12cesteroma')]));
add_event(100,12,'12cesteroma','2026-12-10T10:00');
add_event(101,12,'12cesteroma','2026-11-10T10:00');
add_event(102,12,'12cesteeventi','2026-10-30T10:00');
add_event(103,13,'12cesteroma','2026-10-30T10:00');
add_event(104,12,'12cesteroma','2026-10-30T10:00','draft');
add_event(105,12,'12cesteroma','2026-10-28T10:00');
add_event(106,12,'12cesteroma','2026-10-30T10:00');$meta[106]['_mi_event_cancelled_at']='yes';
add_event(107,12,'12cesteroma','2026-10-30T10:00');$meta[107]['_mi_event_archived_at']='yes';
add_event(108,12,'12cesteroma','bad');
add_event(109,12,'12cesteroma','2026-10-30T10:00');$published[109]=new WP_Error('deleting','Deleting');
check_links(MI_Group_Links::next_event(12,'12cesteroma')===101,'Prossimo pubblicato: non ultimo creato, non apertura iscrizioni, non data bozza');
check_links(MI_Group_Links::next_event(12,'12cesteeventi')===102,'Categorie separate');
$published[101]['closes_at']='2026-10-28T12:00';check_links(MI_Group_Links::next_event(12,'12cesteroma')===101,'Stato iscrizioni chiuse gestito dal modulo esistente');
add_event(110,12,'12cesteroma','2026-10-29T08:00');check_links(MI_Group_Links::next_event(12,'12cesteroma')===110,'Evento resta visibile nel giorno corrente');
unset($posts[110]);
$_SERVER['REQUEST_URI']='/12cesteroma?mi_iscrizione=999';
try{MI_Group_Links::route();throw new RuntimeException('Redirect mancante');}catch(RedirectResult $e){check_links($e->url==='https://example.invalid/?mi_iscrizione=101'&&$e->status===302&&$no_cache,'Redirect temporaneo senza cache e senza query estranee');}
$posts[12]->post_status='draft';check_links(MI_Group_Links::route()===null,'Gruppo non pubblico non esposto');$posts[12]->post_status='publish';
$meta[12]['_mi_group_links']['12cesteroma']['enabled']=false;check_links(MI_Group_Links::route()===null,'Link disattivato non risolve');$meta[12]['_mi_group_links']['12cesteroma']['enabled']=true;
$meta[101]['_mi_event_cancelled_at']='yes';check_links(MI_Group_Links::next_event(12,'12cesteroma')===100,'Passaggio al successivo dopo annullamento');
$posts[100]->post_status='trash';check_links(MI_Group_Links::next_event(12,'12cesteroma')===0,'Nessun futuro pubblicato');
$base='https://example.invalid/sito';check_links(MI_Group_Links::request_slug('/sito/12cesteroma/?q=x')==='12cesteroma','Installazione in sottocartella');
check_links(MI_Group_Links::request_slug('/sito/v/12cesteroma')===''&&MI_Group_Links::request_slug('/12cesteroma')==='','Namespace evento e base non intercettati');$base='https://example.invalid';
$meta[12]['_mi_group_cover_image_id']=99;
$wait=MI_Group_Links::waiting_content(12,['label'=>'Catechesi','title'=>'<script>x</script>','text'=>"A & B\nseconda riga",'image_id'=>0]);
check_links(str_contains($wait,'fixture-image.svg')&&str_contains($wait,'&lt;script&gt;')&&!str_contains($wait,'<script>')&&str_contains($wait,"A &amp; B\nseconda riga"),'Attesa: copertina gruppo, testo e HTML sicuri');
ob_start();MI_Group_Links::group_fields(12);$html=ob_get_clean();check_links(substr_count($html,'data-mi-link-row')===4&&str_contains($html,'readonly')&&str_contains($html,'group_link_image_0'),'Editor multiplo e template');
echo "PASS group links: categorie, salvataggio, collisioni, permessi di gruppo, stato pubblicato, date, redirect, cache, attesa e rendering.\n";
