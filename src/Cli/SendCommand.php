<?php
/**
 * WP-CLI command to resend a WooCommerce order email.
 *
 * @package ADN\WooCommerce\ResendEmail
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace ADN\WooCommerce\ResendEmail\Cli;

use ADN\WooCommerce\ResendEmail\Email\OrderEmailSender;
use WP_CLI;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `wp resendemail send`.
 *
 * @since 1.0.0
 */
final class SendCommand {

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
	 * Registers the WP-CLI command when WP-CLI is available.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'resendemail send', array( $this, 'run' ) );
	}

	/**
	 * Executes the send command.
	 *
	 * ## OPTIONS
	 *
	 * <order_ref>
	 * : Order ID or custom order number.
	 *
	 * --to=<email>
	 * : Destination email address.
	 *
	 * [--email=<email_id>]
	 * : WooCommerce email ID. Default: new_order.
	 *
	 * [--no-prefix]
	 * : Do not prefix the subject with [TEST].
	 *
	 * ## EXAMPLES
	 *
	 *     wp resendemail send 1024 --to=dev@example.com
	 *     wp resendemail send 1024 --to=dev@example.com --email=customer_completed_order --no-prefix
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function run( array $args, array $assoc_args ): void {
		$result = OrderEmailSender::get_instance()->send(
			$args[0] ?? '',
			$assoc_args['to'] ?? '',
			$assoc_args['email'] ?? 'new_order',
			! isset( $assoc_args['no-prefix'] )
		);

		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( __( 'Email sent.', 'resendemail' ) );
	}
}
