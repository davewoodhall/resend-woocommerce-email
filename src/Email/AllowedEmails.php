<?php
/**
 * Catalog of WooCommerce emails that this plugin can resend.
 *
 * @package ADN\WooCommerce\ResendEmail
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace ADN\WooCommerce\ResendEmail\Email;

defined( 'ABSPATH' ) || exit;

/**
 * Supported email IDs and translated labels.
 *
 * Limited to emails whose trigger() expects only an order.
 * Stateless utility: methods are static by design.
 *
 * @since 1.0.0
 */
final class AllowedEmails {

	/**
	 * Returns supported WooCommerce email ID => label map.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			'new_order'                 => __( 'New order (admin)', 'resendemail' ),
			'customer_processing_order' => __( 'Processing order (customer)', 'resendemail' ),
			'customer_on_hold_order'    => __( 'On-hold order (customer)', 'resendemail' ),
			'customer_completed_order'  => __( 'Completed order (customer)', 'resendemail' ),
			'customer_invoice'          => __( 'Order details / invoice (customer)', 'resendemail' ),
		);
	}

	/**
	 * Whether the given email ID is supported.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_id WooCommerce email identifier.
	 * @return bool
	 */
	public static function is_allowed( string $email_id ): bool {
		return array_key_exists( $email_id, self::all() );
	}
}
