## Commit message

Fix order lookup by _order_number only

## Commit description

- Stop returning the latest order when `_order_number` meta queries do not match or are ignored
- Resolve orders only by sequential / displayed number (`_order_number` and related metas, verified), order-tracking filter, then bounded number scan
- Never look up by WordPress/HPOS post ID alone (e.g. Foliole `9993` → post `30657`)
- Strip a leading `#` from the submitted order reference; update admin/CLI/README copy
- Bump version to 1.0.1 and document the fix in CHANGELOG/README
