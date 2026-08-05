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

		$template = GAWG_Settings::get_email_verification_template();

		if ( '' === $template ) {
			// Nothing was configured, so nothing goes out and the entrant can
			// never verify. Silence made this look like a working giveaway
			// collecting entries that could never be completed.
			GAWG_History::append( $participant_id, 'verification_email_failed' );
			GAWG_Logs::error(
				'mail',
				'No verification email template is configured, so no verification email was sent.',
				array( 'participant' => $participant_id, 'giveaway' => $giveaway_title )
			);

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

		if ( $sent ) {
			// Stamped here rather than before the send: this timestamp starts
			// the link's 24-hour expiry clock, so setting it for mail that
			// never left expires a link the entrant was never given.
			update_post_meta( $participant_id, GAWG_Participant::META_VERIFICATION_SENT_AT, time() );

			GAWG_History::append( $participant_id, 'verification_email_sent' );
			GAWG_Logs::add( 'mail', 'Verification email sent.', array( 'participant' => $participant_id, 'giveaway' => $giveaway_title ) );
		} else {
			// wp_mail() returning false was previously discarded, and the
			// history still recorded the mail as sent. An entrant who never
			// receives this cannot enter, so it is a fairness problem rather
			// than a cosmetic one — recorded either way, and to PHP's error log.
			GAWG_History::append( $participant_id, 'verification_email_failed' );
			GAWG_Logs::error( 'mail', 'Verification email could not be sent.', array( 'participant' => $participant_id, 'giveaway' => $giveaway_title ) );
		}

		return $sent;
	}

	public static function send_success_email( $participant_id, $giveaway_term ) {
		$email          = get_the_title( $participant_id );
		$giveaway_title = $giveaway_term->name;
		$rules_url      = (string) get_term_meta( $giveaway_term->term_id, GAWG_Giveaway::META_RULES_URL, true );

		$template = GAWG_Settings::get_email_success_template();

		if ( '' === $template ) {
			GAWG_History::append( $participant_id, 'success_email_failed' );
			GAWG_Logs::error(
				'mail',
				'No success email template is configured, so no confirmation was sent.',
				array( 'participant' => $participant_id, 'giveaway' => $giveaway_title )
			);

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

		if ( $sent ) {
			GAWG_History::append( $participant_id, 'success_email_sent' );
			GAWG_Logs::add( 'mail', 'Success email sent.', array( 'participant' => $participant_id ) );
		} else {
			GAWG_History::append( $participant_id, 'success_email_failed' );
			GAWG_Logs::error( 'mail', 'Success email could not be sent.', array( 'participant' => $participant_id ) );
		}

		return $sent;
	}

	private static function interpolate( $template, array $placeholders ) {
		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
	}
}
