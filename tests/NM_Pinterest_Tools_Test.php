<?php
/**
 * Unit tests for the core NM_Pinterest_Tools class.
 *
 * @package NM_Pinterest_Tools
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class NM_Pinterest_Tools_Test extends TestCase {

	/**
	 * Plugin instance under test.
	 *
	 * @var NM_Pinterest_Tools
	 */
	private $plugin;

	/**
	 * Set up before each test.
	 */
	protected function set_up() {
		parent::set_up();
		$this->plugin = NM_Pinterest_Tools::instance();
	}

	/*
	 * ---------------------------------------------------------------
	 * Singleton
	 * ---------------------------------------------------------------
	 */

	public function test_instance_returns_same_object() {
		$a = NM_Pinterest_Tools::instance();
		$b = NM_Pinterest_Tools::instance();

		$this->assertSame( $a, $b, 'Singleton should return the same instance.' );
	}

	/*
	 * ---------------------------------------------------------------
	 * get_option_name()
	 * ---------------------------------------------------------------
	 */

	public function test_get_option_name_returns_expected_string() {
		$this->assertSame( 'nm_pinterest_tools_settings', $this->plugin->get_option_name() );
	}

	/*
	 * ---------------------------------------------------------------
	 * get_default_settings()
	 * ---------------------------------------------------------------
	 */

	public function test_get_default_settings_returns_array_with_all_expected_keys() {
		$defaults = $this->plugin->get_default_settings();

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
			$this->assertArrayHasKey( $key, $defaults, "Default settings should include '{$key}'." );
		}
	}

	public function test_get_default_settings_has_sensible_values() {
		$defaults = $this->plugin->get_default_settings();

		$this->assertSame( 'post', $defaults['post_type'] );
		$this->assertSame( 'nm_pinterest_image', $defaults['image_field_name'] );
		$this->assertSame( 0, $defaults['generate_recipe_id'] );
		$this->assertSame( 0, $defaults['share_recipe_id'] );
		$this->assertSame( 5000, $defaults['reload_ms'] );
		$this->assertSame( 1, $defaults['require_excerpt'] );
		$this->assertSame( 1, $defaults['enable_admin_column'] );
	}

	/*
	 * ---------------------------------------------------------------
	 * get_meta_keys() / get_meta_key()
	 * ---------------------------------------------------------------
	 */

	public function test_get_meta_keys_returns_all_expected_keys() {
		$meta_keys = $this->plugin->get_meta_keys();

		$expected = array(
			'shared'          => '_nm_pinterest_shared',
			'shared_at'       => '_nm_pinterest_shared_at',
			'pin_url'         => '_nm_pinterest_pin_url',
			'pin_id'          => '_nm_pinterest_pin_id',
			'shared_via'      => '_nm_pinterest_shared_via',
			'last_attempt_at' => '_nm_pinterest_last_attempt_at',
			'last_error'      => '_nm_pinterest_last_error',
		);

		$this->assertSame( $expected, $meta_keys );
	}

	public function test_get_meta_key_returns_correct_key() {
		$this->assertSame( '_nm_pinterest_shared', $this->plugin->get_meta_key( 'shared' ) );
		$this->assertSame( '_nm_pinterest_pin_url', $this->plugin->get_meta_key( 'pin_url' ) );
	}

	public function test_get_meta_key_returns_empty_string_for_unknown_slug() {
		$this->assertSame( '', $this->plugin->get_meta_key( 'nonexistent' ) );
	}

	/*
	 * ---------------------------------------------------------------
	 * is_truthy()
	 * ---------------------------------------------------------------
	 */

	/**
	 * @dataProvider truthy_values_provider
	 */
	public function test_is_truthy_returns_true_for_truthy_values( $value ) {
		$this->assertTrue( $this->plugin->is_truthy( $value ), "Expected is_truthy() to return true for: " . var_export( $value, true ) );
	}

	public function truthy_values_provider() {
		return array(
			'boolean true'    => array( true ),
			'string 1'        => array( '1' ),
			'string true'     => array( 'true' ),
			'string TRUE'     => array( 'TRUE' ),
			'string True'     => array( 'True' ),
			'string yes'      => array( 'yes' ),
			'string YES'      => array( 'YES' ),
			'string y'        => array( 'y' ),
			'string Y'        => array( 'Y' ),
			'string shared'   => array( 'shared' ),
			'string Shared'   => array( 'Shared' ),
			'string SHARED'   => array( 'SHARED' ),
			'padded true'     => array( '  true  ' ),
			'padded 1'        => array( ' 1 ' ),
		);
	}

	/**
	 * @dataProvider falsy_values_provider
	 */
	public function test_is_truthy_returns_false_for_falsy_values( $value ) {
		$this->assertFalse( $this->plugin->is_truthy( $value ), "Expected is_truthy() to return false for: " . var_export( $value, true ) );
	}

	public function falsy_values_provider() {
		return array(
			'boolean false'   => array( false ),
			'string 0'        => array( '0' ),
			'string false'    => array( 'false' ),
			'string no'       => array( 'no' ),
			'string n'        => array( 'n' ),
			'empty string'    => array( '' ),
			'string random'   => array( 'maybe' ),
			'integer 0'       => array( 0 ),
			'integer 2'       => array( 2 ),
		);
	}

	/*
	 * ---------------------------------------------------------------
	 * parse_timestamp()
	 * ---------------------------------------------------------------
	 */

	public function test_parse_timestamp_returns_false_for_empty_values() {
		$this->assertFalse( $this->plugin->parse_timestamp( '' ) );
		$this->assertFalse( $this->plugin->parse_timestamp( null ) );
		$this->assertFalse( $this->plugin->parse_timestamp( 0 ) );
		$this->assertFalse( $this->plugin->parse_timestamp( false ) );
	}

	public function test_parse_timestamp_returns_integer_for_numeric_input() {
		$this->assertSame( 1700000000, $this->plugin->parse_timestamp( 1700000000 ) );
		$this->assertSame( 1700000000, $this->plugin->parse_timestamp( '1700000000' ) );
	}

	public function test_parse_timestamp_parses_date_strings() {
		$result = $this->plugin->parse_timestamp( '2024-01-15 10:30:00' );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	public function test_parse_timestamp_parses_iso8601() {
		$result = $this->plugin->parse_timestamp( '2024-06-15T14:30:00+00:00' );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	public function test_parse_timestamp_returns_false_for_unparseable_string() {
		$this->assertFalse( $this->plugin->parse_timestamp( 'not-a-date' ) );
	}

	/*
	 * ---------------------------------------------------------------
	 * format_date_only()
	 * ---------------------------------------------------------------
	 */

	public function test_format_date_only_returns_expected_format() {
		// 1700000000 = 2023-11-14 in UTC.
		$result = $this->plugin->format_date_only( 1700000000 );

		$this->assertMatchesRegularExpression( '/^\d{1,2}\/\d{1,2}\/\d{2}$/', $result, 'Should match n/j/y format.' );
	}

	public function test_format_date_only_returns_empty_for_empty_input() {
		$this->assertSame( '', $this->plugin->format_date_only( '' ) );
		$this->assertSame( '', $this->plugin->format_date_only( null ) );
	}

	/*
	 * ---------------------------------------------------------------
	 * get_setting()
	 * ---------------------------------------------------------------
	 */

	public function test_get_setting_returns_null_for_unknown_key() {
		$this->assertNull( $this->plugin->get_setting( 'nonexistent_key' ) );
	}
}
