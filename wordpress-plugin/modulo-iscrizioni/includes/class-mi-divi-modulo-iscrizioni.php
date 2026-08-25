<?php

defined( 'ABSPATH' ) || exit;

/** Elemento selezionabile nel Visual Builder di Divi 4. */
final class MI_Divi_Modulo_Iscrizioni extends ET_Builder_Module {
	public $slug = 'mi_divi_modulo_iscrizioni';
	public $vb_support = 'partial';

	public function init() {
		$this->name = 'Modulo iscrizioni';
		$this->plural = 'Moduli iscrizioni';
		$this->main_css_element = '%%order_class%%';
	}

	public function get_fields() {
		return array(
			'event_id' => array(
				'label' => 'Evento',
				'type' => 'select',
				'option_category' => 'basic_option',
				'options' => self::opzioni_eventi(),
				'description' => 'Scegli l’evento da collegare. Il modulo usa la stessa configurazione e gli stessi controlli del pannello iscrizioni.',
				'toggle_slug' => 'main_content',
			),
		);
	}

	public function get_advanced_fields_config() {
		return array(
			'background' => false,
			'fonts' => false,
			'button' => false,
		);
	}

	public function render( $unprocessed_props, $content, $render_slug ) {
		$event_id = absint( $this->props['event_id'] ?? 0 );
		$manager_allowed = current_user_can( 'mi_manage_events' ) && MI_Access::can_access_event( $event_id );
		$public_allowed = 'publish' === get_post_status( $event_id );
		if ( ! $event_id || MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event_id ) || ( ! $manager_allowed && ! $public_allowed ) ) {
			return current_user_can( 'mi_manage_events' ) ? '<p class="mi-registration__notice">Seleziona un evento accessibile nelle impostazioni dell’elemento Modulo iscrizioni.</p>' : '';
		}
		return MI_Shortcode::render( array( 'event' => $event_id ) );
	}

	private static function opzioni_eventi() {
		$options = array( '' => 'Seleziona evento' );
		$events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$status_labels = array( 'publish' => 'pubblicato', 'draft' => 'bozza', 'private' => 'privato' );
		foreach ( $events as $event ) {
			if ( ! MI_Access::can_access_event( $event->ID ) ) continue;
			$status = $status_labels[ $event->post_status ] ?? $event->post_status;
			$options[ (string) $event->ID ] = $event->post_title . ' — ' . $status;
		}
		return $options;
	}
}
