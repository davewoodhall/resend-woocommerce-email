<?php
/**
 * Plugin bootstrap orchestrator.
 *
 * @package ADN\WooCommerce\ResendEmail
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace ADN\WooCommerce\ResendEmail;

use ADN\WooCommerce\ResendEmail\Admin\ResendPage;
use ADN\WooCommerce\ResendEmail\Cli\SendCommand;
use ADN\WooCommerce\ResendEmail\Support\DependencyDetector;

defined( 'ABSPATH' ) || exit;

/**
 * Boots admin UI and CLI after verifying WooCommerce is available.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var   self|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Starts plugin services once WooCommerce is present.
	 *
	 * @since 1.0.0
	 *
	 * @see https://developer.wordpress.org/reference/hooks/plugins_loaded/
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( ! DependencyDetector::is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
			return;
		}

		ResendPage::get_instance()->register_hooks();
		SendCommand::get_instance()->register();
	}

	/**
	 * Renders an admin notice when WooCommerce is missing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'Resend WooCommerce Email requires WooCommerce to be installed and active.',
			'resendemail'
		);
		echo '</p></div>';
	}
}
