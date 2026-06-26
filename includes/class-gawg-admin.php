<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Admin {

	private static $tests = array(
		'create_participant_has_uuid'    => 'Create Participant Has UUID',
		'create_giveaway_term_has_uuid'  => 'Create Giveaway Term Has UUID',
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
			__( 'Participants', 'gawg' ),
			__( 'Participants', 'gawg' ),
			'manage_options',
			'edit.php?post_type=' . GAWG_Participant::POST_TYPE
		);
		add_submenu_page(
			'gawg',
			__( 'Add New Participant', 'gawg' ),
			__( 'Add New', 'gawg' ),
			'manage_options',
			'post-new.php?post_type=' . GAWG_Participant::POST_TYPE
		);
		add_submenu_page(
			'gawg',
			__( 'Giveaways', 'gawg' ),
			__( 'Giveaways', 'gawg' ),
			'manage_options',
			'edit-tags.php?taxonomy=' . GAWG_Giveaway::TAXONOMY . '&post_type=' . GAWG_Participant::POST_TYPE
		);
		add_submenu_page(
			'gawg',
			__( 'Self Tests', 'gawg' ),
			__( 'Self Tests', 'gawg' ),
			'manage_options',
			'gawg',
			array( __CLASS__, 'render_self_tests_page' )
		);
		add_submenu_page(
			'gawg',
			__( 'Help', 'gawg' ),
			__( 'Help', 'gawg' ),
			'manage_options',
			'gawg-help',
			array( __CLASS__, 'render_help_page' )
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
				case 'create_participant_has_uuid':
					$results[] = self::test_create_participant_has_uuid();
					break;
				case 'create_giveaway_term_has_uuid':
					$results[] = self::test_create_giveaway_term_has_uuid();
					break;
			}
		}

		wp_send_json_success( $results );
	}

	public static function render_help_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'gawg' ) );
		}
		$new_participant_url   = admin_url( 'post-new.php?post_type=' . GAWG_Participant::POST_TYPE );
		$all_participants_url  = admin_url( 'edit.php?post_type=' . GAWG_Participant::POST_TYPE );
		$new_giveaway_url      = admin_url( 'edit-tags.php?taxonomy=' . GAWG_Giveaway::TAXONOMY . '&post_type=' . GAWG_Participant::POST_TYPE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'GAWG Help', 'gawg' ); ?></h1>

			<h2><?php esc_html_e( 'Creating a Giveaway', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'Each giveaway is a taxonomy term. Create a giveaway first to obtain its unique reference UUID, then add participants and assign them to that giveaway.', 'gawg' ); ?>
			</p>
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: link to the Giveaways taxonomy screen */
						wp_kses(
							__( 'Go to <a href="%s">Giveaways</a> and add a new giveaway with a descriptive name.', 'gawg' ),
							array( 'a' => array( 'href' => array() ) )
						),
						esc_url( $new_giveaway_url )
					);
					?>
				</li>
				<li><?php esc_html_e( 'Click Add New Giveaway. A unique UUID is automatically assigned and shown in the Reference UUID field when you next edit the term.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'Share the UUID or an entry link with your audience so they can participate.', 'gawg' ); ?></li>
			</ol>

			<h2><?php esc_html_e( 'Adding Participants', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'Participants are posts that belong to one or more giveaways via the Giveaway taxonomy. Each participant also receives a unique reference UUID.', 'gawg' ); ?>
			</p>
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: link to the Add New Participant screen */
						wp_kses(
							__( 'Go to <a href="%s">Add New Participant</a> and enter the participant\'s name as the title.', 'gawg' ),
							array( 'a' => array( 'href' => array() ) )
						),
						esc_url( $new_participant_url )
					);
					?>
				</li>
				<li><?php esc_html_e( 'Assign the participant to one or more giveaways using the Giveaway panel on the right.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'Click Publish. A unique UUID is generated automatically and shown in the Reference UUID box.', 'gawg' ); ?></li>
			</ol>
			<p>
				<?php
				printf(
					/* translators: %s: link to the Participants list screen */
					wp_kses(
						__( 'You can view and manage all participants on the <a href="%s">Participants list</a> screen.', 'gawg' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( $all_participants_url )
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Embedding the Entry Form', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'The entry form can be inserted into any page or post using either the Giveaway Form block or the [gawg_form] shortcode. Both methods produce identical output — changing the shortcode output changes both automatically.', 'gawg' ); ?>
			</p>

			<h3><?php esc_html_e( 'Gutenberg Block', 'gawg' ); ?></h3>
			<p>
				<?php esc_html_e( 'Search for "Giveaway Form" in the block inserter and add it to your page. In the block settings panel (Inspector Controls) on the right:', 'gawg' ); ?>
			</p>
			<ul>
				<li><strong><?php esc_html_e( 'Giveaway', 'gawg' ); ?></strong> — <?php esc_html_e( 'Choose the giveaway campaign from the dropdown. Only giveaways with a Reference UUID are listed.', 'gawg' ); ?></li>
				<li><strong><?php esc_html_e( 'Rules URL', 'gawg' ); ?></strong> — <?php esc_html_e( '(optional) Enter the URL to the giveaway rules page. When set, a required acceptance checkbox is shown in the form.', 'gawg' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'The editor canvas shows a live preview of the form as it will appear to visitors.', 'gawg' ); ?></p>

			<h3><?php esc_html_e( 'Shortcode', 'gawg' ); ?></h3>
			<p><?php esc_html_e( 'Basic usage (copy the giveaway UUID from the Reference UUID field on its edit screen):', 'gawg' ); ?></p>
			<pre><code>[gawg_form uuid="your-giveaway-uuid"]</code></pre>
			<p><?php esc_html_e( 'With a rules link and a custom success message:', 'gawg' ); ?></p>
			<pre><code>[gawg_form uuid="your-giveaway-uuid" rules_url="https://example.com/rules" success_message="&lt;p&gt;You&#39;re in!&lt;/p&gt;"]</code></pre>
			<p><?php esc_html_e( 'Shortcode attributes:', 'gawg' ); ?></p>
			<ul>
				<li><strong>uuid</strong> — <?php esc_html_e( '(required) The Reference UUID of the giveaway term.', 'gawg' ); ?></li>
				<li><strong>rules_url</strong> — <?php esc_html_e( '(optional) Full URL to the giveaway rules page. When provided, a required checkbox is shown.', 'gawg' ); ?></li>
				<li><strong>rules_post_id</strong> — <?php esc_html_e( '(optional) Post ID of a rules page — an alternative to rules_url.', 'gawg' ); ?></li>
				<li><strong>success_message</strong> — <?php esc_html_e( '(optional) HTML shown after a successful submission. Defaults to a translatable thank-you message.', 'gawg' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'If the same email address is submitted for the same giveaway more than once, the form displays "You are already in the list of participants" and no duplicate entry is created.', 'gawg' ); ?></p>

			<h2><?php esc_html_e( 'Settings', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'The Settings page (GAWG → Settings) contains plugin-wide configuration options.', 'gawg' ); ?>
			</p>

			<h3><?php esc_html_e( 'Google reCAPTCHA v2', 'gawg' ); ?></h3>
			<p>
				<?php esc_html_e( 'To protect the entry form from spam, you can enable Google reCAPTCHA v2 (checkbox). When configured, a "I\'m not a robot" checkbox is rendered inside the form and submission is blocked until the challenge is solved.', 'gawg' ); ?>
			</p>
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: link to Google reCAPTCHA admin */
						wp_kses(
							__( 'Register your site at <a href="%s" target="_blank" rel="noopener noreferrer">google.com/recaptcha</a> and choose reCAPTCHA v2 (checkbox type).', 'gawg' ),
							array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
						),
						'https://www.google.com/recaptcha/admin/create'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Copy the Site Key and Secret Key from the reCAPTCHA admin panel.', 'gawg' ); ?></li>
				<li>
					<?php
					printf(
						/* translators: %s: link to the GAWG Settings page */
						wp_kses(
							__( 'Paste both keys into the <a href="%s">GAWG Settings</a> page and save.', 'gawg' ),
							array( 'a' => array( 'href' => array() ) )
						),
						esc_url( admin_url( 'admin.php?page=crb_gawg_settings.php' ) )
					);
					?>
				</li>
				<li><?php esc_html_e( 'The reCAPTCHA widget will now appear automatically in every [gawg_form] or Giveaway Form block on your site.', 'gawg' ); ?></li>
			</ol>
		</div>
		<?php
	}

	private static function test_create_participant_has_uuid() {
		$result = array(
			'id'      => 'create_participant_has_uuid',
			'name'    => 'Create Participant Has UUID',
			'pass'    => false,
			'message' => '',
		);

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'GAWG Self Test – ' . gmdate( 'Y-m-d H:i:s' ),
				'post_type'   => GAWG_Participant::POST_TYPE,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$result['message'] = 'Failed to insert post: ' . $post_id->get_error_message();
			return $result;
		}

		$uuid = get_post_meta( $post_id, GAWG_Participant::META_UUID, true );

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

	private static function test_create_giveaway_term_has_uuid() {
		$result = array(
			'id'      => 'create_giveaway_term_has_uuid',
			'name'    => 'Create Giveaway Term Has UUID',
			'pass'    => false,
			'message' => '',
		);

		$term = wp_insert_term(
			'GAWG Self Test – ' . gmdate( 'Y-m-d H:i:s' ),
			GAWG_Giveaway::TAXONOMY
		);

		if ( is_wp_error( $term ) ) {
			$result['message'] = 'Failed to insert term: ' . $term->get_error_message();
			return $result;
		}

		$term_id = $term['term_id'];
		$uuid    = get_term_meta( $term_id, GAWG_Giveaway::META_UUID, true );

		wp_delete_term( $term_id, GAWG_Giveaway::TAXONOMY );

		if ( '' === $uuid ) {
			$result['message'] = 'UUID meta was empty after saving.';
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'UUID: ' . $uuid . ' — Term deleted successfully.';
		return $result;
	}
}
