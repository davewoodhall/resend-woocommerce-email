<?php
/**
 * Runtime dependency detection for optional and required integrations.
 *
 * @package ADN\WooCommerce\ResendEmail
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace ADN\WooCommerce\ResendEmail\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized checks for third-party runtime availability.
 *
 * Stateless utility: methods are static by design.
 *
 * @since 1.0.0
 */
final class DependencyDetector {

	/**
	 * Whether WooCommerce is loaded and usable.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}
}
