# Resend WooCommerce Email

WordPress / WooCommerce plugin that **resends an order email** to a test address, so you can validate email template changes **without notifying the customer** or the usual recipients.

**Text domain:** `resendemail`  
**Version:** 1.0.0  
**Licence:** GPL-2.0-or-later

---

## What does this plugin do?

When customizing WooCommerce emails (HTML, styles, copy, hooks, theme overrides), you often need to **see the real rendered output** of an email tied to an existing order.

Without a dedicated tool, the usual options fall short:

- triggering a real status change (risk of side effects on the order and genuine notifications);
- using preview plugins that do not always follow the same send path as `WC_Email::trigger()`;
- resending manually from the admin with the original recipients.

**Resend WooCommerce Email** addresses this by:

1. selecting an existing order (WordPress ID or displayed order number);
2. choosing a supported WooCommerce email type;
3. temporarily forcing the recipient to a test address;
4. running the same send mechanism as WooCommerce (`trigger`);
5. restoring the order state (notably the `_new_order_email_sent` meta) so **no lasting trace** remains on the store.

The end customer and administrators configured as usual recipients **receive nothing**.

---

## Requirements

| Item | Requirement |
|------|-------------|
| WordPress | 6.0 or later |
| PHP | 7.4 or later |
| WooCommerce | 7.0 or later (required) |
| Capability | `manage_woocommerce` to use the admin tool / send |

The plugin is **independent** of adncomm-core, ACF, and Elementor. If it is activated without WooCommerce, an admin notice is shown and no features are booted.

Declared compatibility with **HPOS** (High-Performance Order Storage / custom order tables).

---

## Installation

1. Copy the `resend-woocommerce-email` folder into `wp-content/plugins/`.
2. Activate **Resend WooCommerce Email** from Plugins.
3. Confirm WooCommerce is active.
4. (Optional) Ensure the site / user locale loads translations from `languages/` (file `resendemail-fr_FR.mo`).

---

## Usage (admin)

1. Go to **WooCommerce → Resend email** (French UI label: *Renvoi de courriel*).
2. Fill in:
   - **Order number:** WordPress ID (`123`) or custom displayed number (`_order_number` meta);
   - **Recipient:** test address (prefilled with the logged-in user’s email);
   - **Email:** email type to resend;
   - **Options:** prefix the subject with `[TEST]` (on by default).
3. Click **Send**.

A success or error message is shown after redirect.

### Supported emails

Only emails whose `trigger()` expects a single order:

| WooCommerce ID | Label (EN) |
|----------------|------------|
| `new_order` | New order (admin) |
| `customer_processing_order` | Processing order (customer) |
| `customer_on_hold_order` | Order on hold (customer) |
| `customer_completed_order` | Completed order (customer) |
| `customer_invoice` | Order details / invoice (customer) |

Other types (customer notes, refunds, product emails, etc.) are **intentionally** not exposed, to avoid incompatible `trigger()` signatures.

---

## Usage (WP-CLI)

```bash
wp resendemail send <order_ref> --to=<email> [--email=<email_id>] [--no-prefix]
```

| Argument / option | Description |
|-------------------|-------------|
| `<order_ref>` | Order ID or number |
| `--to=` | Destination address (required in practice) |
| `--email=` | Email ID (default: `new_order`) |
| `--no-prefix` | Do not prepend `[TEST]` to the subject |

Examples:

```bash
wp resendemail send 1024 --to=dev@example.com
wp resendemail send 1024 --to=dev@example.com --email=customer_completed_order
wp resendemail send CMD-8891 --to=dev@example.com --email=customer_invoice --no-prefix
```

---

## Technical behaviour (detail)

When sending, the plugin:

1. **Resolves the order** via `wc_get_order( absint( $ref ) )`, then falls back to a query on the `_order_number` meta.
2. **Locates the email object** in `WC()->mailer()->get_emails()` by its `id`.
3. Temporarily adds (priority `PHP_INT_MAX`):
   - `woocommerce_email_recipient_{$email_id}` → test address;
   - `woocommerce_email_enabled_{$email_id}` → `true` (a disabled template remains testable);
   - `woocommerce_email_subject_{$email_id}` → `[TEST]` prefix when requested.
4. For `new_order` only: temporarily removes `_new_order_email_sent` if present (otherwise WooCommerce blocks a second “New order” send), then **restores** the original value after `trigger()`.
5. Calls `$email->trigger( $order_id, $order )`.
6. Removes all filters that were added.

No lasting change to order status, notes, or configured recipients is kept (aside from the meta cycle above, which is restored).

### Security

- Capability `manage_woocommerce` for the menu, rendering, and POST handling.
- Nonce `resendemail_resend_email` via `check_admin_referer()` / `wp_nonce_field()`.
- Input sanitization: `sanitize_text_field`, `sanitize_email`, `sanitize_key`.
- Redirect via `wp_safe_redirect()`.
- Escaping of admin output (`esc_html`, `esc_attr`, `esc_url`).

---

## File structure

Root namespace: `ADN\WooCommerce\ResendEmail` (PSR-4 via `src/Autoloader.php`).

```text
resend-woocommerce-email/
├── resend-woocommerce-email.php   # Bootstrap (constants, autoload, early HPOS, textdomain)
├── src/
│   ├── Autoloader.php
│   ├── Plugin.php                 # Orchestration + missing WooCommerce notice
│   ├── Admin/
│   │   └── ResendPage.php         # Menu, form, admin-post
│   ├── Cli/
│   │   └── SendCommand.php        # wp resendemail send
│   ├── Email/
│   │   ├── AllowedEmails.php      # Catalogue of supported emails
│   │   └── OrderEmailSender.php   # Order resolution + trigger
│   └── Support/
│       ├── DependencyDetector.php
│       └── HposCompatibility.php
├── languages/
│   ├── resendemail.pot
│   ├── resendemail-fr_FR.po
│   └── resendemail-fr_FR.mo
├── README.md
├── CHANGELOG.md
└── commit.md
```

Lean ADN architecture (singletons, autoloader, centralized dependency detection) — no module registry, overridable templates, or microdata.

---

## Internationalization

- **Text domain:** `resendemail`
- **Domain path:** `/languages`
- Source strings in the code are in **English**.
- A **French** translation is provided (`resendemail-fr_FR.*`), carried over from the former monolithic plugin labels.

Loaded via `load_plugin_textdomain()` on the `init` hook.

---

## Known limitations

- Only the five email types listed above.
- Lookup by displayed number relies on the `_order_number` meta (common custom order-number plugins); other schemes may need a later extension.
- The tool is meant for **template testing**, not bulk resend or email delivery tracking.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
