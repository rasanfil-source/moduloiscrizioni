<?php
ob_start(); require __DIR__ . '/management-deposit-totals.php'; ob_end_clean();
function check_timestamp_model( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$test_registration_timestamps = '1';
$wpdb->order['created_at'] = '2026-11-09 09:10:11';
$wpdb->order['status'] = 'CONFIRMED';
$wpdb->people[0]['status'] = 'CANCELLED';
$wpdb->people[0]['cancelled_at'] = '2026-11-09 10:11:12';
$model = MI_Management_Service::summary( 42, array( 1 ), array() );
check_timestamp_model( ! is_wp_error( $model ), 'Lettura non riuscita' );
check_timestamp_model( $model['features']['registration_timestamps'] === true, 'Indicatore esportazione mancante' );
check_timestamp_model( $model['people'][0]['registration_time'] === '2026-11-09 10:10:11', 'Ora iscrizione assente dalla persona' );
check_timestamp_model( $model['people'][0]['cancellation_time'] === '2026-11-09 11:11:12', 'Ora annullamento assente dalla persona' );
check_timestamp_model( $model['people'][1]['cancellation_time'] === '', 'Persona attiva marcata annullata' );
check_timestamp_model( $model['items'][0]['registration_time'] === '2026-11-09 10:10:11', 'Rapporto prenotazioni senza ora iscrizione' );
check_timestamp_model( MI_Management_List::page( $model, array() )['total'] === 1, 'Conteggio attivi modificato' );
$closed = MI_Management_List::page( $model, array( 'includeClosed' => true ) );
$closed_by_id = array_column( $closed['rows'], null, 'id' );
check_timestamp_model( $closed['total'] === 2 && $closed_by_id[1]['cancellation_time'] === '2026-11-09 11:11:12', 'Filtro iscrizioni chiuse perde data annullamento' );
$overview = MI_Management_List::overview( $model );
check_timestamp_model( ! isset( $overview['people'] ) && ! isset( $overview['registration_time'] ), 'Date inserite nel riepilogo' );
$test_registration_timestamps = '';
$legacy = MI_Management_Service::summary( 42, array( 1 ), array() );
check_timestamp_model( ! isset( $legacy['features']['registration_timestamps'] ) && ! isset( $legacy['people'][0]['registration_time'] ), 'Evento esistente modificato' );
echo "PASS: date disponibili nelle pagine di esportazione, filtri chiuse e conteggi invariati, eventi precedenti esclusi.\n";
