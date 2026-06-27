<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Mailer {

	public static function send_verification_email( $participant_id, $giveaway_term ) {
		$email            = get_the_title( $participant_id );
		$participant_uuid = (string) get_post_meta( $participant_id, GAWG_Participant::META_UUID, true );
		$giveaway_uuid    = (string) get_term_meta( $giveaway_term->term_id, GAWG_Giveaway::META_UUID, true );
		$giveaway_title   = $giveaway_term->name;

		$verification_link = add_query_arg(
			array(
				'gwag-giveaway'    => $giveaway_uuid,
				'gwag-participant' => $participant_uuid,
			),
			home_url( '/' )
		);

		update_post_meta( $participant_id, GAWG_Participant::META_VERIFICATION_SENT_AT, time() );

		$template = GAWG_Settings::get_email_verification_template();
		if ( '' === $template ) {
			return false;
		}

		$body = self::interpolate(
			$template,
			array(
				'{participant_email}'  => $email,
				'{giveaway_title}'     => $giveaway_title,
				'{verification_link}'  => $verification_link,
			)
		);

		/* translators: %s: giveaway title */
		$subject = sprintf( __( 'Please verify your email for %s', 'gawg' ), $giveaway_title );

		$sent = wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		GAWG_History::append( $participant_id, 'verification_email_sent' );
		return $sent;
	}

	public static function send_success_email( $participant_id, $giveaway_term ) {
		$email          = get_the_title( $participant_id );
		$giveaway_title = $giveaway_term->name;
		$rules_url      = (string) get_term_meta( $giveaway_term->term_id, GAWG_Giveaway::META_RULES_URL, true );

		$template = GAWG_Settings::get_email_success_template();
		if ( '' === $template ) {
			return false;
		}

		$body = self::interpolate(
			$template,
			array(
				'{participant_email}' => $email,
				'{giveaway_title}'    => $giveaway_title,
				'{rules_url}'         => $rules_url,
			)
		);

		/* translators: %s: giveaway title */
		$subject = sprintf( __( 'You are registered for %s', 'gawg' ), $giveaway_title );

		$sent = wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		GAWG_History::append( $participant_id, 'success_email_sent' );
		return $sent;
	}

	private static function interpolate( $template, array $placeholders ) {
		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
	}
}
