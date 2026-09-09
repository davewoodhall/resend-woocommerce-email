<?php
/**
 * WooCommerce High-Performance Order Storage compatibility.
 *
 * @package ADN\WooCommerce\ResendEmail
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace ADN\WooCommerce\ResendEmail\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Declares HPOS (custom order tables) compatibility with WooCommerce.
 *
 * @since 1.0.0
 */
final class HposCompatibility {

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
	 * Registers the early WooCommerce compatibility hook.
	 *
	 * Must run before WooCommerce boots so `before_woocommerce_init` is not missed.
	 *
	 * @since 1.0.0
	 *
	 * @see https://developer.wordpress.org/reference/hooks/before_woocommerce_init/
	 * @see https://developer.woocommerce.com/docs/features/high-performance-order-storage/
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
	}

	/**
	 * Declares custom order tables compatibility for this plugin file.
	 *
	 * @since 1.0.0
	 *
	 * @see https://developer.woocommerce.com/docs/features/high-performance-order-storage/
	 *
	 * @return void
	 */
	public function declare_compatibility(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			RESENDEMAIL_FILE,
			true
		);
	}
}
