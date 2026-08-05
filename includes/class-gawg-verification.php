<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Verification {

	const EXPIRY_SECONDS = DAY_IN_SECONDS;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'handle_verification_link' ) );
		add_action( 'init', array( __CLASS__, 'handle_resend_link' ) );
	}

	public static function handle_verification_link() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$giveaway_uuid    = isset( $_GET['gwag-giveaway'] )    ? sanitize_text_field( wp_unslash( $_GET['gwag-giveaway'] ) )    : '';
		$participant_uuid = isset( $_GET['gwag-participant'] )  ? sanitize_text_field( wp_unslash( $_GET['gwag-participant'] ) )  : '';
		// phpcs:enable

		if ( '' === $giveaway_uuid || '' === $participant_uuid ) {
			return;
		}

		$term = self::find_term_by_uuid( $giveaway_uuid );
		if ( null === $term ) {
			self::die_error( __( 'Giveaway not found.', 'gawg' ) );
		}

		$participant = self::find_participant_by_uuid( $participant_uuid );
		if ( null === $participant ) {
			self::die_error( __( 'Participant not found.', 'gawg' ) );
		}

		$status = self::process_verification( $participant->ID, $term );

		if ( 'already_verified' === $status ) {
			self::die_success( __( 'Your participation is already registered. No further action is needed.', 'gawg' ) );
		}

		if ( 'expired' === $status ) {
			$resend_url = add_query_arg(
				array(
					'gwag-resend-verify' => $participant_uuid,
					'gwag-giveaway'      => $giveaway_uuid,
				),
				home_url( '/' )
			);
			self::die_error(
				__( 'This verification link has expired.', 'gawg' ),
				'<a href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend verification link', 'gawg' ) . '</a>'
			);
		}

		self::die_success( __( 'Your email has been verified! You are now registered for the giveaway.', 'gawg' ) );
	}

	public static function handle_resend_link() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$participant_uuid = isset( $_GET['gwag-resend-verify'] ) ? sanitize_text_field( wp_unslash( $_GET['gwag-resend-verify'] ) ) : '';
		$giveaway_uuid    = isset( $_GET['gwag-giveaway'] )      ? sanitize_text_field( wp_unslash( $_GET['gwag-giveaway'] ) )      : '';
		// phpcs:enable

		if ( '' === $participant_uuid || '' === $giveaway_uuid ) {
			return;
		}

		$term = self::find_term_by_uuid( $giveaway_uuid );
		if ( null === $term ) {
			self::die_error( __( 'Giveaway not found.', 'gawg' ) );
		}

		$participant = self::find_participant_by_uuid( $participant_uuid );
		if ( null === $participant ) {
			self::die_error( __( 'Participant not found.', 'gawg' ) );
		}

		if ( '1' === get_post_meta( $participant->ID, GAWG_Participant::META_VERIFIED, true ) ) {
			self::die_success( __( 'Your participation is already verified.', 'gawg' ) );
		}

		GAWG_Mailer::send_verification_email( $participant->ID, $term );

		self::die_success( __( 'A new verification email has been sent. Please check your inbox.', 'gawg' ) );
	}

	/**
	 * Core verification logic — extracted for testability.
	 * Returns 'already_verified', 'expired', or 'verified'.
	 *
	 * @param int    $participant_id
	 * @param object $giveaway_term
	 * @param int    $now  Unix timestamp; defaults to time().
	 * @return string
	 */
	public static function process_verification( $participant_id, $giveaway_term, $now = null ) {
		if ( null === $now ) {
			$now = time();
		}

		if ( '1' === get_post_meta( $participant_id, GAWG_Participant::META_VERIFIED, true ) ) {
			return 'already_verified';
		}

		$sent_at = (int) get_post_meta( $participant_id, GAWG_Participant::META_VERIFICATION_SENT_AT, true );
		if ( 0 === $sent_at || ( $now - $sent_at ) > self::EXPIRY_SECONDS ) {
			return 'expired';
		}

		update_post_meta( $participant_id, GAWG_Participant::META_VERIFIED, '1' );
		GAWG_History::append( $participant_id, 'verified' );
		GAWG_Mailer::send_success_email( $participant_id, $giveaway_term );

		self::maybe_record_invite_verified( $participant_id, $giveaway_term );

		return 'verified';
	}

	private static function maybe_record_invite_verified( $participant_id, $giveaway_term ) {
		$invitee_uuid  = (string) get_post_meta( $participant_id, GAWG_Participant::META_UUID, true );
		$giveaway_uuid = (string) get_term_meta( $giveaway_term->term_id, GAWG_Giveaway::META_UUID, true );
		if ( '' === $invitee_uuid || '' === $giveaway_uuid ) {
			return;
		}
		$meta_key = 'gawg_invitee_' . $giveaway_uuid . '_' . $invitee_uuid;
		$inviters = get_posts( array(
			'post_type'      => GAWG_Participant::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => $meta_key,
					'value' => 'registered',
				),
			),
			'fields'         => 'ids',
		) );
		foreach ( $inviters as $inviter_id ) {
			GAWG_History::append( $inviter_id, 'invite_verified' );
		}
	}

	private static function die_success( $message ) {
		wp_die(
			'<p>' . esc_html( $message ) . '</p>',
			__( 'GAWG — Email Verification', 'gawg' ),
			array( 'response' => 200 )
		);
	}

	private static function die_error( $message, $extra_html = '' ) {
		$output = '<p>' . esc_html( $message ) . '</p>';
		if ( '' !== $extra_html ) {
			$output .= '<p>' . $extra_html . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller escapes
		}
		wp_die(
			$output, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by parts above
			__( 'GAWG — Email Verification', 'gawg' ),
			array( 'response' => 400 )
		);
	}

	public static function find_term_by_uuid( $uuid ) {
		$terms = get_terms( array(
			'taxonomy'   => GAWG_Giveaway::TAXONOMY,
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'   => GAWG_Giveaway::META_UUID,
					'value' => $uuid,
				),
			),
			'number'     => 1,
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return $terms[0];
	}

	public static function find_participant_by_uuid( $uuid ) {
		$posts = get_posts( array(
			'post_type'      => GAWG_Participant::POST_TYPE,
			'post_status'    => 'publish',
			'meta_key'       => GAWG_Participant::META_UUID,
			'meta_value'     => $uuid,
			'posts_per_page' => 1,
		) );
		return ! empty( $posts ) ? $posts[0] : null;
	}
}
