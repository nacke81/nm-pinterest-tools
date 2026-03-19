<?php
/**
 * Unit tests for NM_Pinterest_Tools_Settings::sanitize_settings().
 *
 * @package NM_Pinterest_Tools
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class NM_Pinterest_Tools_Settings_Test extends TestCase {

	/**
	 * Settings instance under test.
	 *
	 * @var NM_Pinterest_Tools_Settings
	 */
	private $settings;

	/**
	 * Plugin instance.
	 *
	 * @var NM_Pinterest_Tools
	 */
	private $plugin;

	/**
	 * Set up before each test.
	 */
	protected function set_up() {
		parent::set_up();
		$this->plugin   = NM_Pinterest_Tools::instance();
		$this->settings = new NM_Pinterest_Tools_Settings( $this->plugin );
	}

	/*
	 * ---------------------------------------------------------------
	 * sanitize_settings()
	 * ---------------------------------------------------------------
	 */

	public function test_sanitize_with_valid_input() {
		$input = array(
			'post_type'           => 'post',
			'image_field_name'    => 'nm_pinterest_image',
			'generate_recipe_id'  => '42',
			'share_recipe_id'     => '99',
			'reload_ms'           => '3000',
			'require_excerpt'     => '1',
			'enable_admin_column' => '1',
		);

		$output = $this->settings->sanitize_settings( $input );

		$this->assertSame( 'post', $output['post_type'] );
		$this->assertSame( 'nm_pinterest_image', $output['image_field_name'] );
		$this->assertSame( 42, $output['generate_recipe_id'] );
		$this->assertSame( 99, $output['share_recipe_id'] );
		$this->assertSame( 3000, $output['reload_ms'] );
		$this->assertSame( 1, $output['require_excerpt'] );
		$this->assertSame( 1, $output['enable_admin_column'] );
	}

	public function test_sanitize_falls_back_to_defaults_on_empty_input() {
		$output   = $this->settings->sanitize_settings( array() );
		$defaults = $this->plugin->get_default_settings();

		$this->assertSame( $defaults['post_type'], $output['post_type'] );
		$this->assertSame( $defaults['image_field_name'], $output['image_field_name'] );
		$this->assertSame( 0, $output['generate_recipe_id'] );
		$this->assertSame( 0, $output['share_recipe_id'] );
		$this->assertSame( (int) $defaults['reload_ms'], $output['reload_ms'] );
		$this->assertSame( 0, $output['require_excerpt'] );
		$this->assertSame( 0, $output['enable_admin_column'] );
	}

	public function test_sanitize_strips_invalid_characters_from_post_type() {
		$input  = array( 'post_type' => 'Post Type With SPACES & Caps!' );
		$output = $this->settings->sanitize_settings( $input );

		// sanitize_key lowercases and strips non-alphanumeric (except - and _).
		$this->assertSame( 'posttypewithspacescaps', $output['post_type'] );
	}

	public function test_sanitize_restores_default_when_post_type_is_empty() {
		$input  = array( 'post_type' => '' );
		$output = $this->settings->sanitize_settings( $input );

		$this->assertSame( 'post', $output['post_type'], 'Empty post_type should fall back to default.' );
	}

	public function test_sanitize_restores_default_when_image_field_name_is_empty() {
		$input  = array( 'image_field_name' => '' );
		$output = $this->settings->sanitize_settings( $input );

		$this->assertSame( 'nm_pinterest_image', $output['image_field_name'], 'Empty image_field_name should fall back to default.' );
	}

	public function test_sanitize_converts_recipe_ids_to_positive_integers() {
		$input = array(
			'generate_recipe_id' => '-5',
			'share_recipe_id'    => 'abc',
		);

		$output = $this->settings->sanitize_settings( $input );

		$this->assertSame( 5, $output['generate_recipe_id'], 'Negative recipe ID should become positive via absint.' );
		$this->assertSame( 0, $output['share_recipe_id'], 'Non-numeric recipe ID should become 0.' );
	}

	public function test_sanitize_reload_ms_cannot_be_negative() {
		$input  = array( 'reload_ms' => '-1000' );
		$output = $this->settings->sanitize_settings( $input );

		$this->assertGreaterThanOrEqual( 0, $output['reload_ms'], 'reload_ms should never be negative.' );
	}

	public function test_sanitize_checkboxes_are_binary() {
		// When checked (value present).
		$input_checked = array(
			'require_excerpt'     => '1',
			'enable_admin_column' => 'on',
		);
		$output_checked = $this->settings->sanitize_settings( $input_checked );
		$this->assertSame( 1, $output_checked['require_excerpt'] );
		$this->assertSame( 1, $output_checked['enable_admin_column'] );

		// When unchecked (key absent — browsers don't send unchecked checkboxes).
		$input_unchecked  = array();
		$output_unchecked = $this->settings->sanitize_settings( $input_unchecked );
		$this->assertSame( 0, $output_unchecked['require_excerpt'] );
		$this->assertSame( 0, $output_unchecked['enable_admin_column'] );
	}

	public function test_sanitize_returns_all_expected_keys() {
		$output = $this->settings->sanitize_settings( array() );

		$expected_keys = array(
			'post_type',
			'image_field_name',
			'generate_recipe_id',
			'share_recipe_id',
			'reload_ms',
			'require_excerpt',
			'enable_admin_column',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $output, "Sanitized output should include '{$key}'." );
		}

		// No extra keys should be present.
		$this->assertCount( count( $expected_keys ), $output, 'Sanitized output should contain exactly the expected keys.' );
	}
}
