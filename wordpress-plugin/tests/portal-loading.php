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
function wp_enqueue_script($h,$src,$deps,$version,$args){
    $GLOBALS['assets'][]=$h;
    if (($args['strategy']??null)!=='defer' || ($args['in_footer']??null)!==true) throw new Exception('Portal script must request native deferred loading: '.$h);
}
class MI_Portal_Payments {static function allowed(){return true;}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
foreach ([null,'manage','management','registrations','groups','payments','payment-report','communications','create','operators'] as $view) {
    foreach ([false,true] as $logged) {
        $_GET=['mi_portal'=>'1'];if ($view!==null) $_GET['mi_portal_view']=$view;$assets=[];
        MI_Portal::assets();
        $management=$logged&&in_array($view,[null,'management','registrations','groups'],true);
        // The shared management stylesheet remains needed by the event toolbar.
        if (count(array_filter($assets,fn($h)=>$h==='mi-portal-management'))!==($management?2:1)) throw new Exception('Management assets: '.$view);
        if (in_array('mi-portal-payments',$assets,true)!==($logged&&in_array($view,['payments','payment-report'],true))) throw new Exception('Payment assets: '.$view);
    }
}
echo "Portal loading: 20 combinations passed, including default startup.\n";

// Exercise the real router: opening Iscrizioni must not prepare the Eventi list.
function is_ssl(){return true;}
function current_user_can($cap){return $cap==='mi_portal_access';}
function absint($v){return abs((int)$v);}
function home_url($path=''){return 'https://example.invalid'.$path;}
function wp_logout_url($url){return $url;}
function esc_url($url){return htmlspecialchars($url,ENT_QUOTES);}
function add_query_arg($key,$value,$url=null){$args=is_array($key)?$key:[$key=>$value];$url=is_array($key)?$value:$url;return $url.(str_contains($url,'?')?'&':'?').http_build_query($args);}
class MI_Access {
    static $eventReads=0;
    static function is_suspended(){return false;}
    static function event_ids(){self::$eventReads++;return [];}
}
class MI_Portal_Management {
    static $renders=0;
    static function render($id=0){self::$renders++;echo '<section data-mi-management>Iscrizioni</section>';}
}
$logged=true;
foreach ([['mi_portal'=>1],['mi_portal'=>1,'mi_pwa_group'=>12]] as $launch) {
    $_GET=$launch;
    $html=MI_Portal::render();
    if (!str_contains($html,'data-mi-management') || MI_Access::$eventReads!==0) throw new Exception('Startup must render only registrations');
    if (!preg_match('/aria-current="page"[^>]*>Iscrizioni<\/a>/', $html)) throw new Exception('Registrations navigation must be active');
}
$_GET=['mi_portal'=>1,'mi_portal_view'=>'manage'];
$html=MI_Portal::render();
if (str_contains($html,'data-mi-management') || MI_Access::$eventReads!==1 || MI_Portal_Management::$renders!==2) throw new Exception('Events must load only on explicit navigation');
echo "Portal routing: browser and PWA start on registrations; events load on demand.\n";
