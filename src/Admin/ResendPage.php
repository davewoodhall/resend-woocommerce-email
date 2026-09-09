<?php
/**
 * WooCommerce admin page to resend order emails to a test address.
 *
 * @package ADN\WooCommerce\ResendEmail
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace ADN\WooCommerce\ResendEmail\Admin;

use ADN\WooCommerce\ResendEmail\Email\AllowedEmails;
use ADN\WooCommerce\ResendEmail\Email\OrderEmailSender;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the submenu, handles the form POST, and renders the tool UI.
 *
 * @since 1.0.0
 */
final class ResendPage {

	/**
	 * Admin submenu slug under WooCommerce.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const MENU_SLUG = 'resendemail-resend-email';

	/**
	 * Admin form nonce action.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const NONCE = 'resendemail_resend_email';

	/**
	 * Capability required to use the tool.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const CAP = 'manage_woocommerce';

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
	 * Registers admin menu and form handler hooks.
	 *
	 * @since 1.0.0
	 *
	 * @see https://developer.wordpress.org/reference/hooks/admin_menu/
	 * @see https://developer.wordpress.org/reference/hooks/admin_post_action/
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_resendemail_send', array( $this, 'handle_post' ) );
	}

	/**
	 * Registers the WooCommerce submenu page.
	 *
	 * @since 1.0.0
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_submenu_page/
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Resend email', 'resendemail' ),
			__( 'Resend email', 'resendemail' ),
			self::CAP,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handles the admin form POST and redirects with a status notice.
	 *
	 * @since 1.0.0
	 *
	 * @see https://developer.wordpress.org/reference/functions/check_admin_referer/
	 * @see https://developer.wordpress.org/reference/functions/wp_safe_redirect/
	 *
	 * @return void
	 */
	public function handle_post(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'resendemail' ) );
		}

		check_admin_referer( self::NONCE );

		$result = OrderEmailSender::get_instance()->send(
			sanitize_text_field( wp_unslash( $_POST['order_ref'] ?? '' ) ),
			sanitize_email( wp_unslash( $_POST['to'] ?? '' ) ),
			sanitize_key( wp_unslash( $_POST['email_id'] ?? 'new_order' ) ),
			! empty( $_POST['prefix'] )
		);

		$url = add_query_arg(
			array(
				'page'            => self::MENU_SLUG,
				'resendemail_ok'  => is_wp_error( $result ) ? 0 : 1,
				'resendemail_msg' => rawurlencode( is_wp_error( $result ) ? $result->get_error_message() : '' ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Renders the admin tool page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		if ( isset( $_GET['resendemail_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ok  = '1' === $_GET['resendemail_ok']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg = $ok
				? __( 'Email sent.', 'resendemail' )
				: sanitize_text_field( rawurldecode( wp_unslash( $_GET['resendemail_msg'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				$ok ? 'success' : 'error',
				esc_html( $msg )
			);
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Resend an order email', 'resendemail' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'Resends the selected email to a test address. The customer and usual administrators receive nothing, and the order state is not modified.',
					'resendemail'
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="resendemail_send">
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="resendemail_order"><?php echo esc_html__( 'Order number', 'resendemail' ); ?></label>
						</th>
						<td>
							<input name="order_ref" id="resendemail_order" type="text" class="regular-text" required value="">
							<p class="description">
								<?php echo esc_html__( 'Sequential WooCommerce order number (e.g. _order_number meta), not the post ID.', 'resendemail' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="resendemail_to"><?php echo esc_html__( 'Recipient', 'resendemail' ); ?></label>
						</th>
						<td>
							<input name="to" id="resendemail_to" type="email" class="regular-text" required
								value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="resendemail_email"><?php echo esc_html__( 'Email', 'resendemail' ); ?></label>
						</th>
						<td>
							<select name="email_id" id="resendemail_email">
								<?php foreach ( AllowedEmails::all() as $id => $label ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Options', 'resendemail' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="prefix" value="1" checked>
								<?php echo esc_html__( 'Prefix the subject with “[TEST]”', 'resendemail' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Send', 'resendemail' ) ); ?>
			</form>
		</div>
		<?php
	}
}
