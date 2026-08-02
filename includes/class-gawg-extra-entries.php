<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles the gawg_add_extra_entries action hook.
 *
 * External code can fire:
 *
 *   do_action( 'gawg_add_extra_entries', array(
 *       // One of participant_uuid or participant_email is required:
 *       'participant_uuid'  => 'abc-123',
 *       'participant_email' => 'user@example.com',
 *
 *       'action_id'         => 'gravity_form_quiz',   // required; used in deduplication meta keys
 *       'message'           => 'Completed the quiz',  // required; stored in participant history
 *
 *       // Optional:
 *       'giveaway_uuid'     => 'xyz-456',  // if omitted, all active giveaways are targeted
 *       'entry_count'       => 1,          // entries to award per event; default 1
 *       'unique'            => true,       // true = award once per giveaway; default true
 *       'max_entries'       => 10,         // cap for non-unique mode; default 10
 *   ) );
 */
class GAWG_Extra_Entries {

	public static function init() {
		add_action( 'gawg_add_extra_entries', array( __CLASS__, 'handle' ) );
	}

	/**
	 * @param array $args Hook arguments.
	 */
	public static function handle( $args ) {
		if ( ! is_array( $args ) ) {
			return;
		}

		$action_id = isset( $args['action_id'] ) ? sanitize_key( $args['action_id'] ) : '';
		$message   = isset( $args['message'] )   ? sanitize_text_field( $args['message'] ) : '';

		if ( '' === $action_id || '' === $message ) {
			return;
		}

		$entry_count = isset( $args['entry_count'] ) ? max( 1, (int) $args['entry_count'] ) : 1;
		$unique      = isset( $args['unique'] )       ? (bool) $args['unique'] : true;
		$max_entries = isset( $args['max_entries'] )  ? max( 1, (int) $args['max_entries'] ) : 10;

		$participant = null;
		if ( ! empty( $args['participant_uuid'] ) ) {
			$participant = self::find_participant_by_uuid( sanitize_text_field( $args['participant_uuid'] ) );
		} elseif ( ! empty( $args['participant_email'] ) ) {
			$participant = self::find_participant_by_email( sanitize_email( $args['participant_email'] ) );
		}

		if ( null === $participant ) {
			return;
		}

		if ( '1' !== get_post_meta( $participant->ID, GAWG_Participant::META_VERIFIED, true ) ) {
			return;
		}

		$giveaways = array();
		if ( ! empty( $args['giveaway_uuid'] ) ) {
			$term = self::find_term_by_uuid( sanitize_text_field( $args['giveaway_uuid'] ) );
			if ( null !== $term && self::is_giveaway_active( $term ) ) {
				$giveaways[] = $term;
			}
		} else {
			$giveaways = self::get_active_giveaways_for_participant( $participant->ID );
		}

		if ( empty( $giveaways ) ) {
			return;
		}

		if ( $unique ) {
			self::handle_unique( $participant->ID, $giveaways, $action_id, $message, $entry_count );
		} else {
			self::handle_non_unique( $participant->ID, $giveaways, $action_id, $message, $entry_count, $max_entries );
		}
	}

	/**
	 * Unique mode: award entries once per giveaway. Meta key tracks whether the action has fired.
	 */
	private static function handle_unique( $participant_id, $giveaways, $action_id, $message, $entry_count ) {
		foreach ( $giveaways as $term ) {
			$ga_uuid = get_term_meta( $term->term_id, GAWG_Giveaway::META_UUID, true );
			if ( '' === $ga_uuid ) {
				continue;
			}

			$flag_key = 'gawg_' . $ga_uuid . '_' . $action_id;
			if ( 'true' === get_post_meta( $participant_id, $flag_key, true ) ) {
				continue;
			}

			update_post_meta( $participant_id, $flag_key, 'true' );
			self::increment_entries( $participant_id, $ga_uuid, $entry_count );
			GAWG_History::append( $participant_id, 'extra_entries: ' . $message );
			GAWG_Logs::add( 'entries', 'Extra entries awarded.', array(
				'participant' => $participant_id,
				'giveaway'    => $ga_uuid,
				'entries'     => $entry_count,
				'reason'      => $message,
			) );
		}
	}

	/**
	 * Non-unique mode: award entries up to max_entries times total (global counter per action_id).
	 */
	private static function handle_non_unique( $participant_id, $giveaways, $action_id, $message, $entry_count, $max_entries ) {
		$count_key = 'gawg_' . $action_id . '_count';
		$current   = (int) get_post_meta( $participant_id, $count_key, true );

		if ( $current >= $max_entries ) {
			return;
		}

		update_post_meta( $participant_id, $count_key, $current + 1 );

		foreach ( $giveaways as $term ) {
			$ga_uuid = get_term_meta( $term->term_id, GAWG_Giveaway::META_UUID, true );
			if ( '' === $ga_uuid ) {
				continue;
			}
			self::increment_entries( $participant_id, $ga_uuid, $entry_count );
		}

		GAWG_History::append( $participant_id, 'extra_entries: ' . $message );
	}

	private static function increment_entries( $participant_id, $ga_uuid, $amount ) {
		$meta_key = GAWG_Participant::META_ENTRIES_PREFIX . $ga_uuid;
		$current  = (int) get_post_meta( $participant_id, $meta_key, true );
		update_post_meta( $participant_id, $meta_key, $current + $amount );
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

	private static function find_participant_by_email( $email ) {
		$posts = get_posts( array(
			'post_type'      => GAWG_Participant::POST_TYPE,
			'post_status'    => 'publish',
			'title'          => $email,
			'posts_per_page' => 1,
		) );
		return ! empty( $posts ) ? $posts[0] : null;
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

	private static function is_giveaway_active( $term ) {
		$has_winner = (int) get_term_meta( $term->term_id, GAWG_Giveaway::META_WINNER, true ) > 0;
		$is_closed  = '1' === get_term_meta( $term->term_id, GAWG_Giveaway::META_CLOSED, true );
		return ! $has_winner && ! $is_closed;
	}

	private static function get_active_giveaways_for_participant( $participant_id ) {
		$terms = wp_get_object_terms( $participant_id, GAWG_Giveaway::TAXONOMY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$active = array();
		foreach ( $terms as $term ) {
			if ( self::is_giveaway_active( $term ) ) {
				$active[] = $term;
			}
		}
		return $active;
	}
}
