<?php
/**
 * Sends a WooCommerce order email to an arbitrary test address.
 *
 * @package ADN\WooCommerce\ResendEmail
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace ADN\WooCommerce\ResendEmail\Email;

use Exception;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves orders and triggers WooCommerce emails without lasting side effects.
 *
 * @since 1.0.0
 */
final class OrderEmailSender {

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
	 * Resends an order email to an arbitrary address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $order_ref Order ID or custom order number.
	 * @param string $to        Destination email address.
	 * @param string $email_id  WooCommerce email identifier.
	 * @param bool   $prefix    Whether to prefix the subject with [TEST].
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function send( string $order_ref, string $to, string $email_id = 'new_order', bool $prefix = true ) {
		if ( ! function_exists( 'WC' ) ) {
			return new WP_Error(
				'resendemail_no_wc',
				__( 'WooCommerce is not active.', 'resendemail' )
			);
		}

		if ( ! AllowedEmails::is_allowed( $email_id ) ) {
			return new WP_Error(
				'resendemail_bad_email',
				/* translators: %s: WooCommerce email ID */
				sprintf( __( 'Email “%s” is not supported.', 'resendemail' ), $email_id )
			);
		}

		if ( ! is_email( $to ) ) {
			return new WP_Error(
				'resendemail_bad_to',
				__( 'Invalid destination address.', 'resendemail' )
			);
		}

		$order = $this->find_order( $order_ref );
		if ( ! $order ) {
			return new WP_Error(
				'resendemail_no_order',
				/* translators: %s: order ID or order number */
				sprintf( __( 'Order “%s” could not be found.', 'resendemail' ), $order_ref )
			);
		}

		$email = $this->resolve_email( $email_id );
		if ( ! $email ) {
			return new WP_Error(
				'resendemail_no_email_obj',
				__( 'This email is not registered in WooCommerce.', 'resendemail' )
			);
		}

		$force_to = static function () use ( $to ) {
			return $to;
		};
		add_filter( 'woocommerce_email_recipient_' . $email_id, $force_to, PHP_INT_MAX );

		$force_on = static function () {
			return true;
		};
		add_filter( 'woocommerce_email_enabled_' . $email_id, $force_on, PHP_INT_MAX );

		$force_subject = null;
		if ( $prefix ) {
			$force_subject = static function ( $subject ) {
				return '[TEST] ' . $subject;
			};
			add_filter( 'woocommerce_email_subject_' . $email_id, $force_subject, PHP_INT_MAX );
		}

		$sent_flag = $order->get_meta( '_new_order_email_sent' );
		if ( 'new_order' === $email_id && $sent_flag ) {
			$order->delete_meta_data( '_new_order_email_sent' );
			$order->save();
		}

		$error = null;
		try {
			$email->trigger( $order->get_id(), $order );
		} catch ( Exception $e ) {
			$error = new WP_Error( 'resendemail_send_failed', $e->getMessage() );
		}

		remove_filter( 'woocommerce_email_recipient_' . $email_id, $force_to, PHP_INT_MAX );
		remove_filter( 'woocommerce_email_enabled_' . $email_id, $force_on, PHP_INT_MAX );
		if ( null !== $force_subject ) {
			remove_filter( 'woocommerce_email_subject_' . $email_id, $force_subject, PHP_INT_MAX );
		}

		if ( 'new_order' === $email_id ) {
			$fresh = wc_get_order( $order->get_id() );
			if ( $fresh ) {
				if ( $sent_flag ) {
					$fresh->update_meta_data( '_new_order_email_sent', $sent_flag );
				} else {
					$fresh->delete_meta_data( '_new_order_email_sent' );
				}
				$fresh->save();
			}
		}

		return null !== $error ? $error : true;
	}

	/**
	 * Finds a registered WooCommerce email object by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_id WooCommerce email identifier.
	 * @return object|null Email object or null when not registered.
	 */
	private function resolve_email( string $email_id ) {
		$emails = WC()->mailer()->get_emails();

		foreach ( $emails as $candidate ) {
			if ( isset( $candidate->id ) && $candidate->id === $email_id ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Finds an order by ID, then by custom order number meta.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ref Order ID or custom order number.
	 * @return WC_Order|null
	 */
	private function find_order( string $ref ) {
		$ref = trim( $ref );
		if ( '' === $ref ) {
			return null;
		}

		$order = wc_get_order( absint( $ref ) );
		if ( $order instanceof WC_Order ) {
			return $order;
		}

		$ids = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => '_order_number',
						'value' => $ref,
					),
				),
			)
		);

		if ( ! empty( $ids ) ) {
			$order = wc_get_order( $ids[0] );
			return $order instanceof WC_Order ? $order : null;
		}

		return null;
	}
}
