<?php
define('ABSPATH', __DIR__);
define('MI_PLUGIN_URL', 'https://example.invalid/');
define('MI_VERSION', 'test');
function is_singular(){return false;}
function get_post(){return null;}
function is_user_logged_in(){return $GLOBALS['logged'];}
function sanitize_key($v){return $v;}
function wp_unslash($v){return $v;}
function wp_enqueue_style($h,...$args){$GLOBALS['assets'][]=$h;}
function wp_enqueue_script($h,...$args){$GLOBALS['assets'][]=$h;}
class MI_Portal_Payments {static function allowed(){return true;}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
foreach (['manage','management','registrations','groups','payments','payment-report','communications','create','operators'] as $view) {
    foreach ([false,true] as $logged) {
        $_GET=['mi_portal'=>'1','mi_portal_view'=>$view];$assets=[];
        MI_Portal::assets();
        $management=$logged&&in_array($view,['management','registrations','groups'],true);
        // The shared management stylesheet remains needed by the event toolbar.
        if (count(array_filter($assets,fn($h)=>$h==='mi-portal-management'))!==($management?2:1)) throw new Exception('Management assets: '.$view);
        if (in_array('mi-portal-payments',$assets,true)!==($logged&&in_array($view,['payments','payment-report'],true))) throw new Exception('Payment assets: '.$view);
    }
}
echo "Portal loading: 18 combinations passed.\n";
