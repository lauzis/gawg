<?php
defined( 'ABSPATH' ) || exit;

class GAWG_Admin {

	const META_TEST_FLAG = '_gawg_is_test';

	private static $tests = array(
		'create_participant_has_uuid'         => 'Create Participant Has UUID',
		'create_giveaway_term_has_uuid'       => 'Create Giveaway Term Has UUID',
		'link_participant_single_giveaway'    => 'Participant Linked to Single Giveaway',
		'link_participant_multiple_giveaways' => 'Participant Linked to Multiple Giveaways',
		'entries_default_one'                 => 'Entries Default to 1 After Form Submit',
		'invite_url_format'                   => 'Invite URL Contains Correct Query Params',
		'new_ip_increments_entries'           => 'New IP Increments Entry Count',
		'duplicate_ip_no_increment'           => 'Duplicate IP Does Not Double-Increment Entries',
		'verification_sent_at_set'            => 'Verification Email Sets Sent-At Timestamp',
		'verification_sets_verified_flag'     => 'Verification Link Sets Verified Flag',
		'expired_verification_detected'       => 'Expired Verification Link Is Detected',
		'already_verified_skips_reverify'     => 'Already-Verified Participant Is Not Re-Verified',
		'history_registration_recorded'       => 'History: Registration Is Recorded',
		'history_verification_email_recorded' => 'History: Verification Email Is Recorded',
		'history_verified_recorded'           => 'History: Verified Action Is Recorded',
		'history_invite_visited_recorded'     => 'History: Invite Visited Is Recorded',
		'history_invite_registered_recorded'  => 'History: Invite Registered Is Recorded',
	);

