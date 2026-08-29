<?php
/**
 * Plugin Name: Modulo Iscrizioni
 * Description: Gestione essenziale di gruppi, eventi, capienza e iscrizioni.
 * Version: 3.9.4
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: HappyDuck
 * Text Domain: modulo-iscrizioni
 */

defined( 'ABSPATH' ) || exit;

define( 'MI_VERSION', '3.9.4' );
define( 'MI_PLUGIN_FILE', __FILE__ );
define( 'MI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MI_PLUGIN_DIR . 'includes/class-mi-activator.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-field-schema.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-event-post-type.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-event-activity-migration.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-access.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-admin.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-modello-email.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-code-image.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-spedizione-email.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-workspace-client.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-workspace-settings.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-registration-service.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-rest-controller.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-shortcode.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-portal.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-site-performance.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-integrazione-divi.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-plugin.php';

register_activation_hook( __FILE__, array( 'MI_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MI_Activator', 'deactivate' ) );

MI_Plugin::instance()->boot();
