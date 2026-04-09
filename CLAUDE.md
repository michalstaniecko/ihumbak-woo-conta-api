# ihumbak-woo-conta-api

WooCommerce plugin integrating orders with Conta.no Norwegian accounting system.

## Purpose

Send WooCommerce orders to Conta.no to create invoices and sales documents. The plugin automates the flow: WooCommerce order → Conta customer → Conta invoice → payment registration.

## Tech Stack

- **PHP 8.0+** — WordPress/WooCommerce plugin
- **WordPress 6.0+** with **WooCommerce 8.0+**
- **Conta.no REST API** — OpenAPI 3.0.3, base URL: `https://api.gateway.conta.no`
- Authentication: API Key via `apiKey` HTTP header

## Key Integration Flow

1. WooCommerce order is placed/updated
2. Find or create customer in Conta (search by email, required: name, type, address)
3. Create invoice with line items (required: `customerId`, lines with description, price, quantity, vatCode)
4. Register payment if order is paid
5. Store Conta invoice ID in WooCommerce order meta

## API Reference

- Skill: `/conta-api` — loads Conta API endpoint reference and data models
- Full OpenAPI spec: `docs/conta-external-api.json` (301 schemas)
- Detailed analysis: `docs/conta-api-analysis.md`
- **Sandbox API key:** stored in `docs/sandbox-api-key` (do not commit to public repos)

## Git

- **Remote:** `git@github.com:michalstaniecko/ihumbak-woo-conta-api.git`
- **Branches:** `main` (production), `develop` (active development)
- Work on `develop`, merge to `main` for releases
- Commit messages in English, concise, prefixed with type: `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`

## Coding Conventions

- Follow WordPress Coding Standards (WPCS)
- Use WordPress HTTP API (`wp_remote_post`, `wp_remote_get`) for API calls
- Prefix all functions, classes, hooks with `ihumbak_wca_` or namespace `Ihumbak\WooConta`
- Use WooCommerce HPOS (High-Performance Order Storage) compatible meta access
- Sanitize all inputs, escape all outputs
- Use `wp_options` for plugin settings (API key, organization ID, VAT mapping)
- Log API errors via `WC_Logger`

## Project Structure (planned)

```
ihumbak-woo-conta-api.php          # Main plugin file
includes/
  class-plugin.php                 # Plugin bootstrap
  class-api-client.php             # Conta API HTTP client
  class-customer-sync.php          # WooCommerce → Conta customer mapping
  class-invoice-sync.php           # WooCommerce order → Conta invoice
  class-payment-sync.php           # Payment registration
  class-settings.php               # WP Admin settings page
  class-logger.php                 # API logging wrapper
admin/
  class-admin-page.php             # Settings UI
  class-order-meta-box.php         # Order edit screen meta box
assets/
  css/admin.css
  js/admin.js
```

## VAT Code Mapping

Invoice line VAT codes (Norwegian system):
- `high` = 25% (standard)
- `medium` = 15% (food)
- `low` = 12% (transport, cinema)
- `no.vat` = 0% (no VAT)
- `exempted` = exempt
- `export` = export (0%)
