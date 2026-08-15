<?php
/**
 * Plugin Name:       GPLlib Connector
 * Plugin URI:        https://gpllib.com/connector
 * Description:        将本站与 GPLlib 账户绑定，对「已购买 / 会员有效期内」且 GPLlib 标注支持自动更新的主题/插件实现自动更新。
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            GPL Library Team
 * Author URI:        https://gpllib.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gpllib-connector
 * Domain Path:       /languages
 *
 * @package GPLlib\Connector\Plugin
 */

defined( 'ABSPATH' ) || exit;

define( 'GPLLIB_CONNECTOR_VERSION', '1.2.0' );
define( 'GPLLIB_CONNECTOR_FILE', __FILE__ );
define( 'GPLLIB_CONNECTOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'GPLLIB_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );


if ( ! defined( 'GPLLIB_CONNECTOR_DEFAULT_API_BASE' ) ) {
    define( 'GPLLIB_CONNECTOR_DEFAULT_API_BASE', 'https://gpllib.com/wp-json/gpl/v1' );
}

require_once GPLLIB_CONNECTOR_DIR . 'includes/Bootstrap.php';


add_action( 'init', static function () {
    load_plugin_textdomain( 'gpllib-connector', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

add_action( 'plugins_loaded', [ \GPLlib\Connector\Plugin\Bootstrap::class, 'init' ] );

register_deactivation_hook( __FILE__, [ \GPLlib\Connector\Plugin\Bootstrap::class, 'on_deactivate' ] );
