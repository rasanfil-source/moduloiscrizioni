<?php
defined( 'ABSPATH' ) || exit;
// Included only by the authorized MI_Admin::payments_page(true) report reader.
$report_url = MI_Portal_Payments::report_url();
$payments_url = add_query_arg( array( 'mi_portal_view' => 'payments', 'mi_portal_event' => $filter_event ), MI_Portal::url() );
?>
<section class="mi-payment-report" aria-labelledby="mi-payment-report-title">
	<header class="mi-payment-report__heading">
		<div><h2 id="mi-payment-report-title">Report pagamenti e rimborsi</h2><p class="mi-portal-muted">Movimenti registrati e riepilogo dell’intervallo selezionato.</p></div>
		<?php if ( MI_Portal_Payments::allowed() ) : ?><a class="mi-secondary" href="<?php echo esc_url( $payments_url ); ?>">Torna ai pagamenti</a><?php endif; ?>
	</header>
	<?php if ( ! current_user_can( 'mi_manage_payments' ) ) : ?><p class="mi-portal-notice">Consultazione soltanto: non disponi del permesso per registrare versamenti o rimborsi.</p><?php endif; ?>
	<form method="get" action="<?php echo esc_url( MI_Portal::url() ); ?>" class="mi-payment-report__filters">
		<input type="hidden" name="mi_portal" value="1">
		<input type="hidden" name="mi_portal_view" value="payment-report">
		<label>Evento<select name="payment_event_id"><option value="0">Tutti gli eventi accessibili</option><?php foreach ( $events as $event ) : ?><option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $filter_event, $event->ID ); ?>><?php echo esc_html( $event->post_title ); ?></option><?php endforeach; ?></select></label>
		<label>Fonte<select name="payment_source"><option value="">Tutte</option><?php foreach ( $labels as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filter_source, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
		<label>Movimento<select name="transaction_kind"><option value="">Tutti</option><option value="PAYMENT" <?php selected( $filter_transaction, 'PAYMENT' ); ?>>Versamenti</option><option value="REFUND" <?php selected( $filter_transaction, 'REFUND' ); ?>>Rimborsi</option></select></label>
		<label>Dal<input type="date" name="payment_from" value="<?php echo esc_attr( $filter_from ); ?>"></label>
		<label>Al<input type="date" name="payment_to" value="<?php echo esc_attr( $filter_to ); ?>"></label>
		<div class="mi-payment-report__actions"><button type="submit" class="mi-primary">Applica filtri</button><a class="mi-secondary" href="<?php echo esc_url( $report_url ); ?>">Azzera filtri</a></div>
	</form>
	<dl class="mi-payment-report__totals" aria-label="Riepilogo del filtro">
		<?php foreach ( array( 'PAYMENT' => 'Versamenti', 'REFUND' => 'Rimborsi', 'BANK_TRANSFER' => 'Bonifici netti', 'CARD' => 'Carte nette', 'CASH' => 'Contanti netti' ) as $key => $label ) : ?>
		<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( in_array( $key, array( 'PAYMENT', 'REFUND' ), true ) ? self::formatta_importo( $summary[$key] ) : self::formatta_importo_firmato( $summary[$key] ) ); ?></dd></div>
		<?php endforeach; ?>
	</dl>
	<div class="mi-payment-report__heading"><p><strong><?php echo esc_html( $total_rows ); ?> movimenti</strong> nel filtro · <?php echo esc_html( count( $rows ) ); ?> in questa pagina</p><a class="mi-secondary" href="<?php echo esc_url( $export_url ); ?>">Esporta CSV</a></div>
	<div class="mi-payment-report__table" role="region" aria-label="Movimenti registrati, scorri per leggere tutte le colonne" tabindex="0">
		<table><thead><tr><th scope="col">Data</th><th scope="col">Ordine</th><th scope="col">Evento</th><th scope="col">Movimento</th><th scope="col">Rata</th><th scope="col">Importo</th><th scope="col">Fonte</th><th scope="col">Riferimento</th><th scope="col">Operatore</th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="9">Nessun movimento registrato per i filtri selezionati.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?><tr>
			<td><?php echo esc_html( self::formatta_data_locale( $row['effective_at'] ) ); ?></td>
			<td><code><?php echo esc_html( $row['order_code'] ); ?></code></td>
			<td><?php echo esc_html( $row['event_title'] ); ?></td>
			<td><?php echo esc_html( 'REFUND' === $row['transaction_kind'] ? 'Rimborso' : 'Versamento' ); ?></td>
			<td><?php echo esc_html( $row['installment_kind'] ); ?></td>
			<td><?php echo esc_html( self::formatta_importo( $row['amount_cents'] ) ); ?></td>
			<td><?php echo esc_html( $labels[$row['payment_source']] ?? $row['payment_source'] ); ?></td>
			<td><?php echo esc_html( $row['external_reference'] ?: '—' ); ?></td>
			<td><?php echo esc_html( $row['operator_label'] ?: '—' ); ?></td>
		</tr><?php endforeach; ?>
		</tbody></table>
	</div>
	<?php self::render_pagination( $page, $per_page, $total_rows ); ?>
</section>
