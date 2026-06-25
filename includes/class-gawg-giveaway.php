<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Giveaway {

	const TAXONOMY = 'gawg_giveaway';
	const META_UUID = '_gawg_uuid';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'maybe_generate_uuid' ) );
		add_action( 'edited_' . self::TAXONOMY,  array( __CLASS__, 'maybe_generate_uuid' ) );
		add_action( self::TAXONOMY . '_add_form_fields',  array( __CLASS__, 'render_uuid_field_new' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'render_uuid_field_edit' ) );
	}

	public static function register_taxonomy() {
		$labels = array(
			'name'          => __( 'Giveaways', 'gawg' ),
			'singular_name' => __( 'Giveaway', 'gawg' ),
			'search_items'  => __( 'Search Giveaways', 'gawg' ),
			'all_items'     => __( 'All Giveaways', 'gawg' ),
			'edit_item'     => __( 'Edit Giveaway', 'gawg' ),
			'update_item'   => __( 'Update Giveaway', 'gawg' ),
			'add_new_item'  => __( 'Add New Giveaway', 'gawg' ),
			'new_item_name' => __( 'New Giveaway Name', 'gawg' ),
			'menu_name'     => __( 'Giveaways', 'gawg' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			GAWG_Participant::POST_TYPE,
			array(
				'labels'            => $labels,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'manage_options',
				),
			)
		);
	}

	public static function register_meta() {
		register_term_meta(
			self::TAXONOMY,
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

	public static function maybe_generate_uuid( $term_id ) {
		$existing = get_term_meta( $term_id, self::META_UUID, true );
		if ( '' !== $existing ) {
			return;
		}
		update_term_meta( $term_id, self::META_UUID, wp_generate_uuid4() );
	}

	public static function render_uuid_field_new( $taxonomy ) {
		?>
		<div class="form-field">
			<label><?php esc_html_e( 'Reference UUID', 'gawg' ); ?></label>
			<p><?php esc_html_e( 'UUID will be generated automatically when the giveaway is saved.', 'gawg' ); ?></p>
		</div>
		<?php
	}

	public static function render_uuid_field_edit( $term ) {
		$uuid = get_term_meta( $term->term_id, self::META_UUID, true );
		?>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Reference UUID', 'gawg' ); ?></label></th>
			<td>
				<?php if ( '' === $uuid ) : ?>
					<p><?php esc_html_e( 'UUID will be generated upon saving.', 'gawg' ); ?></p>
				<?php else : ?>
					<input
						type="text"
						readonly
						value="<?php echo esc_attr( $uuid ); ?>"
						style="width:100%;font-family:monospace;"
						onclick="this.select();"
					/>
					<p class="description"><?php esc_html_e( 'Use this UUID in external forms or links to reference this giveaway.', 'gawg' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
