<?php
ob_start(); require __DIR__.'/email-newlines.php'; ob_end_clean();
define('ARRAY_A','ARRAY_A');
function home_url($path=''){return 'https://example.invalid'.$path;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function current_time(...$args){return '2026-11-17 12:00:00';}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-travel.php';
$wpdb=new class {
 public $prefix='wp_',$last_error='',$saved;
 function prepare($sql,...$args){return $sql;}
 function get_var($sql){return str_repeat('a',64);}
 function insert($table,$data){$this->saved=$data;return 1;}
};
$notify=new ReflectionMethod('MI_Travel','notify_locked');
foreach([[],[['participant_id'=>1,'name'=>'Ada Esempio'],['participant_id'=>2,'name'=>'Bruno Esempio']]] as $people){
 $notify->invoke(null,['participant_id'=>1,'registration_id'=>9,'event_id'=>42,'buyer_email'=>'demo@example.invalid'],'Nuovi passeggeri','Persona Esempio ha scelto un posto.',$people);
 $snapshot=json_decode($wpdb->saved['payload_json'],true)['email_preview'];
 $html=MI_Modello_Email::componi_html($snapshot);$text=MI_Modello_Email::componi_testo($snapshot);
 $expected=max(1,count($people));
 expect(count($snapshot['azioni'])===$expected,'Missing travel actions');
 foreach($snapshot['azioni'] as $action){
  expect(substr_count(html_entity_decode($html),$action['url'])===1,'HTML duplicate URL');
  expect(substr_count($text,$action['url'])===1,'Text duplicate URL');
 }
 expect(!str_contains($snapshot['html'],'<a '),'Inline duplicate remains');
}
echo "PASS: single and multiple travel notifications keep one link per person in HTML and text.\n";
