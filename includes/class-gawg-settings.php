<?php
defined( 'ABSPATH' ) || exit;

/**
 * GAWG's settings page.
 *
 * Fields are declared in config/settings.json and rendered by the shared
 * lauzis/wp-plugin-packages settings component, which also supplies the
 * logging control so it matches the other plugins. Ids in the schema are bare;
 * the gawg_ prefix is applied at load time, so every stored option keeps the
 * name it already had.
 */
class GAWG_Settings {

	const PREFIX = 'gawg_';

	public static function init() {
		add_action( 'carbon_fields_register_fields', array( __CLASS__, 'register_fields' ) );
	}

	/**
	 * @return \Lauzis\WpPackages\Settings\Settings|null
	 */
	public static function page() {
		if ( ! class_exists( 'WpPackages_Registry' ) ) {
			return null;
		}

		return WpPackages_Registry::settings(
			'gawg',
			array(
				'title'       => __( 'Settings', 'gawg' ),
				'mode'        => 'flat',
				'page_parent' => 'gawg',
				// Explicit and ending in .php, so the menu entry keeps a stable
				// slug that WordPress resolves to admin.php?page=… correctly.
				'page_file'   => 'crb_gawg_settings.php',
			)
		);
	}

	public static function register_fields() {
		$page = self::page();

		if ( ! $page ) {
			return;
		}

		$page->register(
			GAWG_PLUGIN_DIR . 'config/settings.json',
			array(
				'prefix' => self::PREFIX,
				'domain' => 'gawg',
			)
		);

		$page->register(
			WpPackages_Registry::schema( 'logs' ),
			array(
				'prefix' => self::PREFIX,
				'domain' => 'wp-plugin-packages',
			)
		);

		$page->render();
	}

	/**
	 * Reads a setting by its bare schema id.
	 *
	 * @param string $id
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get( $id, $default = null ) {
		$page = self::page();

		return $page ? $page->get( $id, $default ) : $default;
	}

	public static function get_recaptcha_site_key() {
		return (string) self::get( 'recaptcha_site_key' );
	}

	public static function get_recaptcha_secret_key() {
		return (string) self::get( 'recaptcha_secret_key' );
	}

	public static function is_recaptcha_enabled() {
		return '' !== self::get_recaptcha_site_key() && '' !== self::get_recaptcha_secret_key();
	}

	public static function get_extra_entries_unique_visit() {
		$val = (int) self::get( 'extra_entries_unique_visit' );
		return $val > 0 ? $val : 1;
	}

	public static function get_extra_entries_registration() {
		$val = (int) self::get( 'extra_entries_registration' );
		return $val > 0 ? $val : 1;
	}

	public static function get_email_verification_template() {
		return wp_kses_post( (string) self::get( 'email_verification' ) );
	}

	public static function get_email_success_template() {
		return wp_kses_post( (string) self::get( 'email_success' ) );
	}
}
