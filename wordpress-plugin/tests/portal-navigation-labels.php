<?php
// Real label function with synthetic access scopes; no WordPress writes.
define('ABSPATH', __DIR__);
class MI_Access { public static $scope; public static function event_ids() { return self::$scope; } }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-portal.php';
$method = new ReflectionMethod('MI_Portal', 'manage_label');
$labels = array();
foreach (array(array(42), array(42, 43), 'ALL') as $scope) {
    MI_Access::$scope = $scope;
    $labels[] = $method->invoke(null);
}
if ($labels !== array('Gestisci evento', 'I miei eventi', 'Gestisci eventi')) throw new RuntimeException('Role labels changed');
echo json_encode($labels);
