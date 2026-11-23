<?php
define('ABSPATH', __DIR__);
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-iban.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-database-requirements.php';
function verify($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$valid = 'IT60'.'X0542811101000000123456';
verify(MI_IBAN::valid($valid), 'Valid Italian reference rejected');
verify(MI_IBAN::valid('it60 '.'x054 2811 1010 0000 0123 456'), 'Normalization failed');
foreach (str_split($valid) as $i => $char) {
    $changed = $valid; $changed[$i] = ctype_digit($char) ? (string)(((int)$char + 1) % 10) : ($char === 'Z' ? 'A' : chr(ord($char)+1));
    verify(!MI_IBAN::valid($changed), 'Single character error accepted at '.$i);
}
// Valid MOD-97 with deliberately wrong national CIN must still fail.
$bban = 'Y'.substr($valid,5); $digits = '';
foreach (str_split($bban.'IT00') as $char) $digits .= ctype_digit($char) ? $char : (string)(ord($char)-55);
$remainder = 0; foreach (str_split($digits) as $d) $remainder = ($remainder*10+(int)$d)%97;
verify(!MI_IBAN::valid('IT'.str_pad((string)(98-$remainder),2,'0',STR_PAD_LEFT).$bban), 'National CIN not checked');
foreach (['', $valid.'extra', 'DE89'.'370400440532013000'] as $bad) verify(!MI_IBAN::valid($bad),'Invalid shape accepted');
foreach (['5.7.5'=>true,'5.7.4'=>false,'5.6.51'=>false,'8.0.40'=>true,'10.0.1-MariaDB'=>false,'10.0.2-MariaDB'=>true,'5.5.5-10.11.6-MariaDB-0+deb12u1'=>true,'11.4.3-MariaDB'=>true,''=>false] as $server=>$expected) verify(MI_Database_Requirements::supports_multiple_locks($server)===$expected,'Database requirement: '.$server);
function wp_die($message) { throw new RuntimeException($message); }
function esc_html($value) { return $value; }
$wpdb = new class { public $version='5.6.51'; function db_server_info(){return $this->version;} };
try { MI_Database_Requirements::activation_check(); throw new LogicException('Old database activated'); } catch (RuntimeException $e) { verify(str_contains($e->getMessage(),'MySQL 5.7.5'),'Missing requirement message'); }
$wpdb->version='5.5.5-10.11.6-MariaDB'; MI_Database_Requirements::activation_check();
echo "PASS: IBAN normalization, all single-character errors, independent CIN, database lock requirements and activation guard.\n";
