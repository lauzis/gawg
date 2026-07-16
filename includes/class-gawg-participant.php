<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Participant {

	const POST_TYPE                  = 'gawg_participant';
	const META_UUID                  = '_gawg_uuid';
	const META_ENTRIES_PREFIX        = 'gawg_entries_';
	const META_VISIT_PREFIX          = 'gawg_visit_';
	const META_VERIFIED              = '_gawg_verified';
	const META_VERIFICATION_SENT_AT  = '_gawg_verification_sent_at';

	public static function init() {
		add_action( 'init',       array( __CLASS__, 'register_post_type' ) );
		add_action( 'init',       array( __CLASS__, 'register_meta' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'maybe_generate_uuid' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_uuid_meta_box' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_entries_meta_box' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_history_meta_box' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns',       array( __CLASS__, 'add_entries_column' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_entries_column' ), 10, 2 );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Participants', 'gawg' ),
			'singular_name'      => __( 'Participant', 'gawg' ),
			'add_new'            => __( 'Add New', 'gawg' ),
			'add_new_item'       => __( 'Add New Participant', 'gawg' ),
			'edit_item'          => __( 'Edit Participant', 'gawg' ),
			'new_item'           => __( 'New Participant', 'gawg' ),
			'view_item'          => __( 'View Participant', 'gawg' ),
			'search_items'       => __( 'Search Participants', 'gawg' ),
			'not_found'          => __( 'No participants found.', 'gawg' ),
			'not_found_in_trash' => __( 'No participants found in Trash.', 'gawg' ),
			'menu_name'          => __( 'Participants', 'gawg' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => $labels,
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'capabilities' => array(
					'publish_posts'       => 'manage_options',
					'edit_posts'          => 'manage_options',
					'edit_others_posts'   => 'manage_options',
					'delete_posts'        => 'manage_options',
					'delete_others_posts' => 'manage_options',
					'read_private_posts'  => 'manage_options',
					'edit_post'           => 'manage_options',
					'delete_post'         => 'manage_options',
					'read_post'           => 'manage_options',
				),
			)
		);
	}

	public static function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::META_UUID,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
		register_post_meta(
			self::POST_TYPE,
			self::META_VERIFIED,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '0',
				'sanitize_callback' => function( $value ) {
					return '1' === $value ? '1' : '0';
				},
				'auth_callback'     => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
		register_post_meta(
			self::POST_TYPE,
			self::META_VERIFICATION_SENT_AT,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public static function maybe_generate_uuid( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$existing = get_post_meta( $post_id, self::META_UUID, true );
		if ( '' !== $existing ) {
			return;
		}

		update_post_meta( $post_id, self::META_UUID, wp_generate_uuid4() );
	}

	public static function add_uuid_meta_box() {
		add_meta_box(
			'gawg_participant_uuid',
			__( 'Reference UUID', 'gawg' ),
			array( __CLASS__, 'render_uuid_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	public static function render_uuid_meta_box( $post ) {
		$uuid = get_post_meta( $post->ID, self::META_UUID, true );
		if ( '' === $uuid ) {
			echo '<p>' . esc_html__( 'UUID will be generated upon saving.', 'gawg' ) . '</p>';
			return;
		}
		?>
		<p style="word-break:break-all;">
			<input
				type="text"
				readonly
				value="<?php echo esc_attr( $uuid ); ?>"
				style="width:100%;font-family:monospace;"
				onclick="this.select();"
			/>
		</p>
		<p class="description"><?php esc_html_e( 'Use this UUID in subscription forms or external links to reference this participant.', 'gawg' ); ?></p>
		<?php
	}

	public static function add_entries_meta_box() {
		add_meta_box(
			'gawg_participant_entries',
			__( 'Entries & Invite Links', 'gawg' ),
			array( __CLASS__, 'render_entries_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	public static function render_entries_meta_box( $post ) {
		$participant_uuid = get_post_meta( $post->ID, self::META_UUID, true );
		$terms            = wp_get_object_terms( $post->ID, GAWG_Giveaway::TAXONOMY );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<p>' . esc_html__( 'No giveaway assigned yet.', 'gawg' ) . '</p>';
			return;
		}

		foreach ( $terms as $term ) {
			$giveaway_uuid = get_term_meta( $term->term_id, GAWG_Giveaway::META_UUID, true );
			$count         = '' !== $giveaway_uuid ? (int) get_post_meta( $post->ID, self::META_ENTRIES_PREFIX . $giveaway_uuid, true ) : 0;
			if ( $count < 1 ) {
				$count = 1;
			}
			echo '<p><strong>' . esc_html( $term->name ) . '</strong><br>';
			echo esc_html__( 'Entries:', 'gawg' ) . ' <strong>' . esc_html( (string) $count ) . '</strong></p>';

			if ( '' !== $giveaway_uuid && '' !== $participant_uuid ) {
				$invite_url = GAWG_Form::build_invite_url( $giveaway_uuid, $participant_uuid );
				echo '<p>' . esc_html__( 'Invite link:', 'gawg' ) . '<br>';
				echo '<input type="text" readonly value="' . esc_attr( $invite_url ) . '" style="width:100%;font-family:monospace;font-size:11px;" onclick="this.select();"></p>';
			}
		}
	}

	public static function add_history_meta_box() {
		add_meta_box(
			'gawg_participant_history',
			__( 'Action History', 'gawg' ),
			array( 'GAWG_History', 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'low'
		);
	}

	/**
	 * Return every giveaway a given email address is participating in, with entry breakdowns.
	 *
	 * Intended for use on a front-end "profile" page: pass an email address and receive a plain
	 * array of associative arrays, one per giveaway the address is entered in. This method produces
	 * no output and performs no ownership check on the supplied address, so the caller is responsible
	 * for verifying that the visitor is entitled to view the given email's participation data.
	 *
	 * Each element has the shape:
	 *   array(
	 *     'giveaway_uuid'     => (string) giveaway reference UUID,
	 *     'giveaway_title'    => (string) giveaway name,
	 *     'status'            => (string) one of 'active', 'closed', 'winner_drawn',
	 *     'status_label'      => (string) translated human-readable status label,
	 *     'total_entries'     => (int)    total entry count for this giveaway,
	 *     'entries_by_source' => array(
	 *         'registered'        => (int) base entry awarded for registering,
	 *         'invite_visited'    => (int) entries from unique invite-link visits,
	 *         'invite_registered' => (int) entries from referred registrations,
	 *         'extra_entries'     => (int) entries awarded via gawg_add_extra_entries,
	 *     ),
	 *   )
	 *
	 * The per-source breakdown is derived from the stored entry-count meta (registration base,
	 * unique-visit markers and referred-registration markers), not from the action history log.
	 * The `extra_entries` bucket is the remainder once the other known sources are accounted for.
	 *
	 * @param string $email Email address to look up.
	 * @return array List of giveaway participation records; empty array for an unknown or empty email.
	 */
	public static function get_giveaways_for_email( $email ) {
		$email = strtolower( sanitize_email( (string) $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return array();
		}

		$participants = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'title'          => $email,
			'posts_per_page' => -1,
		) );

		if ( empty( $participants ) ) {
			return array();
		}

		$unique_visit_amount = GAWG_Settings::get_extra_entries_unique_visit();
		$registration_amount = GAWG_Settings::get_extra_entries_registration();

		$results = array();

		foreach ( $participants as $participant ) {
			$terms = wp_get_object_terms( $participant->ID, GAWG_Giveaway::TAXONOMY );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$giveaway_uuid = get_term_meta( $term->term_id, GAWG_Giveaway::META_UUID, true );
				if ( '' === $giveaway_uuid || isset( $results[ $term->term_id ] ) ) {
					continue;
				}

				$total = (int) get_post_meta( $participant->ID, self::META_ENTRIES_PREFIX . $giveaway_uuid, true );
				if ( $total < 1 ) {
					$total = 1;
				}

				list( $status, $status_label ) = self::giveaway_status( $term->term_id );

				$results[ $term->term_id ] = array(
					'giveaway_uuid'     => $giveaway_uuid,
					'giveaway_title'    => $term->name,
					'status'            => $status,
					'status_label'      => $status_label,
					'total_entries'     => $total,
					'entries_by_source' => self::entries_breakdown_for_giveaway(
						$participant->ID,
						$giveaway_uuid,
						$total,
						$unique_visit_amount,
						$registration_amount
					),
				);
			}
		}

		return array_values( $results );
	}

	/**
	 * Aggregate participant action-history entries for a single giveaway.
	 *
	 * Resolves the giveaway by its reference UUID, iterates the participant posts linked to it,
	 * reads each participant's stored history_N action-log entries, and returns a flat array of
	 * records sorted by timestamp descending (most recent first). When an $email is supplied the
	 * aggregation is restricted to that participant's history only; when omitted every participant
	 * in the giveaway (verified and unverified) is included across every tracked action type.
	 *
	 * This method produces no output and performs no ownership check on the supplied email, so the
	 * caller is responsible for verifying that the visitor is entitled to view the requested data.
	 *
	 * Each element has the shape:
	 *   array(
	 *     'datetime'          => (string) ISO-8601 UTC timestamp of the action,
	 *     'action'            => (string) action name (e.g. 'registered', 'verified'),
	 *     'participant_email' => (string) participant email address,
	 *     'participant_uuid'  => (string) participant reference UUID,
	 *   )
	 *
	 * @param string $giveaway_uuid Giveaway reference UUID.
	 * @param string $email         Optional email to restrict the log to a single participant.
	 * @return array List of action-log records ordered newest-first; empty array for an unknown
	 *               giveaway, an unmatched email, or a giveaway with no recorded actions.
	 */
	public static function get_action_logs( $giveaway_uuid, $email = '' ) {
		$giveaway_uuid = sanitize_text_field( (string) $giveaway_uuid );
		if ( '' === $giveaway_uuid ) {
			return array();
		}

		$terms = get_terms( array(
			'taxonomy'   => GAWG_Giveaway::TAXONOMY,
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'   => GAWG_Giveaway::META_UUID,
					'value' => $giveaway_uuid,
				),
			),
			'number'     => 1,
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$term = $terms[0];

		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => GAWG_Giveaway::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
		);

		$email = strtolower( sanitize_email( (string) $email ) );
		if ( '' !== $email ) {
			if ( ! is_email( $email ) ) {
				return array();
			}
			$query_args['title'] = $email;
		}

		$participants = get_posts( $query_args );
		if ( empty( $participants ) ) {
			return array();
		}

		$logs = array();
		foreach ( $participants as $participant ) {
			$participant_uuid = (string) get_post_meta( $participant->ID, self::META_UUID, true );
			foreach ( GAWG_History::get_all( $participant->ID ) as $entry ) {
				$logs[] = array(
					'datetime'          => isset( $entry['datetime'] ) ? (string) $entry['datetime'] : '',
					'action'            => isset( $entry['action'] ) ? (string) $entry['action'] : '',
					'participant_email' => $participant->post_title,
					'participant_uuid'  => $participant_uuid,
				);
			}
		}

		usort(
			$logs,
			function( $a, $b ) {
				return strcmp( (string) $b['datetime'], (string) $a['datetime'] );
			}
		);

		return $logs;
	}

	/**
	 * Reconstruct an entry breakdown by source from a participant's stored meta.
	 *
	 * @param int    $participant_id      Participant post ID.
	 * @param string $giveaway_uuid       Giveaway reference UUID.
	 * @param int    $total               Total entry count for the giveaway.
	 * @param int    $unique_visit_amount Entries awarded per unique invite-link visit.
	 * @param int    $registration_amount Entries awarded per referred registration.
	 * @return array Breakdown keyed by source.
	 */
	private static function entries_breakdown_for_giveaway( $participant_id, $giveaway_uuid, $total, $unique_visit_amount, $registration_amount ) {
		$all_meta = get_post_meta( $participant_id );

		$visit_prefix   = self::META_VISIT_PREFIX . $giveaway_uuid . '_';
		$invitee_prefix = 'gawg_invitee_' . $giveaway_uuid . '_';

		$visit_count   = 0;
		$invitee_count = 0;

		foreach ( $all_meta as $key => $values ) {
			$value = is_array( $values ) && isset( $values[0] ) ? $values[0] : $values;

			if ( 0 === strpos( $key, $visit_prefix ) && '1' === (string) $value ) {
				$visit_count++;
			} elseif ( 0 === strpos( $key, $invitee_prefix ) && 'registered' === (string) $value ) {
				$invitee_count++;
			}
		}

		$registered        = 1;
		$invite_visited    = $visit_count * $unique_visit_amount;
		$invite_registered = $invitee_count * $registration_amount;
		$extra_entries     = max( 0, $total - $registered - $invite_visited - $invite_registered );

		return array(
			'registered'        => $registered,
			'invite_visited'    => $invite_visited,
			'invite_registered' => $invite_registered,
			'extra_entries'     => $extra_entries,
		);
	}

	/**
	 * Resolve a giveaway term's status.
	 *
	 * @param int $term_id Giveaway term ID.
	 * @return array{0:string,1:string} Machine status key and translated label.
	 */
	private static function giveaway_status( $term_id ) {
		$winner_id = (int) get_term_meta( $term_id, GAWG_Giveaway::META_WINNER, true );
		$is_closed = '1' === get_term_meta( $term_id, GAWG_Giveaway::META_CLOSED, true );

		if ( $winner_id > 0 ) {
			return array( 'winner_drawn', __( 'Winner Drawn', 'gawg' ) );
		}
		if ( $is_closed ) {
			return array( 'closed', __( 'Closed', 'gawg' ) );
		}
		return array( 'active', __( 'Active', 'gawg' ) );
	}

	public static function add_entries_column( $columns ) {
		$columns['gawg_entries'] = __( 'Entries', 'gawg' );
		return $columns;
	}

	public static function render_entries_column( $column, $post_id ) {
		if ( 'gawg_entries' !== $column ) {
			return;
		}

		$terms = wp_get_object_terms( $post_id, GAWG_Giveaway::TAXONOMY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '—';
			return;
		}

		$parts = array();
		foreach ( $terms as $term ) {
			$giveaway_uuid = get_term_meta( $term->term_id, GAWG_Giveaway::META_UUID, true );
			$count         = '' !== $giveaway_uuid ? (int) get_post_meta( $post_id, self::META_ENTRIES_PREFIX . $giveaway_uuid, true ) : 0;
			if ( $count < 1 ) {
				$count = 1;
			}
			$parts[] = esc_html( $term->name ) . ': ' . $count;
		}
		echo implode( '<br>', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
