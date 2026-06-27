<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Form {

	const AJAX_ACTION = 'gawg_form_submit';

	private static $script_localized = false;

	public static function init() {
		add_action( 'init',               array( __CLASS__, 'register_shortcode' ) );
		add_action( 'init',               array( __CLASS__, 'handle_invite_link' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION,        array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_submit' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_participant_publish' ), 10, 3 );
	}

	public static function register_shortcode() {
		add_shortcode( 'gawg_form', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function register_assets() {
		wp_register_script(
			'gawg-form',
			GAWG_PLUGIN_URL . 'assets/js/gawg-form.js',
			array(),
			GAWG_VERSION,
			true
		);
		wp_register_script(
			'google-recaptcha',
			'https://www.google.com/recaptcha/api.js',
			array(),
			null,
			true
		);
	}

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'uuid'            => '',
				'rules_url'       => '',
				'rules_post_id'   => '',
				'success_message' => '',
			),
			$atts,
			'gawg_form'
		);

		$uuid = sanitize_text_field( $atts['uuid'] );
		if ( '' === $uuid ) {
			return '';
		}

		$giveaway_term = self::find_term_by_uuid( $uuid );
		if ( null !== $giveaway_term ) {
			$has_winner = (int) get_term_meta( $giveaway_term->term_id, GAWG_Giveaway::META_WINNER, true ) > 0;
			$is_closed  = '1' === get_term_meta( $giveaway_term->term_id, GAWG_Giveaway::META_CLOSED, true );
			if ( $has_winner || $is_closed ) {
				return '<p class="gawg-closed-message">' . esc_html__( 'Sorry, this giveaway is closed.', 'gawg' ) . '</p>';
			}
		}

		$rules_url = '';
		if ( '' !== $atts['rules_url'] ) {
			$rules_url = esc_url_raw( $atts['rules_url'] );
		} elseif ( '' !== $atts['rules_post_id'] ) {
			$rules_url = (string) get_permalink( (int) $atts['rules_post_id'] );
		}

		$success_message = '' !== $atts['success_message']
			? wp_kses_post( $atts['success_message'] )
			: '<p>' . esc_html__( 'Thank you for applying! You are now in the list of participants.', 'gawg' ) . '</p>';

		$recaptcha_site_key = GAWG_Settings::get_recaptcha_site_key();
		$recaptcha_enabled  = GAWG_Settings::is_recaptcha_enabled();

		wp_enqueue_script( 'gawg-form' );
		if ( $recaptcha_enabled ) {
			wp_enqueue_script( 'google-recaptcha' );
		}
		if ( ! self::$script_localized ) {
			wp_localize_script(
				'gawg-form',
				'gawgFormConfig',
				array(
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'action'           => self::AJAX_ACTION,
					'recaptchaEnabled' => $recaptcha_enabled,
					'i18n'             => array(
						'invalidEmail'    => __( 'Please enter a valid email address.', 'gawg' ),
						'acceptRules'     => __( 'Please accept the giveaway rules.', 'gawg' ),
						'networkError'    => __( 'A network error occurred. Please try again.', 'gawg' ),
						'solveRecaptcha'  => __( 'Please complete the reCAPTCHA challenge.', 'gawg' ),
						'inviteHeading'   => __( 'Share your invite link to earn extra entries:', 'gawg' ),
						'inviteDesc'      => __( 'Each unique visitor who uses your link earns you +1 entry.', 'gawg' ),
					),
				)
			);
			self::$script_localized = true;
		}

		$wrap_id = 'gawg-form-' . $uuid;

		ob_start();
		?>
		<div class="gawg-form-wrap" id="<?php echo esc_attr( $wrap_id ); ?>">
			<div class="gawg-form-success" style="display:none;"><?php echo $success_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses_post above ?></div>
			<form class="gawg-form" novalidate>
				<?php wp_nonce_field( 'gawg_form_' . $uuid, '_gawg_nonce', false ); ?>
				<input type="hidden" name="gawg_uuid" value="<?php echo esc_attr( $uuid ); ?>">
				<input type="text" name="gawg_hp" value="" style="display:none !important;" autocomplete="off" tabindex="-1" aria-hidden="true">
				<p>
					<label for="<?php echo esc_attr( $wrap_id . '-email' ); ?>">
						<?php esc_html_e( 'Email address', 'gawg' ); ?>
					</label><br>
					<input
						type="email"
						id="<?php echo esc_attr( $wrap_id . '-email' ); ?>"
						name="gawg_email"
						required
						autocomplete="email"
					>
				</p>
				<?php if ( '' !== $rules_url ) : ?>
				<p>
					<label>
						<input type="checkbox" name="gawg_rules" value="1" required>
						<?php
						printf(
							/* translators: %s: URL of the giveaway rules page */
							wp_kses(
								__( 'I have read and accept the <a href="%s" target="_blank" rel="noopener noreferrer">giveaway rules</a>.', 'gawg' ),
								array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
							),
							esc_url( $rules_url )
						);
						?>
					</label>
				</p>
				<?php endif; ?>
				<?php if ( $recaptcha_enabled ) : ?>
				<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $recaptcha_site_key ); ?>"></div>
				<?php endif; ?>
				<p>
					<button type="submit"><?php esc_html_e( 'Apply', 'gawg' ); ?></button>
				</p>
				<div class="gawg-form-message" role="alert" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function ajax_submit() {
		$uuid  = isset( $_POST['gawg_uuid'] )   ? sanitize_text_field( wp_unslash( $_POST['gawg_uuid'] ) )   : '';
		$nonce = isset( $_POST['_gawg_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_gawg_nonce'] ) ) : '';

		if ( '' === $uuid || ! wp_verify_nonce( $nonce, 'gawg_form_' . $uuid ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gawg' ) ), 403 );
		}

		$honeypot = isset( $_POST['gawg_hp'] ) ? (string) wp_unslash( $_POST['gawg_hp'] ) : '';
		if ( '' !== $honeypot ) {
			wp_send_json_error( array( 'message' => __( 'Submission rejected.', 'gawg' ) ), 400 );
		}

		$email = isset( $_POST['gawg_email'] ) ? strtolower( sanitize_email( wp_unslash( $_POST['gawg_email'] ) ) ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'gawg' ) ) );
		}

		if ( GAWG_Settings::is_recaptcha_enabled() ) {
			$recaptcha_response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
			if ( '' === $recaptcha_response || ! self::verify_recaptcha( $recaptcha_response ) ) {
				wp_send_json_error( array( 'message' => __( 'reCAPTCHA verification failed. Please try again.', 'gawg' ) ) );
			}
		}

		$term = self::find_term_by_uuid( $uuid );
		if ( null === $term ) {
			wp_send_json_error( array( 'message' => __( 'Giveaway not found.', 'gawg' ) ) );
		}

		$has_winner = (int) get_term_meta( $term->term_id, GAWG_Giveaway::META_WINNER, true ) > 0;
		$is_closed  = '1' === get_term_meta( $term->term_id, GAWG_Giveaway::META_CLOSED, true );
		if ( $has_winner || $is_closed ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, this giveaway is closed.', 'gawg' ) ) );
		}

		if ( self::participant_exists( $email, $term->term_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are already in the list of participants.', 'gawg' ) ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $email,
				'post_type'   => GAWG_Participant::POST_TYPE,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not register your entry. Please try again.', 'gawg' ) ) );
		}

		wp_set_object_terms( $post_id, $term->term_id, GAWG_Giveaway::TAXONOMY );

		// Initialise entry count to 1 for this giveaway.
		update_post_meta( $post_id, GAWG_Participant::META_ENTRIES_PREFIX . $uuid, 1 );

		// Build invite URL so the participant can share it immediately.
		$participant_uuid = get_post_meta( $post_id, GAWG_Participant::META_UUID, true );
		$invite_url       = '' !== $participant_uuid ? self::build_invite_url( $uuid, $participant_uuid ) : '';

		// Record registration-after-visit bonus for any inviter who referred this participant.
		self::maybe_record_invite_registration( $post_id, $uuid, $participant_uuid );

		wp_send_json_success( '' !== $invite_url ? array( 'invite_url' => $invite_url ) : null );
	}

	public static function handle_invite_link() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$giveaway_uuid    = isset( $_GET['gwag-giveaway'] ) ? sanitize_text_field( wp_unslash( $_GET['gwag-giveaway'] ) ) : '';
		$participant_uuid = isset( $_GET['invite'] )        ? sanitize_text_field( wp_unslash( $_GET['invite'] ) )        : '';
		// phpcs:enable

		if ( '' === $giveaway_uuid || '' === $participant_uuid ) {
			return;
		}

		$term = self::find_term_by_uuid( $giveaway_uuid );
		if ( null === $term ) {
			return;
		}

		$participant = self::find_participant_by_uuid( $participant_uuid );
		if ( null === $participant ) {
			return;
		}

		if ( ! has_term( $term->term_id, GAWG_Giveaway::TAXONOMY, $participant->ID ) ) {
			return;
		}

		$ip = self::get_visitor_ip();
		if ( '' === $ip ) {
			return;
		}

		self::process_invite( $participant->ID, $giveaway_uuid, $ip );

		// Persist attribution in a cookie so registration can be linked to this invite.
		$cookie_name = 'gawg_invite_' . str_replace( '-', '_', $giveaway_uuid );
		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			setcookie( $cookie_name, $participant_uuid, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			$_COOKIE[ $cookie_name ] = $participant_uuid;
		}
	}

	public static function build_invite_url( $giveaway_uuid, $participant_uuid ) {
		return add_query_arg(
			array(
				'gwag-giveaway' => $giveaway_uuid,
				'invite'        => $participant_uuid,
			),
			home_url( '/' )
		);
	}

	public static function process_invite( $participant_id, $giveaway_uuid, $ip ) {
		$ip_hash   = md5( $ip ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_md5
		$visit_key = GAWG_Participant::META_VISIT_PREFIX . $giveaway_uuid . '_' . $ip_hash;

		if ( '1' === get_post_meta( $participant_id, $visit_key, true ) ) {
			return false;
		}

		$entries_key = GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid;
		$current     = (int) get_post_meta( $participant_id, $entries_key, true );
		if ( $current < 1 ) {
			$current = 1;
		}
		update_post_meta( $participant_id, $entries_key, $current + GAWG_Settings::get_extra_entries_unique_visit() );
		update_post_meta( $participant_id, $visit_key, '1' );

		return true;
	}

	private static function maybe_record_invite_registration( $invitee_post_id, $giveaway_uuid, $invitee_uuid ) {
		if ( '' === $invitee_uuid ) {
			return;
		}
		$cookie_name  = 'gawg_invite_' . str_replace( '-', '_', $giveaway_uuid );
		$inviter_uuid = isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : '';
		if ( '' === $inviter_uuid || $inviter_uuid === $invitee_uuid ) {
			return;
		}
		$inviter = self::find_participant_by_uuid( $inviter_uuid );
		if ( null === $inviter ) {
			return;
		}
		self::record_invitee_registration( $inviter->ID, $giveaway_uuid, $invitee_uuid, get_post_status( $invitee_post_id ) );
	}

	public static function record_invitee_registration( $inviter_id, $giveaway_uuid, $invitee_uuid, $invitee_status ) {
		$meta_key = 'gawg_invitee_' . $giveaway_uuid . '_' . $invitee_uuid;
		if ( 'registered' === get_post_meta( $inviter_id, $meta_key, true ) ) {
			return;
		}
		if ( 'publish' === $invitee_status ) {
			update_post_meta( $inviter_id, $meta_key, 'registered' );
			$entries_key = GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid;
			$current     = (int) get_post_meta( $inviter_id, $entries_key, true );
			update_post_meta( $inviter_id, $entries_key, max( 1, $current ) + GAWG_Settings::get_extra_entries_registration() );
		} else {
			update_post_meta( $inviter_id, $meta_key, 'pending' );
		}
	}

	public static function on_participant_publish( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( GAWG_Participant::POST_TYPE !== $post->post_type ) {
			return;
		}
		$invitee_uuid = get_post_meta( $post->ID, GAWG_Participant::META_UUID, true );
		if ( '' === $invitee_uuid ) {
			return;
		}
		$terms = wp_get_object_terms( $post->ID, GAWG_Giveaway::TAXONOMY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}
		foreach ( $terms as $term ) {
			$giveaway_uuid = get_term_meta( $term->term_id, GAWG_Giveaway::META_UUID, true );
			if ( '' === $giveaway_uuid ) {
				continue;
			}
			$meta_key = 'gawg_invitee_' . $giveaway_uuid . '_' . $invitee_uuid;
			$inviters = get_posts( array(
				'post_type'      => GAWG_Participant::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => $meta_key,
						'value' => 'pending',
					),
				),
				'fields'         => 'ids',
			) );
			foreach ( $inviters as $inviter_id ) {
				update_post_meta( $inviter_id, $meta_key, 'registered' );
				$entries_key = GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid;
				$current     = (int) get_post_meta( $inviter_id, $entries_key, true );
				update_post_meta( $inviter_id, $entries_key, max( 1, $current ) + GAWG_Settings::get_extra_entries_registration() );
			}
		}
	}

	private static function get_visitor_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '';
	}

	private static function find_participant_by_uuid( $uuid ) {
		$posts = get_posts( array(
			'post_type'      => GAWG_Participant::POST_TYPE,
			'post_status'    => 'publish',
			'meta_key'       => GAWG_Participant::META_UUID,
			'meta_value'     => $uuid,
			'posts_per_page' => 1,
		) );
		return ! empty( $posts ) ? $posts[0] : null;
	}

	private static function verify_recaptcha( $token ) {
		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'body' => array(
				'secret'   => GAWG_Settings::get_recaptcha_secret_key(),
				'response' => $token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body['success'] ) && true === $body['success'];
	}

	private static function find_term_by_uuid( $uuid ) {
		$terms = get_terms( array(
			'taxonomy'   => GAWG_Giveaway::TAXONOMY,
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'   => GAWG_Giveaway::META_UUID,
					'value' => $uuid,
				),
			),
			'number' => 1,
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return $terms[0];
	}

	private static function participant_exists( $email, $term_id ) {
		$posts = get_posts( array(
			'post_type'      => GAWG_Participant::POST_TYPE,
			'post_status'    => 'publish',
			'title'          => $email,
			'tax_query'      => array(
				array(
					'taxonomy' => GAWG_Giveaway::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );

		return ! empty( $posts );
	}
}
