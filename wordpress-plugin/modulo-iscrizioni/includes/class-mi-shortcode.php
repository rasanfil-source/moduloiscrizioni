<?php

defined( 'ABSPATH' ) || exit;

final class MI_Shortcode {
	private static $rendered = 0;

	public static function boot() {
		add_shortcode( 'modulo_iscrizioni', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
	}

	public static function maybe_enqueue_assets() {
		global $post;
		if ( is_singular() && $post instanceof WP_Post && has_shortcode( $post->post_content, 'modulo_iscrizioni' ) ) {
			wp_enqueue_style( 'mi-public', MI_PLUGIN_URL . 'assets/public.css', array(), MI_VERSION );
			wp_enqueue_script( 'mi-core', MI_PLUGIN_URL . 'assets/core.js', array(), MI_VERSION, true );
			wp_enqueue_script( 'mi-public', MI_PLUGIN_URL . 'assets/public.js', array( 'mi-core' ), MI_VERSION, true );
		}
	}

	public static function render( $attributes ) {
		$attributes = shortcode_atts( array( 'event' => 0 ), $attributes, 'modulo_iscrizioni' );
		$event_id = absint( $attributes['event'] );
		$event = MI_Registration_Service::public_event( $event_id );
		if ( is_wp_error( $event ) ) {
			return current_user_can( 'mi_manage_events' ) ? '<p class="mi-registration__notice">Evento non pubblicato o configurazione incompleta.</p>' : '';
		}

		self::$rendered++;
		$instance_id = 'mi-registration-' . self::$rendered . '-' . $event_id;
		wp_enqueue_style( 'mi-public', MI_PLUGIN_URL . 'assets/public.css', array(), MI_VERSION );
		wp_enqueue_script( 'mi-core', MI_PLUGIN_URL . 'assets/core.js', array(), MI_VERSION, true );
		wp_enqueue_script( 'mi-public', MI_PLUGIN_URL . 'assets/public.js', array( 'mi-core' ), MI_VERSION, true );

		$config = array(
			'event'      => $event,
			'state'      => MI_Registration_Service::registration_state( $event ),
			'endpoint'   => esc_url_raw( rest_url( MI_REST_Controller::NAMESPACE . '/events/' . $event_id . '/registrations' ) ),
			'instanceId' => $instance_id,
			'startedAt'  => time(),
			'privacyUrl' => get_privacy_policy_url(),
		);

		ob_start();
		?>
		<section id="<?php echo esc_attr( $instance_id ); ?>" class="mi-registration" data-mi-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<header class="mi-registration__header">
				<?php if ( $event['activity_logo'] ) : ?><img class="mi-registration__logo" src="<?php echo esc_url( $event['activity_logo'] ); ?>" alt="<?php echo esc_attr( $event['activity_logo_alt'] ); ?>" width="180" height="80"><?php endif; ?>
				<p class="mi-registration__eyebrow"><?php echo esc_html( $event['activity'] ); ?></p>
				<h2 class="mi-registration__title"><?php echo esc_html( $event['title'] ); ?></h2>
				<?php if ( $event['description'] ) : ?><p class="mi-registration__lead"><?php echo esc_html( $event['description'] ); ?></p><?php endif; ?>
			</header>

			<?php if ( 'OPEN' !== $config['state'] ) : ?>
				<p class="mi-registration__notice" role="status"><?php echo esc_html( self::state_message( $config['state'] ) ); ?></p>
			<?php else : ?>
			<form class="mi-registration__form" novalidate>
				<div class="mi-registration__step">
					<h3>1. Tipologia e partecipanti</h3>
					<div class="mi-registration__tickets">
					<?php foreach ( $event['ticket_types'] as $ticket ) : ?>
						<label class="mi-registration__ticket"><span><strong><?php echo esc_html( $ticket['name'] ); ?></strong><?php if ( 'CALCULATED' === $event['pricing_mode'] ) : ?><small><?php echo esc_html( number_format_i18n( $ticket['price_cents'] / 100, 2 ) ); ?> €</small><?php endif; ?></span><input type="number" min="0" max="<?php echo esc_attr( $ticket['max_per_order'] ); ?>" value="0" data-mi-ticket="<?php echo esc_attr( $ticket['code'] ); ?>" aria-label="Quantità <?php echo esc_attr( $ticket['name'] ); ?>"></label>
					<?php endforeach; ?>
					</div>
					<?php if ( 'CALCULATED' === $event['pricing_mode'] && 'REGISTRATION_ONLY' !== $event['economic_mode'] ) : ?><div class="mi-registration__economic-summary" data-mi-economic-summary aria-live="polite"><p><strong>Totale:</strong> <span data-mi-total>0,00 €</span></p><p data-mi-initial-row hidden><strong data-mi-initial-label>Primo versamento:</strong> <span data-mi-initial>0,00 €</span></p><p data-mi-balance-row hidden><strong>Saldo successivo:</strong> <span data-mi-balance>0,00 €</span></p><small data-mi-economic-note></small></div><?php endif; ?>
					<div class="mi-registration__participants" data-mi-participants></div>
				</div>

				<fieldset class="mi-registration__step">
					<legend>2. Referente</legend>
					<div class="mi-registration__grid">
						<label>Nome <input name="buyerFirstName" maxlength="80" autocomplete="given-name" required></label>
						<label>Cognome <input name="buyerLastName" maxlength="80" autocomplete="family-name" required></label>
						<label>Email <input name="buyerEmail" type="email" maxlength="254" autocomplete="email" required></label>
						<label>Cellulare <input name="buyerPhone" type="tel" maxlength="32" autocomplete="tel" placeholder="+39 …" required></label>
					</div>
				</fieldset>

				<div class="mi-registration__honeypot" aria-hidden="true"><label>Lascia vuoto <input name="website" tabindex="-1" autocomplete="off"></label></div>
				<label class="mi-registration__consent"><input name="privacyAccepted" type="checkbox" required> <span>Ho letto l’<?php if ( $config['privacyUrl'] ) : ?><a href="<?php echo esc_url( $config['privacyUrl'] ); ?>" target="_blank" rel="noopener noreferrer">informativa privacy</a><?php else : ?>informativa privacy<?php endif; ?> applicabile all’evento.</span></label>
				<p class="mi-registration__error" data-mi-error role="alert" tabindex="-1" hidden></p>
				<button class="mi-registration__submit" type="submit">Invia iscrizione</button>
			</form>
			<div class="mi-registration__success" data-mi-success role="status" tabindex="-1" hidden></div>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function state_message( $state ) {
		$messages = array(
			'NOT_OPEN'      => 'Le iscrizioni non sono ancora aperte.',
			'CLOSED'        => 'Le iscrizioni sono chiuse.',
			'MISCONFIGURED' => 'Le iscrizioni non sono al momento disponibili.',
		);
		return $messages[ $state ] ?? 'Le iscrizioni non sono disponibili.';
	}
}
