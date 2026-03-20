<?php
/**
 * Admin UI class.
 *
 * @package NM_Pinterest_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'NM_Pinterest_Tools_Admin' ) ) {

	/**
	 * Handles the post-editor UI (Generate Pin / Share to Pinterest buttons),
	 * admin list column with filtering and sorting.
	 */
	class NM_Pinterest_Tools_Admin {

		/**
		 * Main plugin instance.
		 *
		 * @var NM_Pinterest_Tools
		 */
		private $plugin;

		/**
		 * Constructor.
		 *
		 * @param NM_Pinterest_Tools $plugin Main plugin instance.
		 */
		public function __construct( NM_Pinterest_Tools $plugin ) {
			$this->plugin = $plugin;
		}

		/**
		 * Register hooks.
		 *
		 * @return void
		 */
		public function init() {
			add_filter( 'acf/prepare_field/name=nm_pinterest_tools_ui', array( $this, 'prepare_tools_ui_field' ) );
			add_action( 'admin_post_nm_pinterest_reset_status', array( $this, 'reset_share_status' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'admin_head-post.php', array( $this, 'print_editor_styles' ) );
			add_action( 'admin_head-post-new.php', array( $this, 'print_editor_styles' ) );
			add_action( 'admin_footer-post.php', array( $this, 'print_editor_footer_js' ) );
			add_action( 'admin_footer-post-new.php', array( $this, 'print_editor_footer_js' ) );

			if ( ! empty( $this->plugin->get_setting( 'enable_admin_column' ) ) ) {
				$post_type = (string) $this->plugin->get_setting( 'post_type' );

				add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_posts_list_column' ) );
				add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_posts_list_column' ), 10, 2 );
				add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'make_column_sortable' ) );
				add_action( 'restrict_manage_posts', array( $this, 'render_shared_filter_dropdown' ) );
				add_action( 'pre_get_posts', array( $this, 'modify_admin_list_query' ) );
				add_filter( 'posts_clauses', array( $this, 'handle_shared_column_sorting' ), 10, 2 );
				add_action( 'admin_head-edit.php', array( $this, 'print_list_styles' ) );
			}
		}

		/**
		 * Prepare the ACF message field that holds the Pinterest controls.
		 *
		 * @param array<string,mixed> $field ACF field config.
		 * @return array<string,mixed>|false
		 */
		public function prepare_tools_ui_field( $field ) {
			$post = $this->get_current_post();

			if ( ! $post || $this->plugin->get_setting( 'post_type' ) !== $post->post_type ) {
				return false;
			}

			$field['message'] = $this->get_generate_section_html( $post ) . '<hr>' . $this->get_share_section_html( $post );

			return $field;
		}

		/**
		 * Build the Generate section.
		 *
		 * @param WP_Post $post Post object.
		 * @return string
		 */
		private function get_generate_section_html( WP_Post $post ) {
			$image_field_name = (string) $this->plugin->get_setting( 'image_field_name' );
			$generate_id      = absint( $this->plugin->get_setting( 'generate_recipe_id' ) );
			$require_excerpt  = ! empty( $this->plugin->get_setting( 'require_excerpt' ) );

			$title      = trim( (string) $post->post_title );
			$excerpt    = trim( (string) $post->post_excerpt );
			$image_id   = absint( get_post_meta( $post->ID, $image_field_name, true ) );
			$has_image  = $image_id > 0;
			$can_render = $generate_id > 0;

			$allowed_statuses = array( 'auto-draft', 'draft', 'pending', 'publish' );
			$reasons          = array();

			if ( ! in_array( $post->post_status, $allowed_statuses, true ) ) {
				$reasons[] = 'Post status must be Draft, Pending, or Published (or a new post).';
			}

			if ( $has_image ) {
				$reasons[] = 'A Pinterest image is already selected. Remove it first if you want to regenerate.';
			}

			if ( '' === $title ) {
				$reasons[] = 'Add a post title.';
			}

			if ( $require_excerpt && '' === $excerpt ) {
				$reasons[] = 'Add an excerpt (this is used in the image prompt).';
			}

			if ( ! $can_render ) {
				$reasons[] = 'Add the Generate Pin Magic Link recipe ID in Settings → NM Pinterest Tools.';
			}

			$is_ready = empty( $reasons );
			$html     = '<div class="nm-pinterest-section nm-pinterest-section--generate">';
			$html    .= '<p><strong>Generate Pin</strong></p>';

			if ( $is_ready ) {
				$shortcode = sprintf(
					'[automator_link id="%d" text="Generate Pinterest Pin"]',
					$generate_id
				);

				$html .= '<div class="nm-pinterest-action nm-pinterest-action--generate">' . do_shortcode( $shortcode ) . '</div>';
				$html .= '<p class="description nm-pinterest-help">Generates the Pinterest image and attaches it to this post.</p>';
			} else {
				$html .= '<span class="button button-primary nm-disabled-button" aria-disabled="true">Generate Pinterest Pin</span>';
				$html .= '<div class="nm-pin-reasons">';
				$html .= '<strong>To enable this button:</strong>';
				$html .= '<ul>';
				foreach ( $reasons as $reason ) {
					$html .= '<li>' . esc_html( $reason ) . '</li>';
				}
				$html .= '</ul>';
				$html .= '</div>';
			}

			$html .= '</div>';

			return $html;
		}

		/**
		 * Build the Share section.
		 *
		 * @param WP_Post $post Post object.
		 * @return string
		 */
		private function get_share_section_html( WP_Post $post ) {
			$image_field_name = (string) $this->plugin->get_setting( 'image_field_name' );
			$share_id         = absint( $this->plugin->get_setting( 'share_recipe_id' ) );

			$image_id      = absint( get_post_meta( $post->ID, $image_field_name, true ) );
			$has_image     = $image_id > 0;
			$is_published  = 'publish' === get_post_status( $post );
			$shared_raw    = get_post_meta( $post->ID, $this->plugin->get_meta_key( 'shared' ), true );
			$shared        = $this->plugin->is_truthy( $shared_raw );
			$shared_at_raw = get_post_meta( $post->ID, $this->plugin->get_meta_key( 'shared_at' ), true );
			$shared_at     = $this->plugin->format_datetime( $shared_at_raw );
			$via           = trim( (string) get_post_meta( $post->ID, $this->plugin->get_meta_key( 'shared_via' ), true ) );
			$pin_url       = trim( (string) get_post_meta( $post->ID, $this->plugin->get_meta_key( 'pin_url' ), true ) );
			$pin_id        = trim( (string) get_post_meta( $post->ID, $this->plugin->get_meta_key( 'pin_id' ), true ) );
			$last_error    = trim( (string) get_post_meta( $post->ID, $this->plugin->get_meta_key( 'last_error' ), true ) );

			$reasons = array();

			if ( ! $is_published ) {
				$reasons[] = 'Publish the post before sharing.';
			}

			if ( ! $has_image ) {
				$reasons[] = 'Generate or select a Pinterest image first.';
			}

			if ( $share_id <= 0 ) {
				$reasons[] = 'Add the Share Pin Magic Link recipe ID in Settings → NM Pinterest Tools.';
			}

			$button_text = $shared ? 'Share again to Pinterest' : 'Share to Pinterest';
			$reset_url   = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'nm_pinterest_reset_status',
						'post_id' => $post->ID,
					),
					admin_url( 'admin-post.php' )
				),
				'nm_pinterest_reset_status_' . $post->ID
			);

			$html  = '<div class="nm-pinterest-section nm-pinterest-section--share">';
			$html .= '<p><strong>Share</strong></p>';
			$html .= '<p><strong>Status:</strong> ';
			$html .= $shared ? '<span class="nm-pinterest-status nm-pinterest-status--yes">Shared</span>' : '<span class="nm-pinterest-status nm-pinterest-status--no">Not shared</span>';
			$html .= '</p>';
			$html .= '<p><strong>Last shared:</strong><br>' . ( $shared_at ? esc_html( $shared_at ) : 'Not recorded yet' ) . '</p>';

			if ( '' !== $via ) {
				$html .= '<p><strong>Via:</strong> ' . esc_html( $via ) . '</p>';
			}

			if ( '' !== $pin_id ) {
				$html .= '<p><strong>Pin ID:</strong> ' . esc_html( $pin_id ) . '</p>';
			}

			if ( '' !== $pin_url ) {
				$html .= '<p><a href="' . esc_url( $pin_url ) . '" target="_blank" rel="noopener">View Pin</a></p>';
			}

			if ( '' !== $last_error ) {
				$html .= '<div class="nm-pinterest-note nm-pinterest-note--error"><strong>Last error:</strong> ' . esc_html( $last_error ) . '</div>';
			}

			if ( empty( $reasons ) ) {
				$shortcode = sprintf(
					'[automator_link id="%d" text="%s"]',
					$share_id,
					esc_attr( $button_text )
				);

				$html .= '<div class="nm-pinterest-action nm-pinterest-action--share">' . do_shortcode( $shortcode ) . '</div>';
				$html .= '<p class="description nm-pinterest-help">Runs the Pinterest share recipe, then refreshes this page automatically.</p>';
			} else {
				$html .= '<span class="button button-secondary nm-disabled-button" aria-disabled="true">Share to Pinterest</span>';
				$html .= '<div class="nm-pin-reasons">';
				$html .= '<strong>To enable this button:</strong>';
				$html .= '<ul>';
				foreach ( $reasons as $reason ) {
					$html .= '<li>' . esc_html( $reason ) . '</li>';
				}
				$html .= '</ul>';
				$html .= '</div>';
			}

			if ( $shared || $shared_at_raw || $pin_url || $pin_id || $via || $last_error ) {
				$html .= '<p><a class="button button-secondary" href="' . esc_url( $reset_url ) . '">Reset share status</a></p>';
			}

			$html .= '</div>';

			return $html;
		}

		/**
		 * Get the current post from the editor request.
		 *
		 * @return WP_Post|null
		 */
		private function get_current_post() {
			$post_id = 0;

			// phpcs:disable WordPress.Security.NonceVerification -- Read-only; nonce verified by WP core on this admin screen.
			if ( isset( $_GET['post'] ) ) {
				$post_id = absint( $_GET['post'] );
			} elseif ( isset( $_POST['post_ID'] ) ) {
				$post_id = absint( $_POST['post_ID'] );
			}
			// phpcs:enable WordPress.Security.NonceVerification

			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				if ( $post instanceof WP_Post ) {
					return $post;
				}
			}

			$current = get_post();

			return ( $current instanceof WP_Post ) ? $current : null;
		}

		/**
		 * Reset Pinterest share-related post meta.
		 *
		 * @return void
		 */
		public function reset_share_status() {
			$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

			if ( ! $post_id ) {
				wp_die( 'Missing post ID.' );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_die( 'You do not have permission to edit this post.' );
			}

			check_admin_referer( 'nm_pinterest_reset_status_' . $post_id );

			$meta_keys = $this->plugin->get_meta_keys();
			foreach ( $meta_keys as $meta_key ) {
				delete_post_meta( $post_id, $meta_key );
			}

			$redirect = add_query_arg(
				array(
					'post'               => $post_id,
					'action'             => 'edit',
					'nm_pinterest_reset' => 1,
				),
				admin_url( 'post.php' )
			);

			wp_safe_redirect( $redirect );
			exit;
		}

		/**
		 * Show admin notices.
		 *
		 * @return void
		 */
		public function admin_notices() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Displaying a notice after redirect; nonce was verified in reset_share_status().
			if ( empty( $_GET['nm_pinterest_reset'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['nm_pinterest_reset'] ) ) ) {
				return;
			}

			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			echo '<div class="notice notice-success is-dismissible"><p>Pinterest share status was reset for this post.</p></div>';
		}

		/**
		 * Print editor styles.
		 *
		 * @return void
		 */
		public function print_editor_styles() {
			$screen = get_current_screen();
			if ( ! $screen || 'post' !== $screen->base || $this->plugin->get_setting( 'post_type' ) !== $screen->post_type ) {
				return;
			}
			?>
			<style>
				.nm-pinterest-tools-ui .acf-label,
				.nm-pinterest-tools-ui .acf-label label {
					display: none !important;
				}
				.nm-pinterest-section p {
					margin-top: 0;
				}
				.nm-pinterest-action a {
					display: inline-block;
					padding: 6px 10px;
					border-radius: 4px;
					background: #2271b1;
					color: #fff !important;
					text-decoration: none;
					font-weight: 600;
				}
				.nm-pinterest-action a:hover {
					filter: brightness(0.95);
				}
				.nm-disabled-button {
					opacity: .55;
					cursor: not-allowed;
					pointer-events: none;
				}
				.nm-pin-reasons {
					margin-top: 8px;
				}
				.nm-pin-reasons strong,
				.nm-pin-reasons li,
				.nm-pinterest-help {
					font-size: 12px;
				}
				.nm-pin-reasons ul {
					margin: 6px 0 0 18px;
					list-style: disc;
				}
				.nm-pinterest-status {
					display: inline-block;
					padding: 2px 8px;
					border-radius: 999px;
					font-size: 12px;
					font-weight: 600;
					line-height: 1.8;
				}
				.nm-pinterest-status--yes {
					background: #e7f6ea;
					color: #19612c;
				}
				.nm-pinterest-status--no {
					background: #f6e7e7;
					color: #8a1f1f;
				}
				.nm-pinterest-note {
					margin: 10px 0;
					padding: 10px 12px;
					border-left: 4px solid #dba617;
					background: #fff8e5;
				}
				.nm-pinterest-note--error {
					border-left-color: #b32d2e;
					background: #fcf0f1;
				}
			</style>
			<?php
		}

		/**
		 * Print editor footer JS for Magic Link actions.
		 *
		 * @return void
		 */
		public function print_editor_footer_js() {
			$screen = get_current_screen();
			if ( ! $screen || 'post' !== $screen->base || $this->plugin->get_setting( 'post_type' ) !== $screen->post_type ) {
				return;
			}
			$reload_ms = absint( $this->plugin->get_setting( 'reload_ms' ) );
			?>
			<script>
				document.addEventListener('DOMContentLoaded', function () {
					var links = document.querySelectorAll('.nm-pinterest-action a');

					links.forEach(function (link) {
						link.removeAttribute('target');
						link.removeAttribute('rel');

						link.addEventListener('click', function (event) {
							event.preventDefault();

							if (link.dataset.nmBusy === '1') {
								return;
							}

							var actionWrap = link.closest('.nm-pinterest-action');
							var originalText = link.textContent;
							var loadingText = 'Working...';

							if (actionWrap && actionWrap.classList.contains('nm-pinterest-action--generate')) {
								loadingText = 'Generating Pinterest Pin...';
							} else if (actionWrap && actionWrap.classList.contains('nm-pinterest-action--share')) {
								loadingText = 'Sharing to Pinterest...';
							}

							link.dataset.nmBusy = '1';
							link.textContent = loadingText;
							link.style.pointerEvents = 'none';
							link.style.opacity = '0.7';

							fetch(link.href, {
								method: 'GET',
								credentials: 'same-origin'
							})
							.then(function (response) {
								if (!response.ok) {
									throw new Error('Magic Link request failed.');
								}

								setTimeout(function () {
									window.location.reload();
								}, <?php echo (int) $reload_ms; ?>);
							})
							.catch(function () {
								link.dataset.nmBusy = '0';
								link.textContent = originalText;
								link.style.pointerEvents = '';
								link.style.opacity = '';
								alert('The Pinterest request could not be completed.');
							});
						});
					});
				});
			</script>
			<?php
		}

		/**
		 * Add admin list column.
		 *
		 * @param array<string,string> $columns List columns.
		 * @return array<string,string>
		 */
		public function add_posts_list_column( $columns ) {
			$new_columns = array();

			foreach ( $columns as $key => $label ) {
				if ( 'date' === $key ) {
					$new_columns['nm_pinterest_shared'] = 'Pin Shared';
				}
				$new_columns[ $key ] = $label;
			}

			if ( ! isset( $new_columns['nm_pinterest_shared'] ) ) {
				$new_columns['nm_pinterest_shared'] = 'Pin Shared';
			}

			return $new_columns;
		}

		/**
		 * Render admin list column.
		 *
		 * @param string $column  Column slug.
		 * @param int    $post_id Post ID.
		 * @return void
		 */
		public function render_posts_list_column( $column, $post_id ) {
			if ( 'nm_pinterest_shared' !== $column ) {
				return;
			}

			$shared_raw = get_post_meta( $post_id, $this->plugin->get_meta_key( 'shared' ), true );
			$shared     = $this->plugin->is_truthy( $shared_raw );

			if ( ! $shared ) {
				echo '<span class="nm-pinterest-list-no">No</span>';
				return;
			}

			$shared_at_raw  = get_post_meta( $post_id, $this->plugin->get_meta_key( 'shared_at' ), true );
			$shared_at_date = $this->plugin->format_date_only( $shared_at_raw );

			echo '<span class="nm-pinterest-list-yes">Yes</span>';
			if ( '' !== $shared_at_date ) {
				echo '<span class="nm-pinterest-list-date">' . esc_html( $shared_at_date ) . '</span>';
			}
		}

		/**
		 * Make the column sortable.
		 *
		 * @param array<string,string> $sortable Sortable columns.
		 * @return array<string,string>
		 */
		public function make_column_sortable( $sortable ) {
			$sortable['nm_pinterest_shared'] = 'nm_pinterest_shared';
			return $sortable;
		}

		/**
		 * Render the shared filter dropdown.
		 *
		 * @return void
		 */
		public function render_shared_filter_dropdown() {
			$screen = get_current_screen();
			if ( ! $screen || $this->plugin->get_setting( 'post_type' ) !== $screen->post_type ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter dropdown reads from the URL; no state change.
			$current = isset( $_GET['nm_pinterest_shared_filter'] ) ? sanitize_key( wp_unslash( $_GET['nm_pinterest_shared_filter'] ) ) : '';
			?>
			<select name="nm_pinterest_shared_filter">
				<option value="">All pins</option>
				<option value="shared" <?php selected( $current, 'shared' ); ?>>Shared</option>
				<option value="not_shared" <?php selected( $current, 'not_shared' ); ?>>Not shared</option>
			</select>
			<?php
		}

		/**
		 * Modify the admin list query for filtering and sortable setup.
		 *
		 * @param WP_Query $query Query object.
		 * @return void
		 */
		public function modify_admin_list_query( $query ) {
			if ( ! is_admin() || ! $query->is_main_query() ) {
				return;
			}

			$post_type = (string) $this->plugin->get_setting( 'post_type' );
			if ( $post_type !== $query->get( 'post_type' ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List table filter; read-only URL parameter.
			$filter = isset( $_GET['nm_pinterest_shared_filter'] ) ? sanitize_key( wp_unslash( $_GET['nm_pinterest_shared_filter'] ) ) : '';
			if ( 'shared' === $filter ) {
				$query->set(
					'meta_query',
					array(
						array(
							'key'     => $this->plugin->get_meta_key( 'shared' ),
							'value'   => '1',
							'compare' => '=',
						),
					)
				);
			} elseif ( 'not_shared' === $filter ) {
				$query->set(
					'meta_query',
					array(
						'relation' => 'OR',
						array(
							'key'     => $this->plugin->get_meta_key( 'shared' ),
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => $this->plugin->get_meta_key( 'shared' ),
							'value'   => '1',
							'compare' => '!=',
						),
					)
				);
			}
		}

		/**
		 * Handle custom sorting for the Pin Shared column.
		 *
		 * @param array<string,string> $clauses SQL clauses.
		 * @param WP_Query             $query   Query object.
		 * @return array<string,string>
		 */
		public function handle_shared_column_sorting( $clauses, $query ) {
			if ( ! is_admin() || ! $query->is_main_query() ) {
				return $clauses;
			}

			$post_type = (string) $this->plugin->get_setting( 'post_type' );
			if ( $post_type !== $query->get( 'post_type' ) ) {
				return $clauses;
			}

			if ( 'nm_pinterest_shared' !== $query->get( 'orderby' ) ) {
				return $clauses;
			}

			global $wpdb;

			$alias = 'nm_pinterest_shared_at_pm';
			$meta  = $this->plugin->get_meta_key( 'shared_at' );
			$order = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';

			if ( false === strpos( $clauses['join'], $alias ) ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $alias is a hardcoded string; $wpdb->postmeta and $wpdb->posts are safe table names.
				$clauses['join'] .= $wpdb->prepare(
					" LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$wpdb->posts}.ID = {$alias}.post_id AND {$alias}.meta_key = %s) ",
					$meta
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			$clauses['orderby'] = "CASE WHEN {$alias}.meta_value IS NULL OR {$alias}.meta_value = '' THEN 1 ELSE 0 END ASC, CAST({$alias}.meta_value AS UNSIGNED) {$order}, {$wpdb->posts}.post_date DESC";

			return $clauses;
		}

		/**
		 * Print list table styles.
		 *
		 * @return void
		 */
		public function print_list_styles() {
			$screen = get_current_screen();
			if ( ! $screen || 'edit-' . $this->plugin->get_setting( 'post_type' ) !== $screen->id ) {
				return;
			}
			?>
			<style>
				.column-nm_pinterest_shared {
					width: 90px;
				}
				.nm-pinterest-list-yes {
					display: inline-block;
					font-weight: 600;
					color: #19612c;
				}
				.nm-pinterest-list-no {
					display: inline-block;
					color: #8a1f1f;
				}
				.nm-pinterest-list-date {
					display: block;
					margin-top: 2px;
					font-size: 12px;
					line-height: 1.35;
					color: #646970;
				}
			</style>
			<?php
		}
	}
}
