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
