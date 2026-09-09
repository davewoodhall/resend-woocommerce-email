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
	 * @param string $order_ref Displayed / sequential order number (never a post ID lookup).
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
				/* translators: %s: sequential / displayed order number */
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
	 * Finds an order by sequential / displayed order number only.
	 *
	 * Never resolves by WordPress or HPOS post ID alone. The submitted value
	 * must match `_order_number` (or related) meta and/or
	 * `WC_Order::get_order_number()` — e.g. Foliole `9993` → post `30657`.
	 *
	 * Lookup never returns an arbitrary “latest” order: every candidate must
	 * verify against the submitted number.
	 *
	 * Resolution order: order-tracking filter → order-number meta keys
	 * (verified on the meta value) → bounded `get_order_number()` scan.
	 *
	 * @since 1.0.0
	 * @since 1.0.1 Reject unverified meta_query hits; drop post ID fallback.
	 *
	 * @param string $ref Sequential / displayed order number.
	 * @return WC_Order|null
	 *
	 * @see https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-order-functions.html
	 * @see https://developer.woocommerce.com/docs/features/orders/wc-get-orders/
	 */
	private function find_order( string $ref ) {
		$ref = $this->normalize_order_ref( $ref );
		if ( '' === $ref ) {
			return null;
		}

		$mapped_id = absint( apply_filters( 'woocommerce_shortcode_order_tracking_order_id', $ref ) );
		if ( $mapped_id > 0 && (string) $mapped_id !== $ref ) {
			$mapped = wc_get_order( $mapped_id );
			if ( $this->is_shop_order( $mapped ) && $this->order_number_matches( $mapped, $ref ) ) {
				return $mapped;
			}
		}

		$by_meta = $this->find_order_by_number_meta( $ref );
		if ( $by_meta ) {
			return $by_meta;
		}

		return $this->find_order_by_displayed_number( $ref );
	}

	/**
	 * Normalizes a user-supplied order reference.
	 *
	 * @since 1.0.1
	 *
	 * @param string $ref Raw order reference.
	 * @return string
	 */
	private function normalize_order_ref( string $ref ): string {
		$ref = trim( $ref );
		$ref = ltrim( $ref, "# \t" );

		return $ref;
	}

	/**
	 * Whether a value is a shop order suitable for order emails.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed $order Candidate order object.
	 * @return bool
	 */
	private function is_shop_order( $order ): bool {
		return $order instanceof WC_Order && 'shop_order' === $order->get_type();
	}

	/**
	 * Whether an order’s sequential / displayed number matches a reference.
	 *
	 * Checks `get_order_number()` and the `_order_number` meta (Foliole-style
	 * sequential numbering). Both sides are normalized (trim + leading `#`).
	 *
	 * @since 1.0.1
	 *
	 * @param WC_Order $order Shop order.
	 * @param string   $ref   Normalized order reference.
	 * @return bool
	 *
	 * @see https://woocommerce.github.io/code-reference/classes/WC-Order.html#method_get_order_number
	 */
	private function order_number_matches( WC_Order $order, string $ref ): bool {
		if ( $this->normalize_order_ref( (string) $order->get_order_number() ) === $ref ) {
			return true;
		}

		$meta = $order->get_meta( '_order_number', true );
		return is_scalar( $meta ) && $this->normalize_order_ref( (string) $meta ) === $ref;
	}

	/**
	 * Returns the first shop order whose given meta key equals `$ref`.
	 *
	 * Meta value is verified on the loaded order so an ignored `wc_get_orders`
	 * meta filter cannot return the latest order by accident.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed  $ids      Candidate order IDs from `wc_get_orders()`.
	 * @param string $ref      Normalized order reference.
	 * @param string $meta_key Meta key that was queried.
	 * @return WC_Order|null
	 */
	private function first_order_matching_meta( $ids, string $ref, string $meta_key ) {
		if ( empty( $ids ) || ! is_array( $ids ) || '' === $meta_key ) {
			return null;
		}

		foreach ( $ids as $order_id ) {
			$order = wc_get_order( (int) $order_id );
			if ( ! $this->is_shop_order( $order ) ) {
				continue;
			}

			$meta = $order->get_meta( $meta_key, true );
			if ( is_scalar( $meta ) && $this->normalize_order_ref( (string) $meta ) === $ref ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * Finds an order via common custom order-number meta keys.
	 *
	 * Every hit is verified against the actual meta value on the order so an
	 * ignored or unsupported meta query cannot return the latest order.
	 * Both `meta_key`/`meta_value` and `meta_query` forms are tried: a non-empty
	 * but unverified first result set (ignored filter) does not skip the second.
	 *
	 * @since 1.0.1
	 *
	 * @param string $ref Normalized order reference.
	 * @return WC_Order|null
	 *
	 * @see https://developer.woocommerce.com/docs/features/orders/wc-get-orders/
	 */
	private function find_order_by_number_meta( string $ref ) {
		$meta_keys = array(
			'_order_number',
			'_order_number_formatted',
			'_alg_wc_custom_order_number',
			'_alg_wc_full_custom_order_number',
		);

		/**
		 * Filters meta keys used to resolve a displayed order number.
		 *
		 * @since 1.0.1
		 *
		 * @param string[] $meta_keys Meta keys to query.
		 * @param string   $ref       Normalized order reference.
		 */
		$meta_keys = apply_filters( 'resendemail_order_number_meta_keys', $meta_keys, $ref );

		foreach ( $meta_keys as $meta_key ) {
			$meta_key = (string) $meta_key;
			if ( '' === $meta_key ) {
				continue;
			}

			$queries = array(
				array(
					'limit'        => 20,
					'return'       => 'ids',
					'type'         => 'shop_order',
					'status'       => 'any',
					'meta_key'     => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'   => $ref, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'meta_compare' => '=',
				),
				array(
					'limit'      => 20,
					'return'     => 'ids',
					'type'       => 'shop_order',
					'status'     => 'any',
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => $meta_key,
							'value' => $ref,
						),
					),
				),
			);

			foreach ( $queries as $query_args ) {
				$match = $this->first_order_matching_meta( wc_get_orders( $query_args ), $ref, $meta_key );
				if ( $match ) {
					return $match;
				}
			}
		}

		return null;
	}

	/**
	 * Finds an order by comparing `get_order_number()` across shop orders.
	 *
	 * Used when custom numbering is applied only via the `woocommerce_order_number`
	 * filter and is not stored in a queryable meta key.
	 *
	 * @since 1.0.1
	 *
	 * @param string $ref Normalized order reference.
	 * @return WC_Order|null
	 *
	 * @see https://woocommerce.github.io/code-reference/classes/WC-Order.html#method_get_order_number
	 */
	private function find_order_by_displayed_number( string $ref ) {
		$page      = 1;
		$per_page  = 100;
		$max_pages = 50;

		do {
			$orders = wc_get_orders(
				array(
					'limit'   => $per_page,
					'page'    => $page,
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'objects',
					'type'    => 'shop_order',
					'status'  => 'any',
				)
			);

			if ( empty( $orders ) || ! is_array( $orders ) ) {
				break;
			}

			foreach ( $orders as $order ) {
				if ( $this->is_shop_order( $order ) && $this->order_number_matches( $order, $ref ) ) {
					return $order;
				}
			}

			++$page;
		} while ( $page <= $max_pages && count( $orders ) === $per_page );

		return null;
	}
}
