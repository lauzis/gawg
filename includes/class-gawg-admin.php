<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Admin {

	private static $tests = array(
		'create_giveaway_has_uuid' => 'Create Giveaway Has UUID',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'wp_ajax_gawg_run_self_tests', array( __CLASS__, 'ajax_run_self_tests' ) );
	}

	public static function register_menus() {
		add_menu_page(
			__( 'GAWG', 'gawg' ),
			__( 'GAWG', 'gawg' ),
			'manage_options',
			'gawg',
			array( __CLASS__, 'render_self_tests_page' ),
			'dashicons-awards',
			25
		);
		add_submenu_page(
			'gawg',
			__( 'Self Tests', 'gawg' ),
			__( 'Self Tests', 'gawg' ),
			'manage_options',
			'gawg',
			array( __CLASS__, 'render_self_tests_page' )
		);
	}

	public static function render_self_tests_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'gawg' ) );
		}
		$nonce = wp_create_nonce( 'gawg_self_tests' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'GAWG Self Tests', 'gawg' ); ?></h1>
			<form id="gawg-self-tests-form">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:40px;">
								<input type="checkbox" id="gawg-select-all" checked>
							</th>
							<th><?php esc_html_e( 'Test Name', 'gawg' ); ?></th>
							<th><?php esc_html_e( 'Result', 'gawg' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( self::$tests as $id => $name ) : ?>
						<tr id="gawg-test-row-<?php echo esc_attr( $id ); ?>">
							<td>
								<input type="checkbox" name="tests[]" value="<?php echo esc_attr( $id ); ?>" checked>
							</td>
							<td><?php echo esc_html( $name ); ?></td>
							<td class="gawg-test-result" style="font-family:monospace;">—</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Run Selected', 'gawg' ); ?>
					</button>
					<span id="gawg-running" style="display:none;margin-left:10px;vertical-align:middle;">
						<span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
						<?php esc_html_e( 'Running…', 'gawg' ); ?>
					</span>
				</p>
			</form>
		</div>
		<script>
		(function () {
			var selectAll = document.getElementById('gawg-select-all');
			selectAll.addEventListener('change', function () {
				document.querySelectorAll('#gawg-self-tests-form input[name="tests[]"]').forEach(function (cb) {
					cb.checked = selectAll.checked;
				});
			});

			document.getElementById('gawg-self-tests-form').addEventListener('submit', function (e) {
				e.preventDefault();

				var checked = Array.from(
					document.querySelectorAll('#gawg-self-tests-form input[name="tests[]"]:checked')
				).map(function (cb) { return cb.value; });

				if (!checked.length) {
					alert(<?php echo wp_json_encode( __( 'Please select at least one test.', 'gawg' ) ); ?>);
					return;
				}

				document.querySelectorAll('.gawg-test-result').forEach(function (el) {
					el.textContent = '—';
					el.style.color = '';
				});

				var submitBtn = document.querySelector('#gawg-self-tests-form button[type="submit"]');
				submitBtn.disabled = true;
				document.getElementById('gawg-running').style.display = 'inline-block';

				var data = new FormData();
				data.append('action', 'gawg_run_self_tests');
				data.append('nonce', <?php echo wp_json_encode( $nonce ); ?>);
				checked.forEach(function (id) { data.append('tests[]', id); });

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function (r) { return r.json(); })
					.then(function (response) {
						if (response.success && Array.isArray(response.data)) {
							response.data.forEach(function (result) {
								var cell = document.querySelector('#gawg-test-row-' + result.id + ' .gawg-test-result');
								if (cell) {
									cell.textContent = (result.pass ? '✓ ' : '✗ ') + result.message;
									cell.style.color  = result.pass ? 'green' : '#cc0000';
								}
							});
						}
					})
					.finally(function () {
						submitBtn.disabled = false;
						document.getElementById('gawg-running').style.display = 'none';
					});
			});
		})();
		</script>
		<?php
	}

	public static function ajax_run_self_tests() {
		check_ajax_referer( 'gawg_self_tests', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$requested = isset( $_POST['tests'] ) ? (array) $_POST['tests'] : array();
		$requested = array_map( 'sanitize_key', $requested );

		$results = array();
		foreach ( $requested as $test_id ) {
			if ( ! array_key_exists( $test_id, self::$tests ) ) {
				continue;
			}
			switch ( $test_id ) {
				case 'create_giveaway_has_uuid':
					$results[] = self::test_create_giveaway_has_uuid();
					break;
			}
		}

		wp_send_json_success( $results );
	}

	private static function test_create_giveaway_has_uuid() {
		$result = array(
			'id'      => 'create_giveaway_has_uuid',
			'name'    => 'Create Giveaway Has UUID',
			'pass'    => false,
			'message' => '',
		);

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'GAWG Self Test – ' . gmdate( 'Y-m-d H:i:s' ),
				'post_type'   => GAWG_Giveaway::POST_TYPE,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$result['message'] = 'Failed to insert post: ' . $post_id->get_error_message();
			return $result;
		}

		$uuid = get_post_meta( $post_id, GAWG_Giveaway::META_UUID, true );

		wp_delete_post( $post_id, true );

		if ( '' === $uuid ) {
			$result['message'] = 'UUID meta was empty after saving.';
			return $result;
		}

		if ( get_post( $post_id ) ) {
			$result['message'] = 'Post was not deleted (UUID was: ' . $uuid . ').';
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'UUID: ' . $uuid . ' — Post deleted successfully.';
		return $result;
	}
}
