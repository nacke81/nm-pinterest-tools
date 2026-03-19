<?php
/**
 * Core plugin class.
 *
 * @package NM_Pinterest_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'NM_Pinterest_Tools' ) ) {

	/**
	 * Main NM Pinterest Tools singleton.
	 *
	 * Handles settings retrieval, meta key definitions, ACF field group
	 * registration, and date/time utilities.
	 */
	class NM_Pinterest_Tools {

		/**
		 * Singleton instance.
		 *
		 * @var NM_Pinterest_Tools|null
		 */
		private static $instance = null;

		/**
		 * Plugin settings.
		 *
		 * @var array<string,mixed>
		 */
		private $settings = array();

		/**
		 * Settings handler.
		 *
		 * @var NM_Pinterest_Tools_Settings|null
		 */
		private $settings_page = null;

		/**
		 * Admin handler.
		 *
		 * @var NM_Pinterest_Tools_Admin|null
		 */
		private $admin = null;

		/**
		 * Get singleton instance.
		 *
		 * @return NM_Pinterest_Tools
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Initialize the plugin.
		 *
		 * @return void
		 */
		public function init() {
			$this->settings = wp_parse_args( get_option( $this->get_option_name(), array() ), $this->get_default_settings() );

			add_action( 'acf/include_fields', array( $this, 'register_acf_field_group' ) );

			if ( is_admin() ) {
				$this->settings_page = new NM_Pinterest_Tools_Settings( $this );
				$this->settings_page->init();

				$this->admin = new NM_Pinterest_Tools_Admin( $this );
				$this->admin->init();
			}
		}

		/**
		 * Plugin option name.
		 *
		 * @return string
		 */
		public function get_option_name() {
			return 'nm_pinterest_tools_settings';
		}

		/**
		 * Default settings.
		 *
		 * @return array<string,mixed>
		 */
		public function get_default_settings() {
			return array(
				'post_type'           => 'post',
				'image_field_name'    => 'nm_pinterest_image',
				'generate_recipe_id'  => 0,
				'share_recipe_id'     => 0,
				'reload_ms'           => 5000,
				'require_excerpt'     => 1,
				'enable_admin_column' => 1,
			);
		}

		/**
		 * Get all settings.
		 *
		 * @return array<string,mixed>
		 */
		public function get_settings() {
			if ( empty( $this->settings ) ) {
				$this->settings = wp_parse_args( get_option( $this->get_option_name(), array() ), $this->get_default_settings() );
			}

			return $this->settings;
		}

		/**
		 * Get a single setting.
		 *
		 * @param string $key Setting key.
		 * @return mixed
		 */
		public function get_setting( $key ) {
			$settings = $this->get_settings();

			return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
		}

		/**
		 * Fixed meta keys used by the plugin.
		 *
		 * @return array<string,string>
		 */
		public function get_meta_keys() {
			return array(
				'shared'          => '_nm_pinterest_shared',
				'shared_at'       => '_nm_pinterest_shared_at',
				'pin_url'         => '_nm_pinterest_pin_url',
				'pin_id'          => '_nm_pinterest_pin_id',
				'shared_via'      => '_nm_pinterest_shared_via',
				'last_attempt_at' => '_nm_pinterest_last_attempt_at',
				'last_error'      => '_nm_pinterest_last_error',
			);
		}

		/**
		 * Get one meta key by slug.
		 *
		 * @param string $key Key slug.
		 * @return string
		 */
		public function get_meta_key( $key ) {
			$meta_keys = $this->get_meta_keys();

			return isset( $meta_keys[ $key ] ) ? $meta_keys[ $key ] : '';
		}

		/**
		 * Whether a value should count as truthy for share state.
		 *
		 * @param mixed $value Meta value.
		 * @return bool
		 */
		public function is_truthy( $value ) {
			if ( is_bool( $value ) ) {
				return $value;
			}

			$value = strtolower( trim( (string) $value ) );

			return in_array( $value, array( '1', 'true', 'yes', 'y', 'shared' ), true );
		}

		/**
		 * Format a date/time value for admin display.
		 *
		 * @param mixed       $raw    Raw stored value.
		 * @param string|null $format Optional format.
		 * @return string
		 */
		public function format_datetime( $raw, $format = null ) {
			$timestamp = $this->parse_timestamp( $raw );

			if ( ! $timestamp ) {
				return '';
			}

			if ( null === $format ) {
				$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			}

			return wp_date( $format, $timestamp );
		}

		/**
		 * Format a date-only value for compact list table display.
		 *
		 * @param mixed $raw Raw stored value.
		 * @return string
		 */
		public function format_date_only( $raw ) {
			$timestamp = $this->parse_timestamp( $raw );

			if ( ! $timestamp ) {
				return '';
			}

			return wp_date( 'n/j/y', $timestamp );
		}

		/**
		 * Parse a stored date or timestamp into a Unix timestamp.
		 *
		 * @param mixed $raw Raw stored value.
		 * @return int|false
		 */
		public function parse_timestamp( $raw ) {
			if ( empty( $raw ) ) {
				return false;
			}

			if ( is_numeric( $raw ) ) {
				return (int) $raw;
			}

			$parsed = strtotime( (string) $raw );

			return $parsed ? $parsed : false;
		}

		/**
		 * Register the ACF field group used by the editor.
		 *
		 * This runs on all requests so the Pinterest image field remains
		 * available to front-end integrations like Elementor dynamic tags.
		 *
		 * @return void
		 */
		public function register_acf_field_group() {
			if ( ! function_exists( 'acf_add_local_field_group' ) ) {
				return;
			}

			$post_type        = (string) $this->get_setting( 'post_type' );
			$image_field_name = (string) $this->get_setting( 'image_field_name' );

			if ( '' === $post_type || '' === $image_field_name ) {
				return;
			}

			acf_add_local_field_group(
				array(
					'key'      => 'group_nm_pinterest_tools',
					'title'    => 'Pinterest',
					'fields'   => array(
						array(
							'key'           => 'field_nm_pinterest_image',
							'label'         => 'Pinterest Image',
							'name'          => $image_field_name,
							'type'          => 'image',
							'instructions'  => '2:3 aspect ratio',
							'required'      => 0,
							'return_format' => 'id',
							'library'       => 'all',
							'preview_size'  => 'medium',
						),
						array(
							'key'       => 'field_nm_pinterest_tools_ui',
							'label'     => '',
							'name'      => 'nm_pinterest_tools_ui',
							'type'      => 'message',
							'message'   => '',
							'new_lines' => 'wpautop',
							'esc_html'  => 0,
							'wrapper'   => array(
								'class' => 'nm-pinterest-tools-ui',
							),
						),
					),
					'location' => array(
						array(
							array(
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => $post_type,
							),
						),
					),
					'position' => 'side',
					'style'    => 'default',
					'active'   => true,
				)
			);
		}
	}
}
