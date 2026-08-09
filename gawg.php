<?php
/**
 * Plugin Name: GAWG — GiveAway Winner Generator
 * Description: Create giveaways, collect applicant entries, and run a lottery to pick winners.
 * Version:     1.2.2
 * Author:      Aivars Lauzis
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gawg
 */

defined( 'ABSPATH' ) || exit;

define( 'GAWG_VERSION',    '1.2.2' );
define( 'GAWG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GAWG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'GAWG_LOG_PATH' ) ) {
	// Under uploads/, never inside the plugin directory: WordPress deletes and
	// re-extracts that folder on every update, which would take the logs too.
	$gawg_uploads = wp_upload_dir();
	define( 'GAWG_LOG_PATH', str_replace( '\\', '/', $gawg_uploads['basedir'] ) . '/gawg-logs/' );
	unset( $gawg_uploads );
}

require_once GAWG_PLUGIN_DIR . 'vendor/autoload.php';
// Required explicitly: Composer's files autoload runs only one copy of this
// package per request, so the version gate would never see the others.
require_once GAWG_PLUGIN_DIR . 'vendor/lauzis/wp-plugin-packages/bootstrap.php';

require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-logs.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-history.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-participant.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-giveaway.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-admin.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-settings.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-mailer.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-form.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-block.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-verification.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg-extra-entries.php';
require_once GAWG_PLUGIN_DIR . 'includes/class-gawg.php';

add_action( 'after_setup_theme', array( 'Carbon_Fields\\Carbon_Fields', 'boot' ) );
add_action( 'plugins_loaded', array( 'GAWG', 'init' ) );

register_activation_hook( __FILE__, 'gawg_activate' );
register_deactivation_hook( __FILE__, 'gawg_deactivate' );

function gawg_activate() {}

function gawg_deactivate() {}

// The plugin's version in the admin footer, beside WordPress's own — the first
// thing worth knowing about a page misbehaving is which version drew it.
add_action( 'admin_init', static function () {
    if ( ! class_exists( '\\Lauzis\\WpPackages\\Admin\\Footer' ) ) {
        return;
    }

    \Lauzis\WpPackages\Admin\Footer::show(
        'gawg',
        array(
            'name'    => 'GAWG',
            'version' => defined( 'GAWG_VERSION' ) ? GAWG_VERSION : '',
        'types'   => array( 'gawg_participant' ),
        )
    );
} );
