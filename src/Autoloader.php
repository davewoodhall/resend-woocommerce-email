<?php
/**
 * PSR-4 autoloader for the Resend WooCommerce Email plugin.
 *
 * @package ADN\WooCommerce\ResendEmail
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace ADN\WooCommerce\ResendEmail;

defined( 'ABSPATH' ) || exit;

/**
 * Maps ADN\WooCommerce\ResendEmail\ to the plugin src/ directory.
 *
 * @since 1.0.0
 */
final class Autoloader {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var   self|null
	 */
	private static $instance = null;

	/**
	 * Absolute path to the src/ directory with trailing slash.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $base_dir;

	/**
	 * Returns the singleton instance and registers the autoloader on first call.
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
	 * Registers spl_autoload for this plugin namespace.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->base_dir = trailingslashit( RESENDEMAIL_PATH ) . 'src' . DIRECTORY_SEPARATOR;
		spl_autoload_register( array( $this, 'load' ) );
	}

	/**
	 * Loads a class file when it belongs to this plugin namespace.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public function load( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = $this->base_dir . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
