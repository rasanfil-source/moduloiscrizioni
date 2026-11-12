<?php
// Cache ricostruibile e migrazioni: nessuna connessione o dato operativo.
define('ABSPATH',__DIR__.'/missing-wp-fixture/');define('DB_NAME','synthetic');define('MI_VERSION','audit55');
function absint($v){return abs((int)$v);} function wp_json_encode($v){return json_encode($v);}
$options=[];$transients=[];
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,...$args){$GLOBALS['options'][$k]=$v;}
function delete_option($k){unset($GLOBALS['options'][$k]);}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}
function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=$v;}
function delete_transient($k){unset($GLOBALS['transients'][$k]);}
function audit55($ok,$message){if(!$ok)throw new RuntimeException($message);}
$wpdb=new class {
 public $prefix='wp_', $packet=1048576, $locked=false, $busy=false, $locks=0, $releases=0;
 function prepare($q,...$args){return $q;}
 function get_var($sql){
  if(str_contains($sql,'max_allowed_packet'))return $this->packet;
  if(str_contains($sql,'GET_LOCK')){$this->locks++;if($this->busy)return 0;$this->locked=true;return 1;}
  if(str_contains($sql,'RELEASE_LOCK')){$this->releases++;$this->locked=false;return 1;}
  throw new RuntimeException('Unexpected SQL');
 }
};
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-event-read-cache.php';
MI_Event_Read_Cache::put(1,'a',['text'=>'Piccolo']);
audit55(MI_Event_Read_Cache::get(1,'a')===['text'=>'Piccolo'],'Cache round trip');
$transients=[];
MI_Event_Read_Cache::put(1,'large',['text'=>base64_encode(random_bytes(600000))]);
audit55(!$transients,'Final serialized payload exceeded conservative limit');
$wpdb->packet=10000;
MI_Event_Read_Cache::put(1,'packet',['text'=>base64_encode(random_bytes(2000))]);
audit55(!$transients,'Packet limit ignored');
$wpdb->packet=0;MI_Event_Read_Cache::put(1,'unknown',['a'=>1]);audit55(!$transients,'Unknown limit must skip optional cache');
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php';
$upgrade=new ReflectionMethod(MI_Activator::class,'upgrade_schema');$upgrade->setAccessible(true);
$wpdb->busy=true;$upgrade->invoke(null,false);audit55(!$transients&&!$options,'Busy migration changed state');
$wpdb->busy=false;
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
try{$upgrade->invoke(null,false);}finally{restore_error_handler();}
audit55(!$wpdb->locked&&$wpdb->releases===1,'Failed migration leaked named lock');
audit55(!get_option('mi_db_version')&&!get_option('mi_db_schema_revision'),'Failure marked schema complete');
audit55(get_option('mi_schema_upgrade_failed')===true,'Failure not visible');
$locks=$wpdb->locks;$upgrade->invoke(null,false);audit55($wpdb->locks===$locks,'Backoff retried migration immediately');
echo "PASS: cache limits, unknown packet, migration busy/failure/backoff and lock release.\n";
