<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Giveaway {

	const TAXONOMY    = 'gawg_giveaway';
	const META_UUID   = '_gawg_uuid';
	const META_WINNER = '_gawg_winner';
	const META_CLOSED = '_gawg_closed';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'maybe_generate_uuid' ) );
		add_action( 'edited_' . self::TAXONOMY,  array( __CLASS__, 'maybe_generate_uuid' ) );
		add_action( 'edited_' . self::TAXONOMY,  array( __CLASS__, 'save_closed_meta' ) );
		add_action( self::TAXONOMY . '_add_form_fields',  array( __CLASS__, 'render_uuid_field_new' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'render_uuid_field_edit' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'render_winner_field_edit' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'render_closed_field_edit' ) );
		add_filter( 'manage_edit-' . self::TAXONOMY . '_columns',          array( __CLASS__, 'add_status_column' ) );
		add_filter( 'manage_' . self::TAXONOMY . '_custom_column',         array( __CLASS__, 'render_status_column' ), 10, 3 );
		add_filter( 'manage_edit-' . self::TAXONOMY . '_sortable_columns', array( __CLASS__, 'make_status_column_sortable' ) );
		add_filter( 'terms_clauses',                                        array( __CLASS__, 'handle_status_sort' ), 10, 3 );
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
		register_term_meta(
			self::TAXONOMY,
			self::META_WINNER,
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
		register_term_meta(
			self::TAXONOMY,
			self::META_CLOSED,
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

	public static function render_winner_field_edit( $term ) {
		$winner_id = (int) get_term_meta( $term->term_id, self::META_WINNER, true );
		if ( 0 === $winner_id ) {
			return;
		}
		$winner_post  = get_post( $winner_id );
		$winner_email = $winner_post ? $winner_post->post_title : __( 'Participant not found.', 'gawg' );
		?>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Winner', 'gawg' ); ?></label></th>
			<td>
				<input
					type="text"
					readonly
					value="<?php echo esc_attr( $winner_email ); ?>"
					style="width:100%;font-family:monospace;"
				/>
				<p class="description"><?php esc_html_e( 'The winner drawn for this giveaway.', 'gawg' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function save_closed_meta( $term_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$closed = isset( $_POST['gawg_closed'] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified by WP before edited_ fires
		update_term_meta( $term_id, self::META_CLOSED, $closed );
	}

	public static function render_closed_field_edit( $term ) {
		$is_closed = '1' === get_term_meta( $term->term_id, self::META_CLOSED, true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="gawg-closed"><?php esc_html_e( 'Closed for Participants', 'gawg' ); ?></label>
			</th>
			<td>
				<input
					type="checkbox"
					id="gawg-closed"
					name="gawg_closed"
					value="1"
					<?php checked( $is_closed ); ?>
				/>
				<label for="gawg-closed"><?php esc_html_e( 'Check to prevent new entries for this giveaway.', 'gawg' ); ?></label>
			</td>
		</tr>
		<?php
	}

	public static function add_status_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $value ) {
			if ( 'posts' === $key ) {
				$new['gawg_status'] = __( 'Status', 'gawg' );
			}
			$new[ $key ] = $value;
		}
		return $new;
	}

	public static function render_status_column( $string, $column_name, $term_id ) {
		if ( 'gawg_status' !== $column_name ) {
			return $string;
		}

		$winner_id = (int) get_term_meta( $term_id, self::META_WINNER, true );
		$is_closed = '1' === get_term_meta( $term_id, self::META_CLOSED, true );

		if ( $winner_id > 0 ) {
			$label = esc_html__( 'Winner Drawn', 'gawg' );
			$color = '#7e3af2';
		} elseif ( $is_closed ) {
			$label = esc_html__( 'Closed', 'gawg' );
			$color = '#d97706';
		} else {
			$label = esc_html__( 'Active', 'gawg' );
			$color = '#16a34a';
		}

		return sprintf(
			'<span style="display:inline-block;padding:2px 8px;border-radius:4px;background:%s;color:#fff;font-size:12px;font-weight:600;">%s</span>',
			esc_attr( $color ),
			$label
		);
	}

	public static function make_status_column_sortable( $sortable_columns ) {
		$sortable_columns['gawg_status'] = 'gawg_status';
		return $sortable_columns;
	}

	public static function handle_status_sort( $clauses, $taxonomies, $args ) {
		global $wpdb;

		if ( ! in_array( self::TAXONOMY, (array) $taxonomies, true ) ) {
			return $clauses;
		}
		if ( ! isset( $args['orderby'] ) || 'gawg_status' !== $args['orderby'] ) {
			return $clauses;
		}

		$order = ( isset( $args['order'] ) && 'DESC' === strtoupper( $args['order'] ) ) ? 'DESC' : 'ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clauses['join'] .= $wpdb->prepare( " LEFT JOIN {$wpdb->termmeta} AS gawg_tm_winner ON (t.term_id = gawg_tm_winner.term_id AND gawg_tm_winner.meta_key = %s)", self::META_WINNER );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clauses['join'] .= $wpdb->prepare( " LEFT JOIN {$wpdb->termmeta} AS gawg_tm_closed ON (t.term_id = gawg_tm_closed.term_id AND gawg_tm_closed.meta_key = %s)", self::META_CLOSED );

		// $order is hardcoded to ASC or DESC above, so safe to interpolate.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$clauses['orderby'] = "ORDER BY CASE WHEN CAST(IFNULL(gawg_tm_winner.meta_value,'0') AS UNSIGNED) > 0 THEN 2 WHEN gawg_tm_closed.meta_value = '1' THEN 1 ELSE 0 END {$order}";

		return $clauses;
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
