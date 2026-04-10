---
name: plugin-hooks
description: Full reference of all custom WordPress hooks (filters and actions) provided by the plugin, with signatures, parameters, and usage examples.
user-invocable: true
disable-model-invocation: false
---

# Plugin Hooks Reference

## Filters

### ihumbak_wca_should_sync_order
**File:** `src/Modules/OrderHooks.php`
```php
apply_filters( 'ihumbak_wca_should_sync_order', true, $order )
```
- `$should_sync` (bool) — default: true
- `$order` (WC_Order) — the WooCommerce order

Skip specific orders from syncing (e.g., by order total, customer, or custom meta).

### ihumbak_wca_invoice_data
**File:** `src/Modules/InvoiceSync.php`
```php
apply_filters( 'ihumbak_wca_invoice_data', $data, $order )
```
- `$data` (array) — invoice payload in Conta API format (customerId, invoiceLines, etc.)
- `$order` (WC_Order) — source order

Modify invoice data before the API call. Fires in `sync_order()` after `Invoice::from_wc_order()`.

### ihumbak_wca_customer_data
**File:** `src/Modules/CustomerSync.php` (used in both `create_from_order()` and `update_from_order()`)
```php
apply_filters( 'ihumbak_wca_customer_data', $data, $order )
```
- `$data` (array) — customer payload (name, customerType, address fields, etc.)
- `$order` (WC_Order) — source order

Modify customer data before creating or updating in Conta.

### ihumbak_wca_vat_code
**File:** `src/Services/VatMapper.php`
```php
apply_filters( 'ihumbak_wca_vat_code', $mapped_code, $wc_tax_class )
```
- `$mapped_code` (string) — resolved Conta VAT code
- `$wc_tax_class` (string) — WooCommerce tax class being mapped

Override VAT code resolution for specific tax classes.

### ihumbak_wca_api_request_args
**File:** `src/API/Client.php`
```php
apply_filters( 'ihumbak_wca_api_request_args', $args, $endpoint )
```
- `$args` (array) — HTTP request arguments (method, headers, timeout, body)
- `$endpoint` (string) — API endpoint path

Modify all HTTP requests to the Conta API. Fires in `Client::request()` for every call.

### ihumbak_wca_settings
**File:** `src/Services/Settings.php`
```php
apply_filters( 'ihumbak_wca_settings', $settings )
```
- `$settings` (array) — merged settings with defaults

Override any plugin settings programmatically.

### ihumbak_wca_updates_enabled
**File:** `src/Modules/Updates/UpdateService.php`
```php
apply_filters( 'ihumbak_wca_updates_enabled', true )
```
- `$enabled` (bool) — default: true

Disable automatic plugin updates from GitHub. Also disabled by `IHUMBAK_WCA_DISABLE_UPDATES` constant.

### ihumbak_wca_update_repository_url
**File:** `src/Modules/Updates/UpdateService.php`
```php
apply_filters( 'ihumbak_wca_update_repository_url', self::REPOSITORY_URL )
```
- `$url` (string) — GitHub repository URL

Override the repository used for plugin updates.

### ihumbak_wca_update_info
**File:** `src/Modules/Updates/UpdateService.php`
```php
apply_filters( 'ihumbak_wca_update_info', $info )
```
- `$info` (object) — update info from plugin-update-checker

Modify update information before it's applied.

### ihumbak_wca_github_access_token
**File:** `src/Modules/Updates/UpdateService.php`
```php
apply_filters( 'ihumbak_wca_github_access_token', $token )
```
- `$token` (string) — GitHub access token (default from `IHUMBAK_WCA_GITHUB_ACCESS_TOKEN` constant)

Provide GitHub token for private repository updates.

### ihumbak_wca_uninstall_remove_order_meta
**File:** `uninstall.php`
```php
apply_filters( 'ihumbak_wca_uninstall_remove_order_meta', false )
```
- `$remove_meta` (bool) — default: false

When true, deletes all `_ihumbak_wca_*` order meta on plugin uninstall.

## Actions (Action Scheduler)

All actions accept `$order_id` (int). Registered in `OrderHooks::register()`, handled by `OrderHooks`.

### ihumbak_wca_sync_invoice
Triggered on order status change to trigger status or 'completed' (if not yet synced). Handler: `handle_sync_invoice()` → calls `InvoiceSync::sync_order()`.

### ihumbak_wca_sync_payment
Triggered on order status change to 'completed'. Handler: `handle_sync_payment()` → calls `PaymentSync::sync_payment()`.

### ihumbak_wca_create_credit_note
Triggered on order status change to 'refunded' (only if invoice exists). Handler: `handle_create_credit_note()` → calls `InvoiceSync::create_credit_note()`.

## WooCommerce Hooks Used

| Hook | Handler | Purpose |
|------|---------|---------|
| `woocommerce_order_status_{trigger}` | `OrderHooks::on_invoice_trigger()` | Create invoice |
| `woocommerce_order_status_completed` | `OrderHooks::on_order_completed()` | Sync payment |
| `woocommerce_order_status_refunded` | `OrderHooks::on_order_refunded()` | Create credit note |
| `bulk_actions-woocommerce_page_wc-orders` | Filter | Add "Sync to Conta" bulk action (HPOS) |
| `bulk_actions-edit-shop_order` | Filter | Add "Sync to Conta" bulk action (legacy) |

## AJAX Endpoints

| Action | Nonce | Handler |
|--------|-------|---------|
| `ihumbak_wca_test_connection` | `ihumbak_wca_test_connection` | `SettingsPage::ajax_test_connection()` |
| `ihumbak_wca_sync_order` | `ihumbak_wca_sync_order` | `OrderMetaBox::ajax_sync_order()` |
| `ihumbak_wca_sync_payment` | `ihumbak_wca_sync_payment` | `OrderMetaBox::ajax_sync_payment()` |
