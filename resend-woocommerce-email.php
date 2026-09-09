<?php
/**
 * Plugin Name:       Resend WooCommerce Email
 * Description:       Resend a WooCommerce order email to an address of your choice, to validate template changes without notifying the customer.
 * Version:           1.0.1
 * Author:            ADN Communication
 * License:           GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * Text Domain:       resendemail
 * Domain Path:       /languages
 *
 * @package ADN\WooCommerce\ResendEmail
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'RESENDEMAIL_VERSION', '1.0.1' );
define( 'RESENDEMAIL_FILE', __FILE__ );
define( 'RESENDEMAIL_PATH', plugin_dir_path( __FILE__ ) );
define( 'RESENDEMAIL_BASENAME', plugin_basename( __FILE__ ) );

require_once RESENDEMAIL_PATH . 'src/Autoloader.php';

\ADN\WooCommerce\ResendEmail\Autoloader::get_instance();
\ADN\WooCommerce\ResendEmail\Support\HposCompatibility::get_instance()->register_hooks();

/**
 * Loads the plugin textdomain.
 *
 * @since 1.0.0
 *
 * @see https://developer.wordpress.org/reference/functions/load_plugin_textdomain/
 *
 * @return void
 */
function resendemail_load_textdomain(): void {
	load_plugin_textdomain(
		'resendemail',
		false,
		dirname( RESENDEMAIL_BASENAME ) . '/languages'
	);
}
add_action( 'init', 'resendemail_load_textdomain' );

/**
 * Boots the plugin after plugins are loaded.
 *
 * @since 1.0.0
 *
 * @see https://developer.wordpress.org/reference/hooks/plugins_loaded/
 *
 * @return void
 */
function resendemail_bootstrap(): void {
	\ADN\WooCommerce\ResendEmail\Plugin::get_instance()->boot();
}
add_action( 'plugins_loaded', 'resendemail_bootstrap', 20 );
