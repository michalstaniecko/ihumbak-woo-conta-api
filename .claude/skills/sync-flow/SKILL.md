---
name: sync-flow
description: Step-by-step sync flow from WooCommerce order to Conta.no invoice/payment, including error handling, meta key transitions, and credit note flow.
user-invocable: true
disable-model-invocation: false
---

# Sync Flow Reference

## Entry Points

| Trigger | Handler | What Happens |
|---------|---------|-------------|
| Order status → trigger status | `OrderHooks::on_invoice_trigger()` | Schedules invoice sync |
| Order status → completed | `OrderHooks::on_order_completed()` | Schedules invoice (if needed) + payment sync |
| Order status → refunded | `OrderHooks::on_order_refunded()` | Schedules credit note (if invoice exists) |
| Bulk action "Sync to Conta" | `OrderHooks::handle_bulk_action()` | Synchronous invoice + payment sync |
| Meta box "Sync" button | `OrderMetaBox::ajax_sync_order()` | Synchronous invoice sync |
| Meta box "Sync Payment" button | `OrderMetaBox::ajax_sync_payment()` | Synchronous payment sync |

All automatic triggers check `ihumbak_wca_should_sync_order` filter and `auto_sync` setting.

## Invoice Sync Flow

```
InvoiceSync::sync_order($order) → array|WP_Error

1. ALREADY SYNCED?
   ├─ Check meta '_ihumbak_wca_invoice_id' > 0
   └─ If yes: return cached invoice via API get()

2. SYNC CUSTOMER
   ├─ CustomerSync::sync_customer($order)
   │  ├─ Check order meta '_ihumbak_wca_customer_id'
   │  │  └─ If exists: verify via API get(), return ID or clear stale meta
   │  ├─ Check in-memory cache by email
   │  ├─ Search by email: customers->search(email)
   │  │  └─ Exact match (case-insensitive)
   │  └─ If not found: create via customers->create()
   │     └─ Customer::from_wc_order() builds payload
   │     └─ Filter: 'ihumbak_wca_customer_data'
   ├─ On success: store '_ihumbak_wca_customer_id', cache email→ID
   └─ On error: store_sync_error(), return WP_Error

3. BUILD INVOICE
   ├─ Invoice::from_wc_order(order, customer_id, vat_mapper, settings)
   │  ├─ Product items → InvoiceLine::from_wc_item()
   │  ├─ Shipping → InvoiceLine::from_shipping()
   │  └─ Fees → InvoiceLine::from_fee()
   ├─ VAT mapping per line via VatMapper::get_conta_vat_code()
   │  └─ Filter: 'ihumbak_wca_vat_code' per tax class
   └─ Filter: 'ihumbak_wca_invoice_data'

4. CREATE IN CONTA
   ├─ invoices->create($data) → POST /invoices
   ├─ On success: store meta (invoice_id, invoice_no, sync_status=synced, sync_date)
   │  └─ Delete '_ihumbak_wca_sync_error'
   └─ On error: store_sync_error(sync_status=error, sync_error=message)
```

## Payment Sync Flow

```
PaymentSync::sync_payment($order) → array|WP_Error

1. ALREADY SYNCED?
   └─ Check meta '_ihumbak_wca_payment_synced' == '1'
      └─ If yes: return WP_Error('already_synced')

2. GET INVOICE ID
   └─ Read meta '_ihumbak_wca_invoice_id'
      └─ If missing: return WP_Error('no_invoice')

3. VERIFY INVOICE STATUS
   ├─ invoices->get(invoice_id)
   └─ If status == 'CLOSED_BY_PAYMENT':
      ├─ Already paid in Conta, mark as synced
      └─ Return early (success)

4. REGISTER PAYMENT
   ├─ Payment::from_wc_order($order) builds payload
   │  ├─ date: order date_paid or today
   │  ├─ amount: order total
   │  └─ description: "WooCommerce Order #N (payment_method)"
   ├─ Currency check:
   │  ├─ NOK → payments->create(invoice_id, data)
   │  └─ Other → payments->create_foreign_currency(invoice_id, data)
   └─ On success: set '_ihumbak_wca_payment_synced' = '1'
```

## Credit Note Flow

```
InvoiceSync::create_credit_note($order) → array|WP_Error

1. Check is_synced() — must have existing invoice
2. Get invoice_id from meta
3. POST /invoices/{id}/credit-note
   └─ Body: { creditNoteDate, removePaymentsIfExist: true }
```

## Meta Key State Transitions

| State | sync_status | sync_error | invoice_id | payment_synced |
|-------|-------------|------------|------------|---------------|
| Before sync | (absent) | (absent) | (absent) | (absent) |
| Customer error | `error` | message | (absent) | (absent) |
| Invoice API error | `error` | message | (absent) | (absent) |
| Invoice synced | `synced` | (deleted) | ID | (absent) |
| Payment synced | `synced` | (deleted) | ID | `1` |

## Error Codes (WP_Error)

| Code | Source | Cause |
|------|--------|-------|
| `missing_email` | CustomerSync | Order has no billing email |
| `invalid_response` | CustomerSync | API didn't return customer ID |
| `already_synced` | PaymentSync | Payment already registered |
| `no_invoice` | PaymentSync | Invoice not synced yet |
| API errors | Client | HTTP errors, validation, auth failures |

## Bulk Action Flow

Bulk "Sync to Conta" runs **synchronously** (not via Action Scheduler):
1. For each order: `InvoiceSync::sync_order()` + `PaymentSync::sync_payment()` (if paid)
2. Payment errors are soft failures (logged, don't increment error count)
3. Results stored in transient `ihumbak_wca_bulk_result_{user_id}` (60s TTL)
4. Displayed as admin notice on redirect
