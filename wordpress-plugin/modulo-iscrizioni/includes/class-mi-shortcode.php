<?php
defined( 'ABSPATH' ) || exit;

final class MI_Shortcode {
	private static $rendered = 0;
	const FOCUSED_TEMPLATE = 'mi-pagina-iscrizione-concentrata.php';

	public static function boot() {
		add_shortcode( 'modulo_iscrizioni', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_focused_template' ) );
		add_filter( 'template_include', array( __CLASS__, 'use_focused_template' ) );
		add_action( 'admin_post_mi_anteprima_evento', array( __CLASS__, 'mostra_anteprima_riservata' ) );
	}

	public static function register_focused_template( $templates ) {
		$templates[ self::FOCUSED_TEMPLATE ] = 'Iscrizione — modalità concentrata';
		return $templates;
	}

	public static function use_focused_template( $template ) {
		$focused = MI_PLUGIN_DIR . 'templates/pagina-iscrizione-concentrata.php';
		return is_page() && self::FOCUSED_TEMPLATE === get_page_template_slug() && is_readable( $focused ) ? $focused : $template;
	}

	public static function maybe_enqueue_assets() {
		global $post;
		if ( is_singular() && $post instanceof WP_Post && ( has_shortcode( $post->post_content, 'modulo_iscrizioni' ) || has_shortcode( $post->post_content, 'mi_divi_modulo_iscrizioni' ) ) ) self::enqueue_assets();
	}

	private static function enqueue_assets() {
		wp_enqueue_style( 'mi-public', MI_PLUGIN_URL . 'assets/public.css', array(), MI_VERSION );
		wp_enqueue_script( 'mi-core', MI_PLUGIN_URL . 'assets/core.js', array(), MI_VERSION, true );
		wp_enqueue_script( 'mi-qrcode-generator', MI_PLUGIN_URL . 'assets/qrcode-generator-2.0.4.js', array(), '2.0.4', true );
		wp_enqueue_script( 'mi-public', MI_PLUGIN_URL . 'assets/public.js', array( 'mi-core', 'mi-qrcode-generator' ), MI_VERSION, true );
	}

	public static function render( $attributes ) {
		if ( ! headers_sent() ) nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
		$attributes = shortcode_atts( array( 'event' => 0, 'anteprima' => 0 ), $attributes, 'modulo_iscrizioni' );
		$event_id = absint( $attributes['event'] );
		$is_preview = ! empty( $attributes['anteprima'] ) && current_user_can( 'mi_manage_events' ) && MI_Access::can_access_event( $event_id );
		$event = MI_Registration_Service::public_event( $event_id, $is_preview );
		if ( is_wp_error( $event ) ) return current_user_can( 'mi_manage_events' ) ? '<p class="mi-registration__notice">Evento non pubblicato o configurazione incompleta.</p>' : '';
		self::$rendered++;
		$instance_id = 'mi-registration-' . self::$rendered . '-' . $event_id;
		self::enqueue_assets();
		$config = array( 'event' => $event, 'state' => $is_preview ? 'OPEN' : MI_Registration_Service::registration_state( $event ), 'preview' => $is_preview, 'endpoint' => esc_url_raw( rest_url( MI_REST_Controller::NAMESPACE . '/events/' . $event_id . '/registrations' ) ), 'instanceId' => $instance_id, 'startedAt' => time(), 'privacyUrl' => $event['privacy_url'] );
		$formatted_date = self::formatted_event_date( $event['event_starts_at'] );
		$formatted_closes = self::formatted_event_date( $event['closes_at'] );
		ob_start(); ?>
		<section id="<?php echo esc_attr( $instance_id ); ?>" class="mi-registration" style="--mi-primary:<?php echo esc_attr( $event['accent_color'] ); ?>;--mi-primary-dark:<?php echo esc_attr( self::darken_color( $event['accent_color'] ) ); ?>" data-mi-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<?php if ( $is_preview ) : ?><p class="mi-registration__preview-notice" role="status">Anteprima riservata: puoi provare tutti i passaggi, ma nessuna iscrizione verrà inviata.</p><?php endif; ?>
			<header class="mi-registration__hero<?php echo $event['cover_image'] ? ' mi-registration__hero--with-image' : ''; ?>">
				<?php if ( $event['cover_image'] ) : ?><img class="mi-registration__cover" src="<?php echo esc_url( $event['cover_image'] ); ?>" alt="<?php echo esc_attr( $event['cover_image_alt'] ?: $event['title'] ); ?>"><?php endif; ?>
				<div class="mi-registration__hero-content">
					<?php if ( $event['activity_logo'] ) : ?><img class="mi-registration__logo" src="<?php echo esc_url( $event['activity_logo'] ); ?>" alt="<?php echo esc_attr( $event['activity_logo_alt'] ); ?>" width="180" height="80"><?php endif; ?>
					<p class="mi-registration__eyebrow"><?php echo esc_html( $event['activity'] ); ?></p><h1 class="mi-registration__title"><?php echo esc_html( $event['title'] ); ?></h1>
					<?php if ( $formatted_date || $event['event_location'] ) : ?><dl class="mi-registration__facts"><?php if ( $formatted_date ) : ?><div><dt>Quando</dt><dd><?php echo esc_html( $formatted_date ); ?></dd></div><?php endif; ?><?php if ( $event['event_location'] ) : ?><div><dt>Dove</dt><dd><?php echo esc_html( $event['event_location'] ); ?></dd></div><?php endif; ?></dl><?php endif; ?>
					<?php if ( $event['description'] ) : ?><p class="mi-registration__lead"><?php echo esc_html( $event['description'] ); ?></p><?php endif; ?>
				</div>
			</header>
			<?php if ( 'OPEN' !== $config['state'] ) : ?><p class="mi-registration__notice" role="status"><?php echo esc_html( self::state_message( $config['state'] ) ); ?></p><?php else : ?>
			<?php if ( $event['availability']['full'] && $event['waitlist_enabled'] ) : ?><p class="mi-registration__availability mi-registration__availability--waitlist" role="status"><strong>Posti ordinari esauriti.</strong> Puoi inviare la richiesta: sarà inserita in lista d’attesa.<?php if ( $formatted_closes ) : ?><small>Richieste aperte fino a <?php echo esc_html( $formatted_closes ); ?>.</small><?php endif; ?></p><?php else : ?><p class="mi-registration__availability" role="status"><strong><?php echo esc_html( (string) $event['availability']['remaining'] ); ?> posti disponibili</strong> su <?php echo esc_html( (string) $event['availability']['capacity'] ); ?>.<?php if ( $formatted_closes ) : ?><small>Iscrizioni aperte fino a <?php echo esc_html( $formatted_closes ); ?>.</small><?php endif; ?></p><?php endif; ?>
			<nav class="mi-registration__progress" aria-label="Avanzamento iscrizione"><ol><li aria-current="step" data-mi-progress="1"><span>1</span> Biglietti</li><li data-mi-progress="2"><span>2</span> Partecipanti</li><li data-mi-progress="3"><span>3</span> Conferma</li></ol></nav>
			<form class="mi-registration__form" novalidate>
				<section class="mi-registration__step" data-mi-step="1" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-step-1"><h2 id="<?php echo esc_attr( $instance_id ); ?>-step-1" tabindex="-1">Scegli le iscrizioni</h2><p class="mi-registration__intro">Seleziona la quantità per ogni tipologia.</p>
					<div class="mi-registration__tickets"><?php foreach ( $event['ticket_types'] as $ticket ) : ?><?php $type_availability = $event['availability']['ticket_types'][ $ticket['code'] ] ?? array(); $type_remaining = $type_availability['remaining'] ?? null; $maximum = ( $event['waitlist_enabled'] || null === $type_remaining ) ? $ticket['max_per_order'] : min( $ticket['max_per_order'], $type_remaining ); ?><label class="mi-registration__ticket"><span><strong><?php echo esc_html( $ticket['name'] ); ?></strong><?php if ( 'CALCULATED' === $event['pricing_mode'] ) : ?><small><?php echo esc_html( number_format_i18n( $ticket['price_cents'] / 100, 2 ) ); ?> €</small><?php elseif ( 'ZERO' === $event['pricing_mode'] ) : ?><small>Gratuito</small><?php endif; ?><?php if ( null !== $type_remaining ) : ?><small><?php echo esc_html( $type_remaining . ' disponibili per questa tipologia' ); ?></small><?php endif; ?></span><span class="mi-registration__quantity"><span>Quantità</span><input type="number" min="0" max="<?php echo esc_attr( max( 0, $maximum ) ); ?>" value="0" data-mi-ticket="<?php echo esc_attr( $ticket['code'] ); ?>" aria-label="Quantità <?php echo esc_attr( $ticket['name'] ); ?>" <?php disabled( 0 === $maximum && ! $event['waitlist_enabled'] ); ?>></span></label><?php endforeach; ?></div>
					<?php $order_options = array_values( array_filter( $event['options'], static function ( $option ) { return 'ORDER' === ( $option['scope'] ?? '' ); } ) ); if ( $order_options ) : ?><fieldset class="mi-registration__options"><legend>Opzioni per l’ordine</legend><?php foreach ( $order_options as $option ) : ?><label><span><?php echo esc_html( $option['name'] ); ?><?php if ( 'CALCULATED' === $event['pricing_mode'] && (int) $option['price_cents'] > 0 ) : ?> · <?php echo esc_html( number_format_i18n( $option['price_cents'] / 100, 2 ) ); ?> €<?php endif; ?></span><input type="number" min="0" max="<?php echo esc_attr( $option['max_quantity'] ); ?>" value="0" data-mi-order-option="<?php echo esc_attr( $option['code'] ); ?>"></label><?php endforeach; ?></fieldset><?php endif; ?>
					<?php if ( 'CALCULATED' === $event['pricing_mode'] && 'REGISTRATION_ONLY' !== $event['economic_mode'] ) : ?><div class="mi-registration__economic-summary" data-mi-economic-summary aria-live="polite"><p><strong>Totale:</strong> <span data-mi-total>0,00 €</span></p><p data-mi-initial-row hidden><strong data-mi-initial-label>Primo versamento:</strong> <span data-mi-initial>0,00 €</span></p><p data-mi-balance-row hidden><strong>Saldo successivo:</strong> <span data-mi-balance>0,00 €</span></p><p data-mi-payment-methods-row hidden><strong>Fonti ammesse:</strong> <span data-mi-payment-methods></span></p><small data-mi-economic-note></small></div><?php endif; ?>
				</section>
				<section class="mi-registration__step" data-mi-step="2" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-step-2" hidden><h2 id="<?php echo esc_attr( $instance_id ); ?>-step-2" tabindex="-1">Nomi dei partecipanti</h2><p class="mi-registration__intro">Inserisci nome e cognome di ogni partecipante. Tutti i campi sono necessari.</p><div class="mi-registration__participants" data-mi-participants></div></section>
				<section class="mi-registration__step" data-mi-step="3" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-step-3" hidden><h2 id="<?php echo esc_attr( $instance_id ); ?>-step-3" tabindex="-1">Referente e conferma</h2><div class="mi-registration__grid"><label>Nome <input name="buyerFirstName" maxlength="80" autocomplete="given-name" required></label><label>Cognome <input name="buyerLastName" maxlength="80" autocomplete="family-name" required></label><label>Email <input name="buyerEmail" type="email" maxlength="254" autocomplete="email" required></label><label>Cellulare <input name="buyerPhone" type="tel" maxlength="32" autocomplete="tel" placeholder="+39 …" required></label></div><div class="mi-registration__honeypot" aria-hidden="true"><label>Lascia vuoto <input name="website" tabindex="-1" autocomplete="off"></label></div><label class="mi-registration__consent"><input name="privacyAccepted" type="checkbox" required> <span>Ho letto l’<?php if ( $config['privacyUrl'] ) : ?><a href="<?php echo esc_url( $config['privacyUrl'] ); ?>" target="_blank" rel="noopener noreferrer">informativa privacy</a><?php else : ?>informativa privacy<?php endif; ?><?php if ( $event['privacy_policy_version'] ) : ?> versione <?php echo esc_html( $event['privacy_policy_version'] ); ?><?php endif; ?> applicabile all’evento.</span></label><?php if ( $event['marketing_enabled'] ) : ?><label class="mi-registration__consent"><input name="marketingAccepted" type="checkbox"> <span><strong>Comunicazioni su future iniziative.</strong> Acconsento a ricevere informazioni su altre attività organizzate dalla parrocchia. Il consenso è facoltativo.</span></label><?php endif; ?></section>
				<p class="mi-registration__error" data-mi-error role="alert" tabindex="-1" hidden></p>
				<div class="mi-registration__action-bar"><div aria-live="polite"><small>Selezione</small><strong data-mi-sticky-summary>Nessuna iscrizione</strong></div><div class="mi-registration__actions"><button class="mi-registration__back" type="button" data-mi-back hidden>Indietro</button><button class="mi-registration__next" type="button" data-mi-next>Continua</button><button class="mi-registration__submit" type="submit" hidden>Invia iscrizione</button></div></div>
			</form><div class="mi-registration__success" data-mi-success role="status" tabindex="-1" hidden></div><?php endif; ?>
		</section><?php return ob_get_clean();
	}

	private static function formatted_event_date( $value ) {
		if ( ! $value ) return '';
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		return $date ? wp_date( 'l j F Y, H:i', $date->getTimestamp(), wp_timezone() ) : '';
	}

	public static function mostra_anteprima_riservata() {
		$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
		if ( ! current_user_can( 'mi_manage_events' ) || ! MI_Access::can_access_event( $event_id ) ) wp_die( 'Non hai accesso a questo evento.', 'Accesso negato', array( 'response' => 403 ) );
		check_admin_referer( 'mi_anteprima_evento_' . $event_id );
		// admin-post.php non inizializza uno screen: Divi e la toolbar lo assumono presente negli hook di pagina.
		if ( function_exists( 'get_current_screen' ) && function_exists( 'set_current_screen' ) && ! get_current_screen() ) {
			set_current_screen( 'mi_event_preview' );
		}
		show_admin_bar( false );
		$content = self::render( array( 'event' => $event_id, 'anteprima' => 1 ) );
		nocache_headers();
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head><body class="mi-focused-page"><main class="mi-focused-page__main"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già protetto dal renderer. ?></main><?php wp_footer(); ?></body></html><?php
		exit;
	}

	private static function darken_color( $color ) {
		$color = sanitize_hex_color( $color ) ?: '#c43b2f';
		$red = max( 0, (int) round( hexdec( substr( $color, 1, 2 ) ) * .72 ) );
		$green = max( 0, (int) round( hexdec( substr( $color, 3, 2 ) ) * .72 ) );
		$blue = max( 0, (int) round( hexdec( substr( $color, 5, 2 ) ) * .72 ) );
		return sprintf( '#%02x%02x%02x', $red, $green, $blue );
	}

	private static function state_message( $state ) {
		$messages = array( 'NOT_OPEN' => 'Le iscrizioni non sono ancora aperte.', 'CLOSED' => 'Le iscrizioni sono chiuse.', 'SOLD_OUT' => 'I posti sono esauriti e la lista d’attesa non è attiva.', 'MISCONFIGURED' => 'Le iscrizioni non sono al momento disponibili.' );
		return $messages[ $state ] ?? 'Le iscrizioni non sono disponibili.';
	}
}
