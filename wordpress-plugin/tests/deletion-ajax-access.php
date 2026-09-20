<?php
define( 'ABSPATH', __DIR__ );
class WP_Error { public $code; function __construct( $code, $message ) { $this->code=$code; } }
class MI_Access { static function is_suspended(){return false;} static function is_global_manager(){return true;} }
function is_user_logged_in(){return $GLOBALS['logged'];}
function current_user_can($cap){return true;}
function absint($v){return abs((int)$v);}
function sanitize_text_field($v){return $v;}
function wp_unslash($v){return $v;}
function wp_verify_nonce($nonce,$action){$GLOBALS['nonce_action']=$action;return false;}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-event-deletion.php';
$step=new ReflectionMethod('MI_Event_Deletion','submitted_step');$step->setAccessible(true);
$_POST=['event_id'=>42,'_wpnonce'=>'invalid'];
$logged=false;
if($step->invoke(null)->code!=='mi_delete_access')throw new RuntimeException('Anonymous access');
$logged=true;
if($step->invoke(null)->code!=='mi_delete_nonce'||$nonce_action!=='mi_delete_event_42')throw new RuntimeException('Nonce scope');
echo "Deletion AJAX rejects anonymous users and invalid event nonce before any database access.\n";
