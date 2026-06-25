<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Participant {

	const POST_TYPE = 'gawg_participant';
	const META_UUID = '_gawg_uuid';

	public static function init() {
		add_action( 'init',       array( __CLASS__, 'register_post_type' ) );
		add_action( 'init',       array( __CLASS__, 'register_meta' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'maybe_generate_uuid' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_uuid_meta_box' ) );
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
}
