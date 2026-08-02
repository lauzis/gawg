<?php
/**
 * General log viewer.
 *
 * Separate from a participant's own history, which stays on the participant.
 *
 * @var string   $selected
 * @var array[]  $files
 * @var string[] $lines
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Logs', 'gawg' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Site-wide record of what the plugin did: emails sent or refused, entries awarded, winners drawn. A single entrant\'s own trail stays on their participant record.', 'gawg' ); ?>
	</p>

	<?php if ( empty( $files ) ) : ?>
		<p><?php esc_html_e( 'No log files yet.', 'gawg' ); ?></p>
	<?php else : ?>
		<form method="get" style="display:inline-block;margin:12px 0;">
			<input type="hidden" name="page" value="gawg-logs">
			<label for="gawg-log-date" class="screen-reader-text"><?php esc_html_e( 'Log date', 'gawg' ); ?></label>
			<select name="log_date" id="gawg-log-date" onchange="this.form.submit()">
				<?php foreach ( $files as $file ) : ?>
					<option value="<?php echo esc_attr( $file['date'] ); ?>" <?php selected( $selected, $file['date'] ); ?>>
						<?php
						printf(
							/* translators: 1: date, 2: number of entries */
							esc_html__( '%1$s (%2$d entries)', 'gawg' ),
							esc_html( $file['date'] ),
							(int) $file['count']
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>

		<form method="post" style="display:inline-block;margin-left:8px;">
			<?php wp_nonce_field( 'gawg_clear_logs', 'gawg_clear_logs_nonce' ); ?>
			<input type="hidden" name="action" value="clear_logs">
			<button type="submit" class="button button-secondary"
					onclick="return confirm('<?php echo esc_js( __( 'Delete every log file? This cannot be undone.', 'gawg' ) ); ?>');">
				<?php esc_html_e( 'Clear All', 'gawg' ); ?>
			</button>
		</form>

		<table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
			<thead>
				<tr>
					<th style="width:160px;"><?php esc_html_e( 'Time (UTC)', 'gawg' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Action', 'gawg' ); ?></th>
					<th><?php esc_html_e( 'Message', 'gawg' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $lines ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'This log file is empty.', 'gawg' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $lines as $line ) : ?>
					<?php
					// [2026-08-02 09:14:02] [action] message | {"json":"context"}
					preg_match( '/^\[([^\]]*)\]\s*(?:\[([^\]]*)\])?\s*(.*)$/', $line, $m );
					?>
					<tr>
						<td><code><?php echo esc_html( isset( $m[1] ) ? $m[1] : '' ); ?></code></td>
						<td><?php
							$action = isset( $m[2] ) ? $m[2] : '';
							echo '' === $action ? '<em style="color:#999;">&mdash;</em>' : '<code>' . esc_html( $action ) . '</code>';
						?></td>
						<td style="word-break:break-word;"><?php echo esc_html( isset( $m[3] ) ? $m[3] : $line ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