	public static function init() {
		add_action( 'admin_menu',                           array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts',                array( __CLASS__, 'enqueue_draw_winner_scripts' ) );
		add_action( 'wp_ajax_gawg_run_self_tests',          array( __CLASS__, 'ajax_run_self_tests' ) );
		add_action( 'wp_ajax_gawg_load_participants',       array( __CLASS__, 'ajax_load_participants' ) );
		add_action( 'wp_ajax_gawg_pick_winner',             array( __CLASS__, 'ajax_pick_winner' ) );
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
			__( 'Draw Winner', 'gawg' ),
			__( 'Draw Winner', 'gawg' ),
			'manage_options',
			'gawg-draw-winner',
			array( __CLASS__, 'render_draw_winner_page' )
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

	public static function enqueue_draw_winner_scripts( $hook ) {
		if ( 'gawg_page_gawg-draw-winner' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'gawg-draw-winner',
			GAWG_PLUGIN_URL . 'assets/js/gawg-draw-winner.js',
			array(),
			GAWG_VERSION,
			true
		);
		wp_localize_script(
			'gawg-draw-winner',
			'gawgDrawWinner',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gawg_draw_winner' ),
				'i18n'    => array(
					'loading'        => __( 'Loading participants…', 'gawg' ),
					'noParticipants' => __( 'No participants found for this giveaway.', 'gawg' ),
					'shuffling'      => __( 'Shuffling…', 'gawg' ),
					'networkError'   => __( 'A network error occurred. Please try again.', 'gawg' ),
					'selectGiveaway' => __( 'Please select a giveaway.', 'gawg' ),
				),
			)
		);
	}

	public static function render_draw_winner_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'gawg' ) );
		}

		$giveaways = get_terms( array(
			'taxonomy'   => GAWG_Giveaway::TAXONOMY,
			'hide_empty' => false,
		) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Draw Winner', 'gawg' ); ?></h1>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="gawg-giveaway-select"><?php esc_html_e( 'Giveaway', 'gawg' ); ?></label>
					</th>
					<td>
						<select id="gawg-giveaway-select">
							<option value=""><?php esc_html_e( '— Select a giveaway —', 'gawg' ); ?></option>
							<?php if ( ! is_wp_error( $giveaways ) ) : ?>
								<?php foreach ( $giveaways as $term ) : ?>
									<option value="<?php echo esc_attr( $term->term_id ); ?>">
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gawg-shuffle-count"><?php esc_html_e( 'Shuffle count', 'gawg' ); ?></label>
					</th>
					<td>
						<input type="number" id="gawg-shuffle-count" value="10" min="1" max="100" style="width:80px;">
						<p class="description"><?php esc_html_e( 'How many times to cycle through the list.', 'gawg' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gawg-shuffle-delay"><?php esc_html_e( 'Delay (ms)', 'gawg' ); ?></label>
					</th>
					<td>
						<input type="number" id="gawg-shuffle-delay" value="100" min="50" max="2000" style="width:80px;">
						<p class="description"><?php esc_html_e( 'Milliseconds between each shuffle step.', 'gawg' ); ?></p>
					</td>
				</tr>
			</table>

			<p>
				<button id="gawg-shuffle-btn" class="button button-primary" disabled>
					<?php esc_html_e( 'Shuffle & Pick Winner', 'gawg' ); ?>
				</button>
			</p>

			<div id="gawg-participants-wrap" style="display:none;margin-top:20px;">
				<h2><?php esc_html_e( 'Participants', 'gawg' ); ?></h2>
				<div id="gawg-highlighted-participant" style="font-size:24px;font-weight:bold;font-family:monospace;margin:10px 0;min-height:30px;"></div>
				<ul id="gawg-participants-list" style="max-height:300px;overflow-y:auto;font-family:monospace;background:#f8f8f8;padding:10px;border:1px solid #ddd;list-style:none;margin:0;"></ul>
			</div>

			<div id="gawg-winner-wrap" style="display:none;margin-top:20px;">
				<h2><?php esc_html_e( 'Winner', 'gawg' ); ?></h2>
				<div id="gawg-winner-display" style="font-size:32px;font-weight:bold;font-family:monospace;color:#2271b1;"></div>
			</div>

			<div id="gawg-status" style="margin-top:10px;color:#666;"></div>
		</div>
		<?php
	}

	public static function ajax_load_participants() {
		check_ajax_referer( 'gawg_draw_winner', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( 0 === $term_id ) {
			wp_send_json_error( 'Invalid giveaway.' );
		}

		$term = get_term( $term_id, GAWG_Giveaway::TAXONOMY );
		if ( is_wp_error( $term ) || null === $term ) {
			wp_send_json_error( 'Giveaway not found.' );
		}

		$posts = get_posts( array(
			'post_type'      => GAWG_Participant::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => GAWG_Giveaway::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		) );

		$participants = array();
		foreach ( $posts as $post ) {
			$participants[] = array(
				'id'    => $post->ID,
				'email' => self::mask_email( $post->post_title ),
			);
		}

		wp_send_json_success( $participants );
	}

	public static function ajax_pick_winner() {
		check_ajax_referer( 'gawg_draw_winner', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( 0 === $term_id ) {
			wp_send_json_error( 'Invalid giveaway.' );
		}

		$term = get_term( $term_id, GAWG_Giveaway::TAXONOMY );
		if ( is_wp_error( $term ) || null === $term ) {
			wp_send_json_error( 'Giveaway not found.' );
		}

		$post_ids = get_posts( array(
			'post_type'      => GAWG_Participant::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => GAWG_Giveaway::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
			'fields'         => 'ids',
		) );

		if ( empty( $post_ids ) ) {
			wp_send_json_error( 'No participants found.' );
		}

		$winner_id = $post_ids[ array_rand( $post_ids ) ];
		update_term_meta( $term_id, GAWG_Giveaway::META_WINNER, $winner_id );

		$winner_post  = get_post( $winner_id );
		$winner_email = $winner_post ? self::mask_email( $winner_post->post_title ) : '';

		wp_send_json_success( array(
			'winner_id'    => $winner_id,
			'winner_email' => $winner_email,
		) );
	}

	private static function mask_email( $email ) {
		$parts = explode( '@', $email, 2 );
		if ( 2 !== count( $parts ) ) {
			return $email;
		}
		$local  = $parts[0];
		$domain = $parts[1];
		if ( strlen( $local ) <= 1 ) {
			return $local . '****@' . $domain;
		}
		return $local[0] . '****' . $local[ strlen( $local ) - 1 ] . '@' . $domain;
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
				case 'link_participant_single_giveaway':
					$results[] = self::test_link_participant_single_giveaway();
					break;
				case 'link_participant_multiple_giveaways':
					$results[] = self::test_link_participant_multiple_giveaways();
					break;
				case 'entries_default_one':
					$results[] = self::test_entries_default_one();
					break;
				case 'invite_url_format':
					$results[] = self::test_invite_url_format();
					break;
				case 'new_ip_increments_entries':
					$results[] = self::test_new_ip_increments_entries();
					break;
				case 'duplicate_ip_no_increment':
					$results[] = self::test_duplicate_ip_no_increment();
					break;
				case 'verification_sent_at_set':
					$results[] = self::test_verification_sent_at_set();
					break;
				case 'verification_sets_verified_flag':
					$results[] = self::test_verification_sets_verified_flag();
					break;
				case 'expired_verification_detected':
					$results[] = self::test_expired_verification_detected();
					break;
				case 'already_verified_skips_reverify':
					$results[] = self::test_already_verified_skips_reverify();
					break;
				case 'history_registration_recorded':
					$results[] = self::test_history_registration_recorded();
					break;
				case 'history_verification_email_recorded':
					$results[] = self::test_history_verification_email_recorded();
					break;
				case 'history_verified_recorded':
					$results[] = self::test_history_verified_recorded();
					break;
				case 'history_invite_visited_recorded':
					$results[] = self::test_history_invite_visited_recorded();
					break;
				case 'history_invite_registered_recorded':
					$results[] = self::test_history_invite_registered_recorded();
					break;
			}
		}

		self::cleanup_test_data();

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

			<h2><?php esc_html_e( 'Creating a Giveaway Rules Page', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'A rules page describes the terms, eligibility, and prize details of your giveaway. While optional, it is strongly recommended: when a rules URL is provided, a required acceptance checkbox is shown in the entry form, giving participants informed consent.', 'gawg' ); ?>
			</p>
			<ol>
				<li><?php esc_html_e( 'In WordPress, go to Pages → Add New (or Posts → Add New if you prefer a post).', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'Write your giveaway rules: eligibility requirements, how to enter, prize details, draw date, and any other relevant terms.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'Publish the page and copy its URL (Permalink) from the address bar or the Permalink field in the editor.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'Use this URL as the rules_url attribute in the [gawg_form] shortcode, or paste it into the Rules URL field in the Giveaway Form block Inspector Controls.', 'gawg' ); ?></li>
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
				<li><strong>not_open_message</strong> — <?php esc_html_e( '(optional) Message shown when registration has not started yet (before the Registration Opens date). Defaults to "Registration is not open yet."', 'gawg' ); ?></li>
				<li><strong>closed_message</strong> — <?php esc_html_e( '(optional) Message shown when registration has ended (after the Registration Closes date). Defaults to "Registration is closed."', 'gawg' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'If the same email address is submitted for the same giveaway more than once, the form displays "You are already in the list of participants" and no duplicate entry is created.', 'gawg' ); ?></p>

			<h2><?php esc_html_e( 'Email Verification', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'When a participant submits the entry form, a verification email is sent to the address they provided. The participant must click the link in that email to confirm their registration.', 'gawg' ); ?>
			</p>
			<h3><?php esc_html_e( 'Verification flow', 'gawg' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Participant submits the form → a verification email is sent using the template configured in GAWG → Settings.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'Participant clicks the verification link within 24 hours → their email is marked as verified and a success email is sent.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'If the link has expired, the participant sees an error page with a "Resend verification link" button that sends a fresh verification email.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'If the participant is already verified and visits the link again, no additional email is sent and a confirmation message is shown.', 'gawg' ); ?></li>
			</ol>
			<h3><?php esc_html_e( 'Configuring email templates', 'gawg' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: link to the Settings page */
					wp_kses(
						__( 'Go to <a href="%s">GAWG → Settings</a> and scroll to the Email Templates section. Two templates are available:', 'gawg' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( admin_url( 'admin.php?page=crb_gawg_settings.php' ) )
				);
				?>
			</p>
			<ul>
				<li>
					<strong><?php esc_html_e( 'Verification Email Template', 'gawg' ); ?></strong> —
					<?php esc_html_e( 'Sent when a new participant registers. Available placeholders: {participant_email}, {giveaway_title}, {verification_link}', 'gawg' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Registration Success Email Template', 'gawg' ); ?></strong> —
					<?php esc_html_e( 'Sent after a participant verifies their email. Available placeholders: {participant_email}, {giveaway_title}, {rules_url}', 'gawg' ); ?>
				</li>
			</ul>
			<p><?php esc_html_e( 'The {rules_url} placeholder is populated from the Rules URL field on each giveaway\'s edit screen (GAWG → Giveaways). Set a Rules URL on the giveaway so participants can be linked directly to the rules page in their success email.', 'gawg' ); ?></p>
			<p><?php esc_html_e( 'HTML is accepted in both templates. The content is sanitized on display to prevent XSS.', 'gawg' ); ?></p>

			<h2><?php esc_html_e( 'Drawing a Winner', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'Once participants have entered, use the Draw Winner page to run the lottery.', 'gawg' ); ?>
			</p>
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: link to the Draw Winner admin page */
						wp_kses(
							__( 'Go to <a href="%s">Draw Winner</a>.', 'gawg' ),
							array( 'a' => array( 'href' => array() ) )
						),
						esc_url( admin_url( 'admin.php?page=gawg-draw-winner' ) )
					);
					?>
				</li>
				<li><?php esc_html_e( 'Select the active giveaway from the dropdown. The participant list loads automatically; email addresses are masked for privacy (first character, last character, and domain are shown).', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'Set the Shuffle count (how many animation steps to run) and the Delay in milliseconds between steps.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'Click Shuffle & Pick Winner. The list animates through random participants, then a winner is chosen server-side and their masked email is displayed.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'The winner is saved automatically to the giveaway and can be seen as a read-only field on the giveaway\'s edit screen (GAWG → Giveaways).', 'gawg' ); ?></li>
			</ol>

			<h2><?php esc_html_e( 'Managing Giveaway Status', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'Each giveaway has a status that controls whether participants can submit the entry form. The status is shown as a badge in the Giveaways list and the list can be sorted by status.', 'gawg' ); ?>
			</p>
			<ul>
				<li><strong><?php esc_html_e( 'Active', 'gawg' ); ?></strong> — <?php esc_html_e( 'The giveaway is open; participants can submit the entry form.', 'gawg' ); ?></li>
				<li><strong><?php esc_html_e( 'Closed', 'gawg' ); ?></strong> — <?php esc_html_e( 'Entry has been manually closed; the form shows a "Sorry, this giveaway is closed" message instead, and new submissions are rejected.', 'gawg' ); ?></li>
				<li><strong><?php esc_html_e( 'Winner Drawn', 'gawg' ); ?></strong> — <?php esc_html_e( 'A winner has been selected via Draw Winner; the form automatically shows the closed message and new submissions are rejected.', 'gawg' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'To close a giveaway manually, edit the giveaway term (GAWG → Giveaways → click the giveaway name) and check the "Closed for Participants" checkbox, then click Update.', 'gawg' ); ?></p>

			<h2><?php esc_html_e( 'Registration Date Window', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'Each giveaway can have optional Registration Opens and Registration Closes dates that automatically gate when participants can register — no manual checkbox toggling needed.', 'gawg' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Set these on the giveaway\'s edit screen (GAWG → Giveaways → click the giveaway name). Both fields accept a date and time in the site\'s local timezone.', 'gawg' ); ?>
			</p>
			<ul>
				<li><strong><?php esc_html_e( 'Registration Opens', 'gawg' ); ?></strong> — <?php esc_html_e( 'Before this date/time the full entry form is displayed in a disabled state (all inputs and the submit button are disabled, the wrapper receives the gawg-form--disabled CSS class) with the "Registration is not open yet." message shown above it. New submissions are rejected server-side regardless. Leave empty to open registration immediately.', 'gawg' ); ?></li>
				<li><strong><?php esc_html_e( 'Registration Closes', 'gawg' ); ?></strong> — <?php esc_html_e( 'After this date/time the form shows "Registration is closed." and new submissions are rejected. Leave empty to keep registration open indefinitely.', 'gawg' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'The default messages can be overridden per-placement using the not_open_message and closed_message shortcode attributes, or the matching text fields in the Giveaway Form block\'s Inspector Controls.', 'gawg' ); ?></p>

			<h2><?php esc_html_e( 'Settings', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'The Settings page (GAWG → Settings) contains plugin-wide configuration options.', 'gawg' ); ?>
			</p>

			<h3><?php esc_html_e( 'Spam Protection', 'gawg' ); ?></h3>
			<p>
				<?php esc_html_e( 'The entry form includes two layers of spam protection that work together:', 'gawg' ); ?>
			</p>
			<ul>
				<li>
					<strong><?php esc_html_e( 'Honeypot', 'gawg' ); ?></strong> —
					<?php esc_html_e( 'A hidden field is included in the form that is invisible to real users. Automated bots that fill in all fields will populate it, and the server silently rejects those submissions. This protection is always active and requires no configuration.', 'gawg' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Google reCAPTCHA v2 (optional)', 'gawg' ); ?></strong> —
					<?php esc_html_e( 'When both a Site Key and a Secret Key are saved on the Settings page, an "I\'m not a robot" checkbox widget is automatically rendered inside the form and the response is verified server-side before the entry is accepted. When no keys are configured, reCAPTCHA is skipped entirely — the form works normally without it.', 'gawg' ); ?>
				</li>
			</ul>

			<h3><?php esc_html_e( 'Google reCAPTCHA v2', 'gawg' ); ?></h3>
			<p>
				<?php esc_html_e( 'To enable reCAPTCHA, follow these steps:', 'gawg' ); ?>
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
				<li><?php esc_html_e( 'The reCAPTCHA widget will now appear automatically in every [gawg_form] or Giveaway Form block on your site. Remove either key to disable reCAPTCHA at any time without affecting the honeypot.', 'gawg' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Extra Entries', 'gawg' ); ?></h3>
			<ul>
				<li>
					<strong><?php esc_html_e( 'Extra entries for unique visit', 'gawg' ); ?></strong> —
					<?php esc_html_e( 'How many entries to award an inviter each time a new unique IP visits the site via their invite link. Default: 1.', 'gawg' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Extra entries for registration after visit', 'gawg' ); ?></strong> —
					<?php esc_html_e( 'How many entries to award an inviter when someone they referred completes registration for the same giveaway. Default: 1.', 'gawg' ); ?>
				</li>
			</ul>

			<h2><?php esc_html_e( 'Extra Entries via Invite Links', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'Each participant can earn bonus entries by sharing their personal invite link. Two types of bonus are available, both configurable on the Settings page.', 'gawg' ); ?>
			</p>
			<h3><?php esc_html_e( 'Bonus type 1: Unique visit', 'gawg' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'After submitting the entry form, a participant receives their personal invite link in the success message.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'The participant shares the link with friends. Each click from a unique IP address increments their entry count by the "Extra entries for unique visit" setting (default: 1).', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'The same IP address can only count once per invite link, so refreshing the page or multiple clicks from one location do not award extra entries.', 'gawg' ); ?></li>
			</ol>
			<h3><?php esc_html_e( 'Bonus type 2: Registration after visit', 'gawg' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'When a visitor arrives via an invite link, the plugin stores a short-lived cookie that tracks who referred them.', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'If that visitor then registers for the same giveaway, the inviter is automatically awarded the "Extra entries for registration after visit" bonus (default: 1).', 'gawg' ); ?></li>
				<li><?php esc_html_e( 'The bonus is awarded only once per referred registration, regardless of how many times the visitor clicked the invite link.', 'gawg' ); ?></li>
			</ol>
			<h3><?php esc_html_e( 'Invite link format', 'gawg' ); ?></h3>
			<pre><code>/?gwag-giveaway={giveaway-uuid}&amp;invite={participant-uuid}</code></pre>
			<p><?php esc_html_e( 'The link can point to any page on the site — the plugin detects the query parameters on every page load.', 'gawg' ); ?></p>
			<h3><?php esc_html_e( 'Viewing entry counts', 'gawg' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: link to the Participants list screen */
					wp_kses(
						__( 'The <a href="%s">Participants list</a> shows an Entries column with the current count per giveaway. Each participant\'s edit screen also shows an "Entries &amp; Invite Links" panel with the count and a copyable invite link for every giveaway they are enrolled in.', 'gawg' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( admin_url( 'edit.php?post_type=' . GAWG_Participant::POST_TYPE ) )
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Participant Action History', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'Every significant event in a participant\'s lifecycle is automatically recorded with a UTC timestamp and stored as post meta on the participant. The full history is displayed in a read-only table at the bottom of each participant\'s edit screen under the "Action History" heading.', 'gawg' ); ?>
			</p>
			<p><?php esc_html_e( 'Recorded actions:', 'gawg' ); ?></p>
			<ul>
				<li><strong>registered</strong> — <?php esc_html_e( 'Participant submitted the entry form and their post was created.', 'gawg' ); ?></li>
				<li><strong>verification_email_sent</strong> — <?php esc_html_e( 'A verification email was dispatched to the participant (initial send or resend).', 'gawg' ); ?></li>
				<li><strong>success_email_sent</strong> — <?php esc_html_e( 'A success confirmation email was sent after the participant verified their address.', 'gawg' ); ?></li>
				<li><strong>verified</strong> — <?php esc_html_e( 'Participant clicked the verification link and their email was confirmed.', 'gawg' ); ?></li>
				<li><strong>invite_visited</strong> — <?php esc_html_e( 'A new unique IP address visited via this participant\'s invite link (bonus entry awarded).', 'gawg' ); ?></li>
				<li><strong>invite_registered</strong> — <?php esc_html_e( 'Someone referred via this participant\'s invite link completed registration (bonus entry awarded to this participant as inviter).', 'gawg' ); ?></li>
				<li><strong>invite_verified</strong> — <?php esc_html_e( 'Someone this participant referred has verified their email.', 'gawg' ); ?></li>
			</ul>
			<p>
				<?php
				printf(
					/* translators: %s: link to the Participants list screen */
					wp_kses(
						__( 'To view a participant\'s history, open their edit screen from the <a href="%s">Participants list</a> and scroll to the bottom.', 'gawg' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( admin_url( 'edit.php?post_type=' . GAWG_Participant::POST_TYPE ) )
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Custom Extra Entries Hook', 'gawg' ); ?></h2>
			<p>
				<?php esc_html_e( 'The gawg_add_extra_entries action hook lets any third-party plugin (e.g. Gravity Forms, WooCommerce) award bonus entries to a verified participant programmatically. The hook only acts on participants who have completed email verification — unverified participants are silently ignored.', 'gawg' ); ?>
			</p>
			<h3><?php esc_html_e( 'Basic usage', 'gawg' ); ?></h3>
			<pre><code>do_action( 'gawg_add_extra_entries', array(
    'participant_email' => 'user@example.com', // or 'participant_uuid' => '...'
    'action_id'         => 'gravity_form_quiz',
    'message'           => 'Completed the quiz',
) );</code></pre>
			<h3><?php esc_html_e( 'All parameters', 'gawg' ); ?></h3>
			<ul>
				<li>
					<strong>participant_uuid</strong> / <strong>participant_email</strong> —
					<?php esc_html_e( 'One of these is required to identify the participant. UUID takes precedence if both are supplied.', 'gawg' ); ?>
				</li>
				<li>
					<strong>action_id</strong> —
					<?php esc_html_e( '(required) A unique slug identifying this type of event (e.g. "quiz_completed"). Used as part of the deduplication meta key — keep it consistent across calls for the same event type.', 'gawg' ); ?>
				</li>
				<li>
					<strong>message</strong> —
					<?php esc_html_e( '(required) A human-readable description stored in the participant\'s action history (e.g. "Completed the quiz").', 'gawg' ); ?>
				</li>
				<li>
					<strong>giveaway_uuid</strong> —
					<?php esc_html_e( '(optional) UUID of the giveaway to award entries for. When omitted, all active giveaways the participant is registered in receive the bonus.', 'gawg' ); ?>
				</li>
				<li>
					<strong>entry_count</strong> —
					<?php esc_html_e( '(optional, default 1) Number of entries to award per event.', 'gawg' ); ?>
				</li>
				<li>
					<strong>unique</strong> —
					<?php esc_html_e( '(optional, default true) When true, the bonus is awarded at most once per participant per giveaway — subsequent calls with the same action_id for the same giveaway are ignored. When false, the bonus can be awarded repeatedly until the max_entries cap is reached.', 'gawg' ); ?>
				</li>
				<li>
					<strong>max_entries</strong> —
					<?php esc_html_e( '(optional, default 10) Maximum total number of times the bonus can be awarded to a participant when unique is false. Ignored in unique mode.', 'gawg' ); ?>
				</li>
			</ul>
			<h3><?php esc_html_e( 'Uniqueness behaviour', 'gawg' ); ?></h3>
			<p>
				<?php esc_html_e( 'When unique=true (the default), the plugin stores a flag in participant meta keyed as gawg_{giveaway_uuid}_{action_id}. If the flag is already set for a given giveaway, that giveaway is skipped. This ensures the event is awarded exactly once per participant per giveaway.', 'gawg' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'When unique=false, a global counter keyed as gawg_{action_id}_count tracks how many times the action has fired for that participant. If the counter has reached max_entries, the hook exits without awarding any entries.', 'gawg' ); ?>
			</p>
			<h3><?php esc_html_e( 'Example: Gravity Forms integration', 'gawg' ); ?></h3>
			<pre><code>add_action( 'gform_after_submission_5', function( $entry, $form ) {
    $email = rgar( $entry, '1' ); // field 1 = email
    do_action( 'gawg_add_extra_entries', array(
        'participant_email' => $email,
        'action_id'         => 'gform_quiz_5',
        'message'           => 'Completed the quiz (form #5)',
        'entry_count'       => 2,
        'unique'            => true,
    ) );
}, 10, 2 );</code></pre>

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

		update_post_meta( $post_id, self::META_TEST_FLAG, '1' );

		$uuid = get_post_meta( $post_id, GAWG_Participant::META_UUID, true );

		wp_delete_post( $post_id, true );

		if ( '' === $uuid ) {
			$result['message'] = 'UUID meta was empty after saving.';
			return $result;
		}

		if ( ! self::is_valid_uuid_v4( $uuid ) ) {
			$result['message'] = 'UUID is not a valid v4 UUID: ' . $uuid;
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'UUID: ' . $uuid;
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
		update_term_meta( $term_id, self::META_TEST_FLAG, '1' );

		$uuid = get_term_meta( $term_id, GAWG_Giveaway::META_UUID, true );

		wp_delete_term( $term_id, GAWG_Giveaway::TAXONOMY );

		if ( '' === $uuid ) {
			$result['message'] = 'UUID meta was empty after saving.';
			return $result;
		}

		if ( ! self::is_valid_uuid_v4( $uuid ) ) {
			$result['message'] = 'UUID is not a valid v4 UUID: ' . $uuid;
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'UUID: ' . $uuid;
		return $result;
	}

	private static function test_link_participant_single_giveaway() {
		$result = array(
			'id'      => 'link_participant_single_giveaway',
			'name'    => 'Participant Linked to Single Giveaway',
			'pass'    => false,
			'message' => '',
		);

		$ts   = gmdate( 'Y-m-d H:i:s' );
		$term = wp_insert_term( 'GAWG Test Giveaway – ' . $ts, GAWG_Giveaway::TAXONOMY );

		if ( is_wp_error( $term ) ) {
			$result['message'] = 'Failed to insert giveaway term: ' . $term->get_error_message();
			return $result;
		}

		$term_id = $term['term_id'];
		update_term_meta( $term_id, self::META_TEST_FLAG, '1' );

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'GAWG Test Participant – ' . $ts,
				'post_type'   => GAWG_Participant::POST_TYPE,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_delete_term( $term_id, GAWG_Giveaway::TAXONOMY );
			$result['message'] = 'Failed to insert participant post: ' . $post_id->get_error_message();
			return $result;
		}

		update_post_meta( $post_id, self::META_TEST_FLAG, '1' );

		$assigned = wp_set_object_terms( $post_id, $term_id, GAWG_Giveaway::TAXONOMY );

		if ( is_wp_error( $assigned ) ) {
			wp_delete_post( $post_id, true );
			wp_delete_term( $term_id, GAWG_Giveaway::TAXONOMY );
			$result['message'] = 'Failed to assign taxonomy term: ' . $assigned->get_error_message();
			return $result;
		}

		$terms = wp_get_object_terms( $post_id, GAWG_Giveaway::TAXONOMY, array( 'fields' => 'ids' ) );

		wp_delete_post( $post_id, true );
		wp_delete_term( $term_id, GAWG_Giveaway::TAXONOMY );

		if ( is_wp_error( $terms ) || ! in_array( $term_id, $terms, true ) ) {
			$result['message'] = 'Participant was not linked to the giveaway term.';
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'Participant correctly linked to 1 giveaway (term ID: ' . $term_id . ').';
		return $result;
	}

	private static function test_link_participant_multiple_giveaways() {
		$result = array(
			'id'      => 'link_participant_multiple_giveaways',
			'name'    => 'Participant Linked to Multiple Giveaways',
			'pass'    => false,
			'message' => '',
		);

		$ts    = gmdate( 'Y-m-d H:i:s' );
		$term1 = wp_insert_term( 'GAWG Test Giveaway A – ' . $ts, GAWG_Giveaway::TAXONOMY );
		$term2 = wp_insert_term( 'GAWG Test Giveaway B – ' . $ts, GAWG_Giveaway::TAXONOMY );

		if ( is_wp_error( $term1 ) || is_wp_error( $term2 ) ) {
			if ( ! is_wp_error( $term1 ) ) {
				wp_delete_term( $term1['term_id'], GAWG_Giveaway::TAXONOMY );
			}
			$result['message'] = 'Failed to insert giveaway terms.';
			return $result;
		}

		$term_id1 = $term1['term_id'];
		$term_id2 = $term2['term_id'];
		update_term_meta( $term_id1, self::META_TEST_FLAG, '1' );
		update_term_meta( $term_id2, self::META_TEST_FLAG, '1' );

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'GAWG Test Participant – ' . $ts,
				'post_type'   => GAWG_Participant::POST_TYPE,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_delete_term( $term_id1, GAWG_Giveaway::TAXONOMY );
			wp_delete_term( $term_id2, GAWG_Giveaway::TAXONOMY );
			$result['message'] = 'Failed to insert participant post: ' . $post_id->get_error_message();
			return $result;
		}

		update_post_meta( $post_id, self::META_TEST_FLAG, '1' );

		$assigned = wp_set_object_terms( $post_id, array( $term_id1, $term_id2 ), GAWG_Giveaway::TAXONOMY );

		if ( is_wp_error( $assigned ) ) {
			wp_delete_post( $post_id, true );
			wp_delete_term( $term_id1, GAWG_Giveaway::TAXONOMY );
			wp_delete_term( $term_id2, GAWG_Giveaway::TAXONOMY );
			$result['message'] = 'Failed to assign taxonomy terms: ' . $assigned->get_error_message();
			return $result;
		}

		$terms = wp_get_object_terms( $post_id, GAWG_Giveaway::TAXONOMY, array( 'fields' => 'ids' ) );

		wp_delete_post( $post_id, true );
		wp_delete_term( $term_id1, GAWG_Giveaway::TAXONOMY );
		wp_delete_term( $term_id2, GAWG_Giveaway::TAXONOMY );

		if ( is_wp_error( $terms ) ) {
			$result['message'] = 'Failed to retrieve taxonomy terms: ' . $terms->get_error_message();
			return $result;
		}

		if ( ! in_array( $term_id1, $terms, true ) || ! in_array( $term_id2, $terms, true ) ) {
			$result['message'] = 'Participant was not linked to all giveaway terms. Found term IDs: ' . implode( ', ', $terms );
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'Participant correctly linked to 2 giveaways (term IDs: ' . $term_id1 . ', ' . $term_id2 . ').';
		return $result;
	}

	private static function make_test_participant_in_giveaway() {
		$ts   = gmdate( 'Y-m-d H:i:s' ) . ' ' . wp_generate_uuid4();
		$term = wp_insert_term( 'GAWG Test Giveaway – ' . $ts, GAWG_Giveaway::TAXONOMY );
		if ( is_wp_error( $term ) ) {
			return array( 'error' => 'Failed to insert giveaway: ' . $term->get_error_message() );
		}
		$term_id = $term['term_id'];
		update_term_meta( $term_id, self::META_TEST_FLAG, '1' );
		GAWG_Giveaway::maybe_generate_uuid( $term_id );
		$giveaway_uuid = get_term_meta( $term_id, GAWG_Giveaway::META_UUID, true );

		$post_id = wp_insert_post( array(
			'post_title'  => 'test-' . $ts . '@example.com',
			'post_type'   => GAWG_Participant::POST_TYPE,
			'post_status' => 'publish',
		), true );
		if ( is_wp_error( $post_id ) ) {
			wp_delete_term( $term_id, GAWG_Giveaway::TAXONOMY );
			return array( 'error' => 'Failed to insert participant: ' . $post_id->get_error_message() );
		}
		update_post_meta( $post_id, self::META_TEST_FLAG, '1' );
		wp_set_object_terms( $post_id, $term_id, GAWG_Giveaway::TAXONOMY );

		return array(
			'post_id'       => $post_id,
			'term_id'       => $term_id,
			'giveaway_uuid' => $giveaway_uuid,
		);
	}

	private static function test_entries_default_one() {
		$result = array(
			'id'      => 'entries_default_one',
			'name'    => 'Entries Default to 1 After Form Submit',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id       = $setup['post_id'];
		$giveaway_uuid = $setup['giveaway_uuid'];

		update_post_meta( $post_id, GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid, 1 );
		$entries = (int) get_post_meta( $post_id, GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid, true );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		if ( 1 !== $entries ) {
			$result['message'] = 'Expected 1 entry, got ' . $entries;
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'Entry count correctly initialised to 1.';
		return $result;
	}

	private static function test_invite_url_format() {
		$result = array(
			'id'      => 'invite_url_format',
			'name'    => 'Invite URL Contains Correct Query Params',
			'pass'    => false,
			'message' => '',
		);

		$giveaway_uuid    = wp_generate_uuid4();
		$participant_uuid = wp_generate_uuid4();

		$url = GAWG_Form::build_invite_url( $giveaway_uuid, $participant_uuid );

		$parsed = wp_parse_url( $url );
		$query  = array();
		if ( isset( $parsed['query'] ) ) {
			wp_parse_str( $parsed['query'], $query );
		}

		if ( ! isset( $query['gwag-giveaway'] ) || $query['gwag-giveaway'] !== $giveaway_uuid ) {
			$result['message'] = 'Missing or wrong gwag-giveaway param in: ' . $url;
			return $result;
		}

		if ( ! isset( $query['invite'] ) || $query['invite'] !== $participant_uuid ) {
			$result['message'] = 'Missing or wrong invite param in: ' . $url;
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'URL format correct: ' . $url;
		return $result;
	}

	private static function test_new_ip_increments_entries() {
		$result = array(
			'id'      => 'new_ip_increments_entries',
			'name'    => 'New IP Increments Entry Count',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id       = $setup['post_id'];
		$giveaway_uuid = $setup['giveaway_uuid'];

		update_post_meta( $post_id, GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid, 1 );

		GAWG_Form::process_invite( $post_id, $giveaway_uuid, '192.0.2.1' );

		$entries = (int) get_post_meta( $post_id, GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid, true );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		if ( 2 !== $entries ) {
			$result['message'] = 'Expected 2 entries after new IP, got ' . $entries;
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'Entry count incremented to 2 after unique IP visit.';
		return $result;
	}

	private static function test_duplicate_ip_no_increment() {
		$result = array(
			'id'      => 'duplicate_ip_no_increment',
			'name'    => 'Duplicate IP Does Not Double-Increment Entries',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id       = $setup['post_id'];
		$giveaway_uuid = $setup['giveaway_uuid'];

		update_post_meta( $post_id, GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid, 1 );

		GAWG_Form::process_invite( $post_id, $giveaway_uuid, '192.0.2.2' );
		GAWG_Form::process_invite( $post_id, $giveaway_uuid, '192.0.2.2' ); // same IP again

		$entries = (int) get_post_meta( $post_id, GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid, true );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		if ( 2 !== $entries ) {
			$result['message'] = 'Expected 2 entries (not double-counted), got ' . $entries;
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'Entry count stayed at 2; duplicate IP correctly ignored.';
		return $result;
	}

	private static function test_verification_sent_at_set() {
		$result = array(
			'id'      => 'verification_sent_at_set',
			'name'    => 'Verification Email Sets Sent-At Timestamp',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id = $setup['post_id'];
		$term    = get_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		// Suppress actual email sending during test.
		add_filter( 'pre_wp_mail', '__return_true', 0 );
		GAWG_Mailer::send_verification_email( $post_id, $term );
		remove_filter( 'pre_wp_mail', '__return_true', 0 );

		$sent_at  = (int) get_post_meta( $post_id, GAWG_Participant::META_VERIFICATION_SENT_AT, true );
		$verified = get_post_meta( $post_id, GAWG_Participant::META_VERIFIED, true );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		if ( $sent_at <= 0 ) {
			$result['message'] = '_gawg_verification_sent_at was not set after send_verification_email.';
			return $result;
		}

		if ( '1' === $verified ) {
			$result['message'] = '_gawg_verified should NOT be set after sending verification email.';
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'Sent-at timestamp set; verified flag not prematurely set.';
		return $result;
	}

	private static function test_verification_sets_verified_flag() {
		$result = array(
			'id'      => 'verification_sets_verified_flag',
			'name'    => 'Verification Link Sets Verified Flag',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id = $setup['post_id'];
		$term    = get_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		// Simulate a verification email having been sent within 24h.
		update_post_meta( $post_id, GAWG_Participant::META_VERIFICATION_SENT_AT, time() );

		// Suppress success email during test.
		add_filter( 'pre_wp_mail', '__return_true', 0 );
		$status = GAWG_Verification::process_verification( $post_id, $term );
		remove_filter( 'pre_wp_mail', '__return_true', 0 );

		$verified = get_post_meta( $post_id, GAWG_Participant::META_VERIFIED, true );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		if ( 'verified' !== $status ) {
			$result['message'] = 'process_verification returned "' . $status . '" instead of "verified".';
			return $result;
		}

		if ( '1' !== $verified ) {
			$result['message'] = '_gawg_verified was not set to "1" after verification.';
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'Participant verified correctly; _gawg_verified = 1.';
		return $result;
	}

	private static function test_expired_verification_detected() {
		$result = array(
			'id'      => 'expired_verification_detected',
			'name'    => 'Expired Verification Link Is Detected',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id = $setup['post_id'];
		$term    = get_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		// Simulate an expired link: sent 25 hours ago.
		update_post_meta( $post_id, GAWG_Participant::META_VERIFICATION_SENT_AT, time() - 25 * HOUR_IN_SECONDS );

		$status = GAWG_Verification::process_verification( $post_id, $term );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		if ( 'expired' !== $status ) {
			$result['message'] = 'process_verification returned "' . $status . '" instead of "expired".';
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'Expired link correctly identified.';
		return $result;
	}

	private static function test_already_verified_skips_reverify() {
		$result = array(
			'id'      => 'already_verified_skips_reverify',
			'name'    => 'Already-Verified Participant Is Not Re-Verified',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id = $setup['post_id'];
		$term    = get_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		update_post_meta( $post_id, GAWG_Participant::META_VERIFIED, '1' );
		update_post_meta( $post_id, GAWG_Participant::META_VERIFICATION_SENT_AT, time() );

		$status = GAWG_Verification::process_verification( $post_id, $term );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		if ( 'already_verified' !== $status ) {
			$result['message'] = 'process_verification returned "' . $status . '" instead of "already_verified".';
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'Already-verified participant correctly skipped re-verification.';
		return $result;
	}

	private static function test_history_registration_recorded() {
		$result = array(
			'id'      => 'history_registration_recorded',
			'name'    => 'History: Registration Is Recorded',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id = $setup['post_id'];

		GAWG_History::append( $post_id, 'registered' );

		$entries = GAWG_History::get_all( $post_id );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		if ( empty( $entries ) ) {
			$result['message'] = 'No history entries found after append.';
			return $result;
		}

		$first = reset( $entries );
		if ( 'registered' !== ( $first['action'] ?? '' ) ) {
			$result['message'] = 'Expected action "registered", got "' . ( $first['action'] ?? '' ) . '".';
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = 'history_1 action = "registered" correctly recorded.';
		return $result;
	}

	private static function test_history_verification_email_recorded() {
		$result = array(
			'id'      => 'history_verification_email_recorded',
			'name'    => 'History: Verification Email Is Recorded',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id = $setup['post_id'];
		$term    = get_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		add_filter( 'pre_wp_mail', '__return_true', 0 );
		GAWG_Mailer::send_verification_email( $post_id, $term );
		remove_filter( 'pre_wp_mail', '__return_true', 0 );

		$entries = GAWG_History::get_all( $post_id );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		$actions = array_column( $entries, 'action' );
		if ( ! in_array( 'verification_email_sent', $actions, true ) ) {
			$result['message'] = 'Action "verification_email_sent" not found in history. Found: ' . implode( ', ', $actions );
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = '"verification_email_sent" correctly recorded in history.';
		return $result;
	}

	private static function test_history_verified_recorded() {
		$result = array(
			'id'      => 'history_verified_recorded',
			'name'    => 'History: Verified Action Is Recorded',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id = $setup['post_id'];
		$term    = get_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		update_post_meta( $post_id, GAWG_Participant::META_VERIFICATION_SENT_AT, time() );

		add_filter( 'pre_wp_mail', '__return_true', 0 );
		$status = GAWG_Verification::process_verification( $post_id, $term );
		remove_filter( 'pre_wp_mail', '__return_true', 0 );

		$entries = GAWG_History::get_all( $post_id );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		if ( 'verified' !== $status ) {
			$result['message'] = 'process_verification returned "' . $status . '" instead of "verified".';
			return $result;
		}

		$actions = array_column( $entries, 'action' );
		if ( ! in_array( 'verified', $actions, true ) ) {
			$result['message'] = 'Action "verified" not found in history. Found: ' . implode( ', ', $actions );
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = '"verified" correctly recorded in history.';
		return $result;
	}

	private static function test_history_invite_visited_recorded() {
		$result = array(
			'id'      => 'history_invite_visited_recorded',
			'name'    => 'History: Invite Visited Is Recorded',
			'pass'    => false,
			'message' => '',
		);

		$setup = self::make_test_participant_in_giveaway();
		if ( isset( $setup['error'] ) ) {
			$result['message'] = $setup['error'];
			return $result;
		}

		$post_id       = $setup['post_id'];
		$giveaway_uuid = $setup['giveaway_uuid'];

		update_post_meta( $post_id, GAWG_Participant::META_ENTRIES_PREFIX . $giveaway_uuid, 1 );

		GAWG_Form::process_invite( $post_id, $giveaway_uuid, '192.0.2.50' );

		$entries = GAWG_History::get_all( $post_id );

		wp_delete_post( $post_id, true );
		wp_delete_term( $setup['term_id'], GAWG_Giveaway::TAXONOMY );

		$actions = array_column( $entries, 'action' );
		if ( ! in_array( 'invite_visited', $actions, true ) ) {
			$result['message'] = 'Action "invite_visited" not found in history. Found: ' . implode( ', ', $actions );
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = '"invite_visited" correctly recorded in history.';
		return $result;
	}

	private static function test_history_invite_registered_recorded() {
		$result = array(
			'id'      => 'history_invite_registered_recorded',
			'name'    => 'History: Invite Registered Is Recorded',
			'pass'    => false,
			'message' => '',
		);

		$inviter_setup = self::make_test_participant_in_giveaway();
		if ( isset( $inviter_setup['error'] ) ) {
			$result['message'] = $inviter_setup['error'];
			return $result;
		}

		$inviter_id    = $inviter_setup['post_id'];
		$giveaway_uuid = $inviter_setup['giveaway_uuid'];
		$invitee_uuid  = wp_generate_uuid4();

		GAWG_Form::record_invitee_registration( $inviter_id, $giveaway_uuid, $invitee_uuid, 'publish' );

		$entries = GAWG_History::get_all( $inviter_id );

		wp_delete_post( $inviter_id, true );
		wp_delete_term( $inviter_setup['term_id'], GAWG_Giveaway::TAXONOMY );

		$actions = array_column( $entries, 'action' );
		if ( ! in_array( 'invite_registered', $actions, true ) ) {
			$result['message'] = 'Action "invite_registered" not found in history. Found: ' . implode( ', ', $actions );
			return $result;
		}

		$result['pass']    = true;
		$result['message'] = '"invite_registered" correctly recorded in history.';
		return $result;
	}

	private static function cleanup_test_data() {
		$posts = get_posts( array(
			'post_type'      => GAWG_Participant::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => self::META_TEST_FLAG,
			'meta_value'     => '1',
			'fields'         => 'ids',
		) );
		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$terms = get_terms( array(
			'taxonomy'   => GAWG_Giveaway::TAXONOMY,
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'   => self::META_TEST_FLAG,
					'value' => '1',
				),
			),
			'fields'     => 'ids',
		) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				wp_delete_term( $term_id, GAWG_Giveaway::TAXONOMY );
			}
		}
	}

	private static function is_valid_uuid_v4( $uuid ) {
		return (bool) preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$uuid
		);
	}
}
