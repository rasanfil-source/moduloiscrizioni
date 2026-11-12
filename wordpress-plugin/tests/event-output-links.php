<?php
define('ABSPATH',__DIR__);
function absint($v){return abs((int)$v);}
function esc_url($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_attr($v){return esc_html($v);}
function get_post($id){return(object)['post_title'=>'Evento prova'];}
function get_post_status($id){return 'publish';}
function get_post_meta($id,$key,$single=true){return ['_mi_ticket_types'=>[['code'=>'base']], '_mi_operational_sheet_url'=>'https://docs.google.com/spreadsheets/d/demo-sheet/edit', '_mi_registration_url'=>'https://example.invalid/evento', '_mi_economic_mode'=>'DEPOSIT_BALANCE'][$key]??'';}
function home_url($p){return 'https://example.invalid'.$p;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function admin_url($p){return 'https://example.invalid/wp-admin/'.$p;}
function wp_nonce_url($url,$action){return $url.'&_wpnonce=test';}
function wp_nonce_field(...$args){}
class MI_Access {static function can_access_event($id){return $id===42;}}
class MI_Sheet_Open {static function url($id){return 'https://example.invalid/private-sheet?event='.$id;}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
$method=new ReflectionMethod(MI_Portal::class,'event_outputs_panel');
ob_start();$method->invoke(null,42);$html=ob_get_clean();
if(!preg_match('/data-mi-copy="([^"]+)" data-mi-copy-success-label="Copiato"/',$html,$match)||html_entity_decode($match[1])!=='https://docs.google.com/spreadsheets/d/demo-sheet/edit')throw new RuntimeException('Shared link requires WordPress login');
if(!str_contains($html,'href="https://example.invalid/private-sheet?event=42"'))throw new RuntimeException('Operational refresh link lost');
echo "PASS: collegamento condiviso diretto a Google e apertura operativa conservata.\n";
