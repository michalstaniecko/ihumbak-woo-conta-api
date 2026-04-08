---
name: conta-api
description: Conta.no REST API reference for building WooCommerce integration. Use when creating API requests, mapping WooCommerce data to Conta models, working with invoices, customers, products, or payments in the Conta API.
user-invocable: true
disable-model-invocation: false
---

# Conta.no REST API Reference

## Environment

| Environment | Base URL |
|-------------|----------|
| Production | `https://api.gateway.conta.no` |
| Sandbox | `https://api.gateway.conta-sandbox.no` |

## Authentication

Send API key as HTTP header with every request:

```
apiKey: <your-api-key>
```

Keys are created in Conta app: Settings > User account > Manage API keys.

## Endpoints

### Organizations

| Method | Path | Description |
|--------|------|-------------|
| GET | `/invoice/organizations` | List accessible organizations (returns `opContextOrgId`) |

### Customers

| Method | Path | Description |
|--------|------|-------------|
| GET | `/invoice/organizations/{orgId}/customers` | List/search customers (`?q=`, `?hits=`, `?page=`, `?sort=`) |
| POST | `/invoice/organizations/{orgId}/customers` | Create customer |
| GET | `/invoice/organizations/{orgId}/customers/{id}` | Get customer |
| PUT | `/invoice/organizations/{orgId}/customers/{id}` | Update customer |

**Required fields:** `name`, `customerType`, `customerAddressLine1`, `customerAddressPostcode`, `customerAddressCity`

### Products

| Method | Path | Description |
|--------|------|-------------|
| GET | `/invoice/organizations/{orgId}/products` | List/search products |
| POST | `/invoice/organizations/{orgId}/products` | Create product |
| GET | `/invoice/organizations/{orgId}/products/{id}` | Get product |
| PUT | `/invoice/organizations/{orgId}/products/{id}` | Update product |

**Required fields:** `name`, `vatCode`, `bookkeepingAccountNo`

### Invoices

| Method | Path | Description |
|--------|------|-------------|
| GET | `/invoice/organizations/{orgId}/invoices` | List/search invoices |
| POST | `/invoice/organizations/{orgId}/invoices` | **Create invoice** |
| GET | `/invoice/organizations/{orgId}/invoices/{id}` | Get invoice |
| GET | `/invoice/organizations/{orgId}/invoices/unpaid` | Get unpaid invoices |
| POST | `/invoice/organizations/{orgId}/invoices/{id}/credit-note` | Create credit note |
| POST | `/invoice/organizations/{orgId}/invoices/files` | Upload attachment |

**Required fields:** `customerId`

### Payments

| Method | Path | Description |
|--------|------|-------------|
| GET | `/invoice/organizations/{orgId}/invoices/{id}/payments` | Get invoice payments |
| POST | `/invoice/organizations/{orgId}/invoices/{invoiceId}/payments` | Create payment |
| POST | `/invoice/organizations/{orgId}/invoices/{invoiceId}/payments/foreign-currency` | Create foreign currency payment |
| DELETE | `/invoice/organizations/{orgId}/payments/{id}` | Delete payment |
| GET | `/invoice/organizations/{orgId}/invoice-payments` | Search payments across invoices |

### Invoice Drafts

| Method | Path | Description |
|--------|------|-------------|
| GET | `/invoice/organizations/{orgId}/invoice-drafts` | List drafts |
| POST | `/invoice/organizations/{orgId}/invoice-drafts` | Create draft |
| GET | `/invoice/organizations/{orgId}/invoice-drafts/{id}` | Get draft |

### Other

| Method | Path | Description |
|--------|------|-------------|
| GET | `/invoice/organizations/{orgId}/bank-accounts` | Bank accounts |
| GET | `/invoice/organizations/{orgId}/departments` | Departments |
| GET | `/invoice/organizations/{orgId}/projects` | Projects |
| GET | `/invoice/organizations/{orgId}/subscription-plan` | Subscription info |
| GET | `/invoice/conta-ehf/recipients/{recipientId}` | Check EHF recipient |
| GET | `/invoice/organizations/{orgId}/invoices/allowed-delivery-methods` | Allowed delivery methods |

For detailed field descriptions, validation rules, and complete schema definitions, see [reference.md](reference.md).

For full OpenAPI 3.0.3 specification (301 schemas), see `docs/conta-external-api.json`.
