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

		// The test button answers over admin-ajax, which never renders the
		// settings page, so its endpoint is registered on every admin request.
		if ( is_admin() ) {
			add_action(
				'admin_init',
				static function () {
					$tester = GAWG_Logs::slack_tester();

					if ( $tester ) {
						$tester->boot();
					}
				}
			);
		}
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
				'prefix'   => self::PREFIX,
				'domain'   => 'gawg',
				// Supplied here rather than in the JSON because default values
				// are not visited by the translation manifest, and these are
				// the only defaults containing prose an entrant will read.
				'defaults' => array(
					'email_verification' => self::default_verification_template(),
					'email_success'      => self::default_success_template(),
				),
			)
		);

		// Draws the "Send a test message" button under the Slack webhook field.
		// Without the callback the schema's html field renders nothing, so an
		// older bundled package simply has no button.
		$tester = GAWG_Logs::slack_tester();

		if ( $tester ) {
			$page->callback( 'logs_slack_test', array( $tester, 'render' ) );
		}

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
		$template = trim( (string) self::get( 'email_verification' ) );

		// Falls back rather than returning empty: without this template no
		// entrant can ever verify, so an unconfigured site is a broken site.
		// The settings field is pre-filled with the same text to edit.
		if ( '' === $template ) {
			$template = self::default_verification_template();
		}

		return wp_kses_post( $template );
	}

	public static function get_email_success_template() {
		$template = trim( (string) self::get( 'email_success' ) );

		if ( '' === $template ) {
			$template = self::default_success_template();
		}

		return wp_kses_post( $template );
	}

	/**
	 * The verification email as shipped.
	 *
	 * Written to be sendable as-is and obvious to edit: short paragraphs, one
	 * link, no layout tables or inline CSS that the visual editor would fight
	 * with. The bare link is repeated as text because a fair number of mail
	 * clients still show the anchor without making it clickable.
	 */
	public static function default_verification_template() {
		return '<p>' . __( 'Hi,', 'gawg' ) . '</p>' . "\n\n"
			. '<p>' . sprintf(
				/* translators: %s: giveaway title placeholder, replaced when the mail is sent */
				__( 'Thanks for entering %s. Please confirm your email address to complete your entry.', 'gawg' ),
				'{giveaway_title}'
			) . '</p>' . "\n\n"
			. '<p><a href="{verification_link}">' . __( 'Confirm my entry', 'gawg' ) . '</a></p>' . "\n\n"
			. '<p>' . __( 'If the link above does not work, copy this address into your browser:', 'gawg' ) . '<br />'
			. '{verification_link}</p>' . "\n\n"
			. '<p>' . __( 'This link is valid for 24 hours. If you did not enter this giveaway, you can ignore this email.', 'gawg' ) . '</p>';
	}

	/**
	 * The post-verification confirmation email as shipped.
	 *
	 * {rules_url} is deliberately absent: it is optional per giveaway, and an
	 * unset one renders as a link to nowhere in every mail sent. The help text
	 * documents it for admins whose giveaways do set one.
	 */
	public static function default_success_template() {
		return '<p>' . __( 'Hi,', 'gawg' ) . '</p>' . "\n\n"
			. '<p>' . sprintf(
				/* translators: %s: giveaway title placeholder, replaced when the mail is sent */
				__( 'Your entry to %s is confirmed — good luck!', 'gawg' ),
				'{giveaway_title}'
			) . '</p>' . "\n\n"
			. '<p>' . __( 'We will be in touch if you win.', 'gawg' ) . '</p>';
	}
}
