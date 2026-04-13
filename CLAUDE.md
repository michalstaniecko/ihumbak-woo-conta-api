# ihumbak-woo-conta-api

WooCommerce plugin integrating orders with Conta.no Norwegian accounting system.

## Purpose

Send WooCommerce orders to Conta.no to create invoices, credit notes, and sales documents. The plugin automates the flow: WooCommerce order → Conta customer → Conta invoice → payment registration. Refunded orders get automatic credit notes.

## Tech Stack

- **PHP 8.0+** — WordPress/WooCommerce plugin
- **WordPress 6.0+** with **WooCommerce 8.0+ (tested to 9.6)**
- **Conta.no REST API** — OpenAPI 3.0.3
- Authentication: API Key via `apiKey` HTTP header
- **Composer** — PSR-4 autoloading, `Ihumbak\WooConta\` → `src/`

## Architecture

```
ihumbak-woo-conta-api.php        # Entry point: constants, HPOS compat, activation hook
src/
  Plugin.php                     # Singleton service container, wires all dependencies
  API/
    Client.php                   # HTTP client (wp_remote_*), auth, error handling, logging
    Endpoints/                   # AbstractEndpoint + Customers, Invoices, Payments, Products, Organizations
    Models/                      # Customer, Invoice, InvoiceLine, Payment (from_wc_order() + to_array())
  Modules/
    CustomerSync.php             # Find-or-create customer (email + VAT lookup), dedup with selection UI
    InvoiceSync.php              # Order → invoice creation (regular/cash), credit notes, meta tracking
    PaymentSync.php              # Payment registration, foreign currency support
    OrderHooks.php               # WC status hooks, Action Scheduler, bulk actions
    Updates/UpdateService.php    # GitHub auto-updates (PUC library)
  Services/
    Settings.php                 # wp_options reader, merges ihumbak_wca_* options
    Logger.php                   # WC_Logger wrapper, sensitive data masking
    VatMapper.php                # WC tax class → Conta VAT code mapping
  Admin/
    SettingsPage.php             # WooCommerce settings tab "Conta Integration"
    OrderMetaBox.php             # Order meta box: sync actions, manual invoice number, customer selection
assets/css/admin.css
assets/js/admin.js
uninstall.php                    # Cleanup on plugin removal
```

Key patterns:
- `Plugin::register_services()` constructs everything — manual constructor injection, no DI container
- Models use `from_wc_order()` factory + `to_array()` for API serialization
- All API responses return `array|WP_Error`
- Async processing via Action Scheduler (group: `ihumbak-woo-conta-api`), fallback to `do_action()`
- Invoice type split: orders with VAT number → Regular Invoice (`INVOICE`), without → Cash Invoice (`CASH_INVOICE`)
- Customer deduplication: search by email + VAT number, admin UI for selecting among multiple matches
- Invoice model includes `orgReference` and `customerReference` (order number, payment method, transaction ID)

## Order Meta Keys

| Meta Key | Module | Value |
|----------|--------|-------|
| `_ihumbak_wca_invoice_id` | InvoiceSync | Conta invoice ID |
| `_ihumbak_wca_invoice_no` | InvoiceSync | Invoice number (display) |
| `_ihumbak_wca_sync_status` | InvoiceSync | `synced` / `error` |
| `_ihumbak_wca_sync_date` | InvoiceSync | ISO 8601 timestamp |
| `_ihumbak_wca_sync_error` | InvoiceSync | Error message (deleted on success) |
| `_ihumbak_wca_invoice_type` | InvoiceSync | `INVOICE` or `CASH_INVOICE` |
| `_ihumbak_wca_customer_id` | CustomerSync | Conta customer ID |
| `_ihumbak_wca_payment_synced` | PaymentSync | `1` if payment registered |

## Settings

Consolidated option: `ihumbak_wca_settings`. Individual WC options:
- `ihumbak_wca_api_key`, `ihumbak_wca_environment` (sandbox/production), `ihumbak_wca_organization_id`
- `ihumbak_wca_invoice_language` (NO/EN), `ihumbak_wca_trigger_status`, `ihumbak_wca_delivery_method`
- `ihumbak_wca_vat_number_field` (custom field key for customer VAT number)
- `ihumbak_wca_personal_message_template` (invoice personal message with `{placeholders}`)
- `ihumbak_wca_auto_sync` (yes/no — disabled by default), `ihumbak_wca_vat_standard`, `ihumbak_wca_vat_reduced_rate`, `ihumbak_wca_vat_zero_rate`

## Custom Hooks

Key filters (use `/plugin-hooks` skill for full signatures):
- `ihumbak_wca_should_sync_order` — skip specific orders from sync
- `ihumbak_wca_invoice_data` / `ihumbak_wca_customer_data` — modify payload before API call
- `ihumbak_wca_vat_code` — override VAT code resolution
- `ihumbak_wca_api_request_args` — modify HTTP request args for all API calls
- `ihumbak_wca_settings` — filter merged plugin settings

Action Scheduler actions: `ihumbak_wca_sync_invoice`, `ihumbak_wca_sync_payment`, `ihumbak_wca_create_credit_note`

## VAT Code Mapping

**Invoice VAT codes:** `no.vat` (0%), `high` (25%), `medium` (15%), `low` (12%), `zero.rate` (0%), `exempted`, `export`

**Product VAT codes:** `output.high`, `output.medium`, `output.low`, `output.zero.rate`, `output.exempted`, `output.export`, `output.no.vat`, `input.no.vat`

Default mapping: `'' → high`, `reduced-rate → medium`, `zero-rate → zero.rate`. Fallback: `high`.

## API Reference

- Skill: `/conta-api` — loads Conta API endpoint reference and data models
- Skill: `/plugin-hooks` — full hook signatures with parameters and examples
- Skill: `/sync-flow` — step-by-step sync flow with error handling and meta transitions
- Skill: `/bump-version` — bump plugin version (major/minor/patch) in all required files
- Skill: `/commit-tag-release` — full release flow: bump version, commit, tag, push
- Full OpenAPI spec: `docs/conta-external-api.json` (301 schemas)
- Detailed analysis: `docs/conta-api-analysis.md`
- **Sandbox API key:** stored in `docs/sandbox-api-key` (do not commit to public repos)

## Git

- **Remote:** `git@github.com:michalstaniecko/ihumbak-woo-conta-api.git`
- **Branches:** `main` (production), `develop` (active development)
- Work on `develop`, merge to `main` for releases
- Commit messages in English, concise, prefixed with type: `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`
- GitHub Actions: `release.yml` — builds ZIP and creates GitHub release on version tags (v*)

## Coding Conventions

- Follow WordPress Coding Standards (WPCS) — PSR-4 filenames allowed in `src/`
- Use WordPress HTTP API (`wp_remote_post`, `wp_remote_get`) for API calls
- Prefix all hooks with `ihumbak_wca_`, namespace `Ihumbak\WooConta`
- HPOS-compatible meta access: `$order->get_meta()`, `$order->update_meta_data()`, `$order->save()`
- Sanitize all inputs, escape all outputs
- Log API errors via `WC_Logger` (source: `ihumbak-woo-conta-api`)

## Development Tooling

- `composer lint:php` — PHPCS (WordPress Coding Standards)
- `composer fix:php` — auto-fix with PHPCBF
- `composer analyse` — PHPStan level 6 with WordPress extensions
