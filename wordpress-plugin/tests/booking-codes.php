<?php
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
function remove_accents($s){return strtr($s,['à'=>'a','è'=>'e','é'=>'e','ì'=>'i','ò'=>'o','ù'=>'u']);}
function wp_strip_all_tags($s){return strip_tags($s);}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-registration-service.php';
class CodeDatabase {
 public $prefix='wp_', $last_error='', $rows=[];
 function prepare($sql,...$args){return [$sql,$args];}
 function get_row($q,$mode){return $this->rows[$q[1][0]]??null;}
 function query($q){[$sql,$args]=$q;if(str_starts_with($sql,'INSERT')){[$id,$prefix]=$args;foreach($this->rows as $r)if($r['prefix']===$prefix)return 0;$this->rows[$id]=['prefix'=>$prefix,'sequence'=>0];return 1;}[$seq,$id]=$args;$this->rows[$id]['sequence']=$seq;return 1;}
}
$wpdb=new CodeDatabase();
$method=new ReflectionMethod('MI_Registration_Service','generate_order_code');
function check($condition){if(!$condition)throw new Exception('Booking code test failed');}
check($method->invoke(null,1,'Cammino di Santiago 2027')==='CDS1');
check($method->invoke(null,1,'Titolo modificato')==='CDS2');
$duplicate=$method->invoke(null,2,'Cammino di Santiago 2028');check((bool)preg_match('/^CDS[A-Z]+1$/',$duplicate));
check(strlen($method->invoke(null,3,'Assisi'))>=3);
check($method->invoke(null,4,'2027')==='EV1');
echo "Booking codes OK: initials, sequence, stable prefix, collision, short title.\n";
