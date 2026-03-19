<?php
/**
 * Plugin Name: NM Pinterest Tools
 * Description: Pinterest image and sharing tools for WordPress posts, including editor UI, admin list status, and plugin settings.
 * Version: 0.1.0
 * Author: Nacke Media
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: nm-pinterest-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'NM_PINTEREST_TOOLS_VERSION' ) ) {
	define( 'NM_PINTEREST_TOOLS_VERSION', '0.1.0' );
}

if ( ! defined( 'NM_PINTEREST_TOOLS_FILE' ) ) {
	define( 'NM_PINTEREST_TOOLS_FILE', __FILE__ );
}

if ( ! defined( 'NM_PINTEREST_TOOLS_PATH' ) ) {
	define( 'NM_PINTEREST_TOOLS_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'NM_PINTEREST_TOOLS_URL' ) ) {
	define( 'NM_PINTEREST_TOOLS_URL', plugin_dir_url( __FILE__ ) );
}

require_once NM_PINTEREST_TOOLS_PATH . 'includes/class-nm-pinterest-tools.php';
require_once NM_PINTEREST_TOOLS_PATH . 'includes/class-nm-pinterest-tools-settings.php';
require_once NM_PINTEREST_TOOLS_PATH . 'includes/class-nm-pinterest-tools-admin.php';

/**
 * Bootstrap the plugin.
 *
 * @return NM_Pinterest_Tools
 */
function nm_pinterest_tools() {
	return NM_Pinterest_Tools::instance();
}

add_action( 'plugins_loaded', array( nm_pinterest_tools(), 'init' ) );
