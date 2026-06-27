<?php
defined( 'ABSPATH' ) || exit;

class GAWG_History {

	const META_PREFIX = 'history_';

	public static function append( $participant_id, $action ) {
		$all_meta = get_post_meta( $participant_id );
		$n        = 0;
		if ( is_array( $all_meta ) ) {
			foreach ( array_keys( $all_meta ) as $key ) {
				if ( preg_match( '/^history_(\d+)$/', $key, $m ) ) {
					$n = max( $n, (int) $m[1] );
				}
			}
		}
		$n++;
		add_post_meta(
			$participant_id,
			self::META_PREFIX . $n,
			wp_json_encode(
				array(
					'datetime' => gmdate( 'c' ),
					'action'   => $action,
				)
			)
		);
	}

	public static function get_all( $participant_id ) {
		$all_meta = get_post_meta( $participant_id );
		$entries  = array();
		if ( ! is_array( $all_meta ) ) {
			return $entries;
		}
		foreach ( $all_meta as $key => $values ) {
			if ( preg_match( '/^history_(\d+)$/', $key, $m ) ) {
				$decoded = json_decode( $values[0], true );
				if ( is_array( $decoded ) ) {
					$entries[ (int) $m[1] ] = $decoded;
				}
			}
		}
		ksort( $entries );
		return $entries;
	}

	public static function render_meta_box( $post ) {
		$entries = self::get_all( $post->ID );
		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'No history recorded yet.', 'gawg' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="width:100%;">
			<thead>
				<tr>
					<th style="width:40px;">#</th>
					<th><?php esc_html_e( 'Datetime (UTC)', 'gawg' ); ?></th>
					<th><?php esc_html_e( 'Action', 'gawg' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $n => $entry ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $n ); ?></td>
					<td style="font-family:monospace;"><?php echo esc_html( isset( $entry['datetime'] ) ? $entry['datetime'] : '' ); ?></td>
					<td style="font-family:monospace;"><?php echo esc_html( isset( $entry['action'] ) ? $entry['action'] : '' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
