<?php
defined( 'ABSPATH' ) || exit;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class GAWG_Settings {

	public static function init() {
		add_action( 'carbon_fields_register_fields', array( __CLASS__, 'register_fields' ) );
	}

	public static function register_fields() {
		Container::make( 'theme_options', 'gawg_settings', __( 'Settings', 'gawg' ) )
			->set_page_parent( 'gawg' )
			->add_fields( array(
				Field::make( 'separator', 'gawg_recaptcha_separator', __( 'Google reCAPTCHA v2', 'gawg' ) ),
				Field::make( 'text', 'gawg_recaptcha_site_key', __( 'Site Key', 'gawg' ) )
					->set_help_text( __( 'The site key (public key) from your Google reCAPTCHA v2 admin panel.', 'gawg' ) ),
				Field::make( 'text', 'gawg_recaptcha_secret_key', __( 'Secret Key', 'gawg' ) )
					->set_help_text( __( 'The secret key (private key) from your Google reCAPTCHA v2 admin panel.', 'gawg' ) ),
				Field::make( 'separator', 'gawg_extra_entries_separator', __( 'Extra Entries', 'gawg' ) ),
				Field::make( 'text', 'gawg_extra_entries_unique_visit', __( 'Extra entries for unique visit', 'gawg' ) )
					->set_attribute( 'type', 'number' )
					->set_attribute( 'min', '0' )
					->set_help_text( __( 'Entries awarded to an inviter when a unique visitor clicks their invite link. Default: 1.', 'gawg' ) ),
				Field::make( 'text', 'gawg_extra_entries_registration', __( 'Extra entries for registration after visit', 'gawg' ) )
					->set_attribute( 'type', 'number' )
					->set_attribute( 'min', '0' )
					->set_help_text( __( 'Entries awarded to an inviter when a visitor they referred completes registration. Default: 1.', 'gawg' ) ),
			) );
	}

	public static function get_recaptcha_site_key() {
		return (string) carbon_get_theme_option( 'gawg_recaptcha_site_key' );
	}

	public static function get_recaptcha_secret_key() {
		return (string) carbon_get_theme_option( 'gawg_recaptcha_secret_key' );
	}

	public static function is_recaptcha_enabled() {
		return '' !== self::get_recaptcha_site_key() && '' !== self::get_recaptcha_secret_key();
	}

	public static function get_extra_entries_unique_visit() {
		$val = (int) carbon_get_theme_option( 'gawg_extra_entries_unique_visit' );
		return $val > 0 ? $val : 1;
	}

	public static function get_extra_entries_registration() {
		$val = (int) carbon_get_theme_option( 'gawg_extra_entries_registration' );
		return $val > 0 ? $val : 1;
	}
}
