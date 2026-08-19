# WooCommerce Custom Email Templates

Design modern, branded WooCommerce transactional emails with a drag-and-drop builder — without ever editing WooCommerce's own files.

| | |
|---|---|
| **Stable tag** | 1.1.0 |
| **Requires WordPress** | 6.4+ (tested to 7.0) |
| **Requires PHP** | 8.1+ |
| **Requires WooCommerce** | 7.0+ (tested to 11.0) |
| **HPOS** | Compatible |
| **License** | [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html) |
| **Contributors** | manpreetdev21 |
| **Tags** | woocommerce, email, templates, email builder, transactional email |

---

## Description

WooCommerce Custom Email Templates lets you replace the look of any WooCommerce email — New Order, Processing, Completed, Customer Invoice and more — with your own drag-and-drop design, while WooCommerce keeps sending the mail exactly as it always has.

- Automatic discovery of every WooCommerce email type, including ones added by other plugins
- A block-based builder: header, columns, heading, text, image, button, divider, spacer, custom HTML, order details, order totals, customer details, footer
- Reusable components — build a section once, insert it into any template, re-sync them all when it changes
- Version history for every template, with one-click restore
- A starter library with live previews: Minimal, Modern Store, Premium, Dark, Compact and Blank
- Global colors, typography and spacing, applied across the whole template
- Dynamic `{tag}` placeholders for customer and order data
- Preheader (inbox preview text) support
- Desktop, tablet and mobile preview, with sample data or a real order
- Send-test-email, with a send throttle
- Duplicate, delete, reset to WooCommerce default
- JSON import/export
- A guided first-run setup
- Never modifies a WooCommerce file — overrides are applied through WooCommerce's own hooks and are instantly reversible

### Requirements

WooCommerce must be installed and active.

## Installation

1. Upload the plugin to `/wp-content/plugins/woo-custom-email-templates`.
2. Activate it through the **Plugins** screen.
3. Open **Email Templates** in the admin sidebar (just below the WooCommerce menus) to get started.

## Frequently Asked Questions

### Does this change any WooCommerce files?

No. Templates are injected at send time through WooCommerce's own `woocommerce_mail_content` and `woocommerce_email_subject_{id}` filters. Turning an override off restores WooCommerce's own template immediately, and your WooCommerce email settings are never written to.

### What happens to my templates if I deactivate the plugin?

Nothing is deleted. Emails simply revert to WooCommerce's own templates. Data is only removed on uninstall, and only if you tick **Delete plugin data on uninstall** in Settings.

## For developers

Root namespace: `WCEM\`, autoloaded PSR-4 from `includes/`. No Composer dependency.

| Namespace | Responsibility |
|---|---|
| `WCEM\Core\` | Plugin bootstrap, settings, the autoloader |
| `WCEM\Admin\` | Menu, screens, form handlers, AJAX endpoints |
| `WCEM\Builder\` | Block registry, sanitizers and email-safe renderers |
| `WCEM\Templates\` | Storage, rendering, components, versions, starter library |
| `WCEM\Email\` | WooCommerce email discovery, tags, preview and test sending |
| `WCEM\WooCommerce\` | The override bridge onto WooCommerce's own filters |
| `WCEM\Tools\` | JSON import/export |

### How an override is applied

```text
WooCommerce sends an email
        ↓
woocommerce_email_header  →  Bridge captures which WC_Email it is
        ↓
woocommerce_mail_content  →  Bridge looks up the assigned template
        ↓
        is it assigned, enabled, and published?  ── no ──▶  WooCommerce's own HTML
        ↓ yes
TemplateRenderer renders blocks + styles, resolves {tags}
        ↓
        our HTML replaces the body
```

### Actions and filters

| Hook | Type | Purpose |
|---|---|---|
| `wcem_template_blocks` | filter | Filter the block registry (add your own block type). |
| `wcem_custom_email_block` | filter | Render a block type the plugin does not know. |
| `wcem_email_tags` | filter | Filter the resolved `{tag}` values for a rendering context. |
| `wcem_available_templates` | filter | Filter the starter library. |
| `wcem_before_render_email` | action | Fires before a template is rendered. |
| `wcem_after_render_email` | filter | Filter the finished email HTML. |
| `wcem_email_template_assigned` | action | Fires when a template is assigned to an email type. |
| `wcem_before_send_test_email` | action | Fires before a test email is rendered and sent. |
| `wcem_menu_position` | filter | Filter where the plugin's admin menu sits. |

### Backwards compatibility

Pre-1.1 class names (`WCEM_Plugin`, `WCEM_Template_Post_Type`, `WCEM_Blocks`, …) and the `wcem_block()` / `wcem_install_starter_templates()` helpers still resolve through a deprecated compatibility layer in `includes/compat.php`. They are lazily aliased, so nothing extra loads unless a legacy name is actually used.

### Tests

Four plain-PHP suites, no PHPUnit or Composer required. See [`tests/README.md`](tests/README.md).

```sh
php tests/smoke-test.php          # pure logic, no WordPress needed
php tests/integration-core.php    # live bootstrap, CRUD, blocks, override bridge
php tests/integration-screens.php # all admin screens render without notices
php tests/integration-ajax.php    # every AJAX endpoint, nonces and caps included
```

## Changelog

### 1.1.0

- **Fixed: WooCommerce order blocks could send fabricated order data to real customers.** Order Details, Order Totals and Customer Details fell back to demo content (`Classic T-Shirt × 2`, `123 Sample St`) whenever no order was present — including in a live send. A template containing one of those blocks assigned to New Account or Reset Password, which carry a user and never an order, mailed that placeholder basket and address to the customer. Demo content is now gated to editor previews; in a live send those blocks render nothing.
- **Fixed: the template editor, component editor and onboarding wizard were unreachable.** `remove_submenu_page()` during `admin_menu` made WordPress resolve the wrong page hook, so all three died with "Sorry, you are not allowed to access this page." They are now hidden on `admin_head`, after the access check.
- Previews now fill customer and order tags with demo values instead of leaving blanks, so the editor no longer shows "Hi ,". Live sends are unaffected.
- Declared High-Performance Order Storage (HPOS) compatibility. WooCommerce previously listed the plugin as "uncertain" and warned administrators before enabling HPOS.
- Verified against WordPress 7.0 and WooCommerce 11; tested-up-to headers raised accordingly.
- Refactored the whole plugin onto the `WCEM\` namespace with a PSR-4 autoloader; every pre-1.1 class name still works through a deprecated alias layer.
- Moved the plugin to its own top-level admin menu instead of nesting inside WooCommerce's submenu.
- Added reusable components, with re-sync into the templates that use them.
- Added template version history and restore, built on WordPress post revisions.
- Added a template library screen with live previews and category filtering.
- Added a first-run setup wizard.
- Added a Columns block (2 or 3 email-safe table columns).
- Added tablet preview, plus sorting and pagination on the templates list.
- Added integration test suites covering the admin screens and every AJAX endpoint.
- Fixed: the preheader / preview text field was saved but never rendered into the email.
- Fixed: the global Link Color setting had no effect on links in text and footer blocks.
- Security: capped the size of uploaded import files, and quoted the display name in the test-email `From` header.
- Accessibility: keyboard block reordering (<kbd>Alt</kbd>+<kbd>↑</kbd>/<kbd>↓</kbd>), labels on every control, <kbd>Esc</kbd> closes dialogs.

### 1.0.0

- Initial release.
