<?php
/**
 * Plugin Name: GiveAway Winner Generator
 * Description: Create giveaways, collect applicant entries, and run a lottery to pick winners.
 * Version:     1.0.0
 * Author:      Aivars Lauzis
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gawg
 */

defined( 'ABSPATH' ) || exit;

define( 'GAWG_VERSION',    '1.0.0' );
define( 'GAWG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GAWG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GAWG_PLUGIN_DIR . 'vendor/autoload.php';

require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-participant.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-giveaway.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-admin.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-settings.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-mailer.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-form.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-block.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-verification.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg.php';

add_action( 'after_setup_theme', array( 'Carbon_Fields\\Carbon_Fields', 'boot' ) );
add_action( 'plugins_loaded', array( 'GAWG', 'init' ) );

register_activation_hook( __FILE__, 'gawg_activate' );
register_deactivation_hook( __FILE__, 'gawg_deactivate' );

function gawg_activate() {}

function gawg_deactivate() {}
