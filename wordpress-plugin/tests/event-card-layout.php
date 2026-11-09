<?php
$render_html = in_array( '--html', $argv, true );
$argv = array( $argv[0] );
ob_start(); require __DIR__ . '/event-wizard.php'; ob_end_clean();
function add_query_arg( $args, $value, $url = null ) {
    if ( ! is_array( $args ) ) $args = array( $args => $value );
    else $url = $value;
    return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
}
class MI_Portal_Management {
    public static function allowed() { return true; }
    public static function url( $id ) { return 'https://example.invalid/registrations?event=' . $id; }
}
class MI_Sheet_Open {
    public static function url( $id ) { return 'https://example.invalid/open-sheet?event=' . $id; }
}
class MI_Shortcode {
    public static function url_iscrizione( $id ) { return 'https://example.invalid/event'; }
}
$_GET = array( 'mi_portal' => '1', 'mi_portal_view' => 'manage' );
$GLOBALS['meta'][42]['_mi_travel'] = array( 'enabled' => false );
$GLOBALS['meta'][42]['_mi_event_starts_at'] = '2027-11-22T16:00';
$GLOBALS['meta'][42]['_mi_registration_opens_at'] = '2027-10-12T09:59';
$GLOBALS['meta'][42]['_mi_registration_closes_at'] = '2027-11-22T15:00';
$GLOBALS['meta'][42]['_mi_event_location'] = 'Sala incontri';
$GLOBALS['meta'][42]['_mi_operational_sheet_url'] = 'https://example.invalid/sheet';
$GLOBALS['meta'][42]['_mi_capacity'] = 198;
$render_card = new ReflectionMethod( 'MI_Portal', 'event_management_card' );
ob_start(); $render_card->invoke( null, 42, 0, 0 ); $card_html = ob_get_clean();
check_wizard( str_contains( $card_html, 'class="mi-sheet-button" href="https://example.invalid/open-sheet?event=42"' ), 'Google sheet no longer uses verified opening route' );
check_wizard( str_contains( $card_html, '<span>Apri foglio</span>' ), 'Google sheet button lacks explicit label' );
check_wizard( str_contains( $card_html, 'name="opens_at" value="2027-10-12T09:59"' ) && str_contains( $card_html, 'name="capacity" value="198"' ), 'Existing field values changed' );
check_wizard( strpos( $html, 'Formato richiesto:' ) < strpos( $html, 'name="availability_count_visible"' ), 'Date help separated from date fields by availability checkbox' );
check_wizard( str_contains( $card_html, '<label>Apertura iscrizioni<input' ), 'Opening label not aligned with other short date labels' );
if ( $render_html ) echo $card_html;
else echo "PASS: scheda evento reale, pulsante con apertura verificata, valori conservati e nota date prima della disponibilita.\n";
