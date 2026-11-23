<?php
define('ABSPATH',__DIR__);
define('ARRAY_A','ARRAY_A');
function wp_timezone(){return new DateTimeZone('UTC');}
function wp_date($format,$timestamp,$zone){return gmdate($format,$timestamp);}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-travel.php';
$rows=[['participant_id'=>1,'first_name'=>'Caterina','last_name'=>'Comberiati','mode'=>'DRIVER','seats'=>2],['participant_id'=>2,'first_name'=>'Carmine','last_name'=>'Scalzi','mode'=>'OWN','companion_of'=>1]];
if(MI_Travel::participant_summary($rows[1],$rows)!=='Viaggio in auto con Caterina Comberiati')throw new RuntimeException('Companion label');
unset($rows[1]['companion_of']);
if(MI_Travel::participant_summary($rows[1],$rows)!=='Trasferimento autonomo')throw new RuntimeException('Legacy guessed');
if(!str_contains(MI_Travel::participant_summary($rows[0],$rows),'Posti offerti: 2'))throw new RuntimeException('Driver seats');
$rows[1]['companion_of']=1;
foreach($rows as &$row)$row+=['registration_id'=>42,'driver_id'=>null,'origin'=>'Roma','departure_at'=>'2027-01-01 10:00:00','price_cents'=>500,'state'=>'NONE','person_status'=>'ACTIVE'];unset($row);
$rows[]=['participant_id'=>3,'registration_id'=>99,'mode'=>'NEED','driver_id'=>1,'first_name'=>'Anna','last_name'=>'Esempio','state'=>'ASSIGNED','person_status'=>'ACTIVE'];
$wpdb=new class($rows){public $prefix='wp_',$last_error='';function __construct(public $rows){}function prepare($sql,...$args){return $sql;}function get_results(...$args){return $this->rows;}};
$details=MI_Travel::management_details(7,42);
if(count($details)!==2||!in_array('Accompagnatore: Carmine Scalzi',$details[1],true)||!in_array('Anna Esempio: Passaggio assegnato',$details[1],true)||!in_array('Viaggio in auto con Caterina Comberiati',$details[2],true))throw new RuntimeException('Management detail incomplete');
echo "PASS: companion, independent legacy choice and seats.\n";
