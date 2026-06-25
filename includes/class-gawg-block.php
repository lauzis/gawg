<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Block {

	public static function init() {
		add_action( 'init',          array( __CLASS__, 'register_block' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	public static function register_block() {
		wp_register_script(
			'gawg-form-block-editor',
			GAWG_PLUGIN_URL . 'assets/js/gawg-block-editor.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-server-side-render', 'wp-api-fetch', 'wp-i18n' ),
			GAWG_VERSION
		);

		register_block_type(
			GAWG_PLUGIN_DIR . 'blocks/gawg-form/',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	public static function render_block( $attributes ) {
		$uuid = isset( $attributes['giveaway_uuid'] ) ? sanitize_text_field( $attributes['giveaway_uuid'] ) : '';
		if ( '' === $uuid ) {
			return '';
		}

		$rules_url     = isset( $attributes['rules_url'] )     ? esc_url_raw( $attributes['rules_url'] )  : '';
		$rules_post_id = isset( $attributes['rules_post_id'] ) ? (int) $attributes['rules_post_id']        : 0;

		$shortcode = '[gawg_form uuid="' . esc_attr( $uuid ) . '"';
		if ( '' !== $rules_url ) {
			$shortcode .= ' rules_url="' . esc_attr( $rules_url ) . '"';
		} elseif ( $rules_post_id > 0 ) {
			$shortcode .= ' rules_post_id="' . $rules_post_id . '"';
		}
		$shortcode .= ']';

		return do_shortcode( $shortcode );
	}

	public static function register_rest_routes() {
		register_rest_route(
			'gawg/v1',
			'/giveaways',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_giveaways' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public static function rest_get_giveaways() {
		$terms = get_terms( array(
			'taxonomy'   => GAWG_Giveaway::TAXONOMY,
			'hide_empty' => false,
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$options = array();
		foreach ( $terms as $term ) {
			$uuid = get_term_meta( $term->term_id, GAWG_Giveaway::META_UUID, true );
			if ( '' !== $uuid ) {
				$options[] = array(
					'value' => $uuid,
					'label' => $term->name . ' (' . $uuid . ')',
				);
			}
		}

		return $options;
	}
}
