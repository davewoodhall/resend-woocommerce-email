# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-09

### Added

- Initial release of **Resend WooCommerce Email** (formerly single-file *Renvoi de courriel de commande*).
- Namespace `ADN\WooCommerce\ResendEmail` with PSR-4 autoloader and lean ADN class layout (`Plugin`, `Admin`, `Cli`, `Email`, `Support`).
- Plugin bootstrap with centralized WooCommerce dependency detection and admin notice when WooCommerce is absent.
- Admin tool under WooCommerce to resend supported order emails to a test address (`Admin\ResendPage`).
- Order email send service with temporary recipient, enabled-state, and optional `[TEST]` subject filters around `WC_Email::trigger()` (`Email\OrderEmailSender`).
- Restoration of `_new_order_email_sent` after resending the admin new-order email.
- WP-CLI command `wp resendemail send` (`Cli\SendCommand`).
- Early HPOS compatibility declaration (`Support\HposCompatibility`).
- Internationalization with text domain `resendemail` and French translation (`languages/`).
- Project documentation: `README.md`, `CHANGELOG.md`, and `commit.md`.

### Changed

- Renamed plugin folder and bootstrap file to `resend-woocommerce-email`.
- Replaced `rcc-` / `rcc_` technical prefixes with `resendemail-` / `resendemail_`.
- Switched user-facing source strings to English (French provided via translation files).
