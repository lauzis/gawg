<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Form {

	const AJAX_ACTION = 'gawg_form_submit';

	private static $script_localized = false;

	public static function init() {
		add_action( 'init',               array( __CLASS__, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION,        array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_submit' ) );
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

		wp_send_json_success();
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
