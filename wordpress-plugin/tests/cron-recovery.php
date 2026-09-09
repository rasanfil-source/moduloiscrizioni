<?php
define('ABSPATH', __DIR__);
$events = ['mi_sync_workspace_pending' => ['schedule'=>'hourly','time'=>1]];
function wp_next_scheduled($hook) { global $events; return $events[$hook]['time'] ?? false; }
function wp_get_schedule($hook) { global $events; return $events[$hook]['schedule'] ?? false; }
function wp_clear_scheduled_hook($hook) { global $events; unset($events[$hook]); }
function wp_schedule_event($time,$schedule,$hook) { global $events; $events[$hook]=compact('time','schedule'); }
define('HOUR_IN_SECONDS',3600);
define('DAY_IN_SECONDS',86400);
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php';
$method = new ReflectionMethod('MI_Activator','ensure_schedule');
$method->invoke(null);
if ($events['mi_sync_workspace_pending']['schedule'] !== 'mi_five_minutes') throw new Exception('Recupero non aggiornato');
if ($events['mi_sync_workspace_pending']['time'] > time()+2) throw new Exception('Recupero rinviato');
$first=$events;
$method->invoke(null);
if ($events !== $first) throw new Exception('Pianificazione duplicata');
if (MI_Activator::cron_schedules([])['mi_five_minutes']['interval'] !== 300) throw new Exception('Intervallo errato');
echo "Cron: migrazione oraria, avvio immediato e idempotenza superati.\n";
