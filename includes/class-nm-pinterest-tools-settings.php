<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'NM_Pinterest_Tools_Settings' ) ) {
	class NM_Pinterest_Tools_Settings {

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
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}

		/**
		 * Add settings page.
		 *
		 * @return void
		 */
		public function add_settings_page() {
			add_options_page(
				'NM Pinterest Tools',
				'NM Pinterest Tools',
				'manage_options',
				'nm-pinterest-tools',
				array( $this, 'render_settings_page' )
			);
		}

		/**
		 * Register settings and fields.
		 *
		 * @return void
		 */
		public function register_settings() {
			register_setting(
				'nm_pinterest_tools_settings_group',
				$this->plugin->get_option_name(),
				array( $this, 'sanitize_settings' )
			);

			add_settings_section(
				'nm_pinterest_tools_main',
				'',
				'__return_false',
				'nm-pinterest-tools'
			);

			add_settings_field(
				'post_type',
				'Post type',
				array( $this, 'render_text_field' ),
				'nm-pinterest-tools',
				'nm_pinterest_tools_main',
				array(
					'key'         => 'post_type',
					'description' => 'The post type that should get the Pinterest editor tools.',
				)
			);

			add_settings_field(
				'image_field_name',
				'Pinterest image field name',
				array( $this, 'render_text_field' ),
				'nm-pinterest-tools',
				'nm_pinterest_tools_main',
				array(
					'key'         => 'image_field_name',
					'description' => 'Keep this stable if your front-end templates already reference the field.',
				)
			);

			add_settings_field(
				'generate_recipe_id',
				'Generate Pin Magic Link recipe ID',
				array( $this, 'render_number_field' ),
				'nm-pinterest-tools',
				'nm_pinterest_tools_main',
				array(
					'key'         => 'generate_recipe_id',
					'description' => 'Used by the Generate Pinterest Pin button.',
				)
			);

			add_settings_field(
				'share_recipe_id',
				'Share to Pinterest Magic Link recipe ID',
				array( $this, 'render_number_field' ),
				'nm-pinterest-tools',
				'nm_pinterest_tools_main',
				array(
					'key'         => 'share_recipe_id',
					'description' => 'Used by the Share to Pinterest button.',
				)
			);

			add_settings_field(
				'reload_ms',
				'Reload delay (ms)',
				array( $this, 'render_number_field' ),
				'nm-pinterest-tools',
				'nm_pinterest_tools_main',
				array(
					'key'         => 'reload_ms',
					'description' => 'How long the editor waits before refreshing after a button is clicked.',
				)
			);

			add_settings_field(
				'require_excerpt',
				'Require excerpt to generate pin',
				array( $this, 'render_checkbox_field' ),
				'nm-pinterest-tools',
				'nm_pinterest_tools_main',
				array(
					'key'         => 'require_excerpt',
					'label'       => 'Disable the Generate button until the post excerpt exists.',
					'description' => '',
				)
			);

			add_settings_field(
				'enable_admin_column',
				'Enable admin list column',
				array( $this, 'render_checkbox_field' ),
				'nm-pinterest-tools',
				'nm_pinterest_tools_main',
				array(
					'key'         => 'enable_admin_column',
					'label'       => 'Show the Pin Shared column on the post list table.',
					'description' => '',
				)
			);
		}

		/**
		 * Sanitize settings.
		 *
		 * @param array<string,mixed> $input Raw input.
		 * @return array<string,mixed>
		 */
		public function sanitize_settings( $input ) {
			$defaults = $this->plugin->get_default_settings();

			$output = array(
				'post_type'           => isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : $defaults['post_type'],
				'image_field_name'    => isset( $input['image_field_name'] ) ? sanitize_key( $input['image_field_name'] ) : $defaults['image_field_name'],
				'generate_recipe_id'  => isset( $input['generate_recipe_id'] ) ? absint( $input['generate_recipe_id'] ) : 0,
				'share_recipe_id'     => isset( $input['share_recipe_id'] ) ? absint( $input['share_recipe_id'] ) : 0,
				'reload_ms'           => isset( $input['reload_ms'] ) ? max( 0, absint( $input['reload_ms'] ) ) : (int) $defaults['reload_ms'],
				'require_excerpt'     => ! empty( $input['require_excerpt'] ) ? 1 : 0,
				'enable_admin_column' => ! empty( $input['enable_admin_column'] ) ? 1 : 0,
			);

			if ( '' === $output['post_type'] ) {
				$output['post_type'] = $defaults['post_type'];
			}

			if ( '' === $output['image_field_name'] ) {
				$output['image_field_name'] = $defaults['image_field_name'];
			}

			return $output;
		}

		/**
		 * Render a simple text field.
		 *
		 * @param array<string,string> $args Field args.
		 * @return void
		 */
		public function render_text_field( $args ) {
			$settings = $this->plugin->get_settings();
			$key      = $args['key'];
			$value    = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
			$name     = $this->plugin->get_option_name() . '[' . $key . ']';

			echo '<input type="text" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';

			if ( ! empty( $args['description'] ) ) {
				echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
			}
		}

		/**
		 * Render a simple number field.
		 *
		 * @param array<string,string> $args Field args.
		 * @return void
		 */
		public function render_number_field( $args ) {
			$settings = $this->plugin->get_settings();
			$key      = $args['key'];
			$value    = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
			$name     = $this->plugin->get_option_name() . '[' . $key . ']';

			echo '<input type="number" class="small-text" min="0" step="1" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';

			if ( ! empty( $args['description'] ) ) {
				echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
			}
		}

		/**
		 * Render a checkbox field.
		 *
		 * @param array<string,string> $args Field args.
		 * @return void
		 */
		public function render_checkbox_field( $args ) {
			$settings = $this->plugin->get_settings();
			$key      = $args['key'];
			$checked  = ! empty( $settings[ $key ] );
			$name     = $this->plugin->get_option_name() . '[' . $key . ']';

			echo '<label>';
			echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $checked, true, false ) . '>';
			echo ' ' . esc_html( $args['label'] );
			echo '</label>';

			if ( ! empty( $args['description'] ) ) {
				echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
			}
		}

		/**
		 * Render the settings page.
		 *
		 * @return void
		 */
		public function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
			<div class="wrap">
				<h1>NM Pinterest Tools</h1>
				<p>These settings control the Pinterest editor tools, Magic Link IDs, and admin list behavior.</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'nm_pinterest_tools_settings_group' ); ?>
					<?php do_settings_sections( 'nm-pinterest-tools' ); ?>
					<?php submit_button(); ?>
				</form>

				<hr>
				<h2>Supported post meta keys</h2>
				<p>These can be written by Uncanny Automator, Make, or any other automation layer:</p>
				<ul style="list-style:disc;margin-left:20px;">
					<li><code><?php echo esc_html( $this->plugin->get_meta_key( 'shared' ) ); ?></code></li>
					<li><code><?php echo esc_html( $this->plugin->get_meta_key( 'shared_at' ) ); ?></code></li>
					<li><code><?php echo esc_html( $this->plugin->get_meta_key( 'pin_url' ) ); ?></code></li>
					<li><code><?php echo esc_html( $this->plugin->get_meta_key( 'pin_id' ) ); ?></code></li>
					<li><code><?php echo esc_html( $this->plugin->get_meta_key( 'shared_via' ) ); ?></code></li>
					<li><code><?php echo esc_html( $this->plugin->get_meta_key( 'last_attempt_at' ) ); ?></code></li>
					<li><code><?php echo esc_html( $this->plugin->get_meta_key( 'last_error' ) ); ?></code></li>
				</ul>
			</div>
			<?php
		}
	}
}
