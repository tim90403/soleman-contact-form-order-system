<?php
/**
 * Plugin Name: Soleman Contact Form Order System
 * Description: 擴充 Elementor Form Submissions：顯示匯款末5碼、確認收款按鈕，並以 Elementor 信件版型寄送確認信。
 * Version: 1.0.0
 * Author: Soleman
 * Text Domain: soleman-contact-form-order-system
 * Requires Plugins: elementor, pro-elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCFOS_VERSION', '1.0.0' );
define( 'SCFOS_FILE', __FILE__ );
define( 'SCFOS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCFOS_URL', plugin_dir_url( __FILE__ ) );
define( 'SCFOS_BASENAME', plugin_basename( __FILE__ ) );

require_once SCFOS_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Soleman_Contact_Form_Order_System\\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Soleman_Contact_Form_Order_System\Plugin::instance();
	},
	20
);
