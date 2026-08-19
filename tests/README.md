# Tests

Four suites, all plain PHP — no PHPUnit, no Composer, nothing to install.

| File | Needs WordPress? | What it covers |
|---|---|---|
| `smoke-test.php` | No | Pure logic: block sanitizers, renderers, tags, style/body JSON round-trips, the starter library. Runs with lightweight stand-ins for the handful of WordPress functions those classes call. |
| `integration-core.php` | Yes | Bootstrap and autoloading in a real request, legacy class aliases, reading pre-1.1 data, WooCommerce email discovery, every block against real WooCommerce, full template CRUD, versioning, components, import/export, and the override bridge driven exactly as WooCommerce drives it. |
| `integration-screens.php` | Yes | Renders all nine admin screens (plus filtered/paged variants) through the real admin stack and asserts no fatals or notices. Also prints the admin menu position so placement regressions are visible. |
| `integration-ajax.php` | Yes | Every `wp_ajax_wcem_*` endpoint with real nonces and capability checks: rejection of bad nonces and under-privileged users, XSS payloads, save/preview/duplicate/delete, assignment, version restore, and test-email sending (intercepted, never actually sent). |

## Running

```sh
php tests/smoke-test.php
php tests/integration-core.php
php tests/integration-screens.php
php tests/integration-ajax.php
```

Each exits non-zero if anything fails, so they chain in CI:

```sh
for t in tests/*.php; do php "$t" || exit 1; done
```

The integration suites locate WordPress relative to their own path
(`dirname( __DIR__, 4 )`), so they work in any install without configuration.

## Safety

The integration suites create their own scratch records — every one is named
`ZZ Scratch …` or `ZZ Ajax …` — and delete them again at the end. Existing
templates are read, never written. `integration-screens.php` is entirely
read-only. Test emails are intercepted via `pre_wp_mail` and never leave the
server.

They do write to the real database, so run them against a development install,
not production.

## Not covered

Browser-side behaviour: the drag-and-drop canvas, the settings panel, modals,
toasts and the JavaScript console. `assets/js/*.js` is syntax-checked with
`node --check`, but its runtime behaviour needs a logged-in browser session.
