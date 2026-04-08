# Conta.no API — Detailed Reference

## Models

### Customer (RouteV1CustomerModel)

**Required:** `name`, `customerType`, `customerAddressLine1`, `customerAddressPostcode`, `customerAddressCity`

```json
{
  "name": "string (2-200 chars)",
  "customerType": "INDIVIDUAL | ORGANIZATION",
  "orgNo": "string (6-50 chars)",
  "phoneNo": "string (5-200 chars)",
  "emailAddress": "valid email",
  "dateOfBirth": "YYYY-MM-DD",
  "parentCompanyNameForNuf": "string",
  "daysUntilPaymentReminder": "integer (max 365)",
  "daysUntilEstimateOverdue": "integer (max 365)",
  "defaultInvoiceDiscount": "number (0-100)",
  "customerAddressLine1": "string (2-200 chars)",
  "customerAddressLine2": "string (2-200 chars)",
  "customerAddressPostcode": "string (3-50 chars)",
  "customerAddressCity": "string (1-200 chars)",
  "customerAddressCountry": "string (2-200 chars)",
  "mailingAddressLine1": "string (2-200 chars)",
  "mailingAddressLine2": "string (2-200 chars)",
  "mailingAddressPostcode": "string (3-50 chars)",
  "mailingAddressCity": "string (1-200 chars)",
  "mailingAddressCountry": "string (2-200 chars)",
  "deliveryAddressLine1": "string (2-200 chars)",
  "deliveryAddressLine2": "string (2-200 chars)",
  "deliveryAddressPostcode": "string (3-50 chars)",
  "deliveryAddressCity": "string (1-200 chars)",
  "deliveryAddressCountry": "string (2-200 chars)",
  "invoiceDeliveryMethod": "EMAIL | MAIL | EHF | EFAKTURA | DO_NOT_DELIVER | NONE",
  "isActive": "boolean",
  "contacts": [
    {
      "name": "string",
      "phoneNo": "string",
      "emailAddress": "valid email",
      "title": "string"
    }
  ],
  "efakturaName": "string",
  "efakturaPhoneNo": "string",
  "efakturaEmailAddress": "valid email",
  "ehfRecipientOrgNo": "string"
}
```

**Response includes additional field:** `id` (integer)

### Invoice (RouteV1InvoiceModel)

**Required:** `customerId`

```json
{
  "customerId": "integer (required)",
  "invoiceLines": [
    {
      "productId": "integer (optional - link to Conta product)",
      "description": "string (1-500 chars) - appears on PDF",
      "price": "number - price ex. VAT per unit, in invoice currency",
      "quantity": "number",
      "discount": "number (0-100) - discount percentage",
      "sumDiscount": "number - discount in currency amount",
      "sumNet": "number - total net sum (without VAT)",
      "vatCode": "no.vat | high | medium | low | zero.rate | exempted | export",
      "lineNo": "integer - order on invoice, starting from 1"
    }
  ],
  "invoiceRecipients": [
    {
      "type": "EMAIL | MAIL | EHF | EFAKTURA | DO_NOT_DELIVER",
      "emailAddress": "string",
      "emailRecipientType": "EMAIL_TO | EMAIL_CC | EMAIL_BCC | NOT_EMAIL",
      "emailSubject": "string",
      "emailContent": "string",
      "includeInvoiceInfo": "boolean",
      "includeInvoiceAttachment": "boolean",
      "includeDefaultAttachment": "boolean",
      "ehfRecipient": "string (org number for EHF)",
      "name": "string",
      "customerId": "integer",
      "mailAddressLine1": "string (2-200 chars)",
      "mailAddressLine2": "string (2-200 chars)",
      "mailAddressPostcode": "string (3-50 chars)",
      "mailAddressCity": "string (1-200 chars)",
      "mailAddressCountryCode": "string (exactly 2 chars)"
    }
  ],
  "invoiceDate": "YYYY-MM-DD",
  "invoiceDueDate": "YYYY-MM-DD",
  "status": "BEING_CREATED",
  "type": "NORMAL | CASH | CREDIT",
  "invoiceLanguage": "NO | EN",
  "invoiceCurrency": "string (default: NOK)",
  "exchangeRate": "number (default: 1)",
  "exchangeRateReferenceDate": "YYYY-MM-DD (required if foreign currency)",
  "personalMessage": "string - appears on PDF",
  "customerReference": "string - appears on PDF",
  "isCreatedInOtherSystem": "boolean (default: false)",
  "kid": "string - KID number for auto payment matching",
  "deliveryDate": "YYYY-MM-DD",
  "deliveryAddress": "string",
  "deliveryPostcode": "string",
  "deliveryCity": "string",
  "deliveryCountry": "string",
  "departmentId": "integer",
  "projectId": "integer",
  "showDiscount": "boolean",
  "ehfOrderReference": "string",
  "ehfAdditionalDocumentReference": "string",
  "ehfContractDocumentReference": "string",
  "orgReference": "string - appears on PDF",
  "invoiceNo": "integer (only when isCreatedInOtherSystem=true)"
}
```

**Response includes additional fields:**
- `id` — invoice ID
- `invoiceNo` — auto-assigned invoice number
- `sumVAT`, `sumNet`, `sumRemaining`, `sumTotal` — calculated amounts
- `followUpDate` — initially set to due date
- `invoiceFileId` — PDF file reference
- `invoicePaymentReminder` — payment reminder info
- `creditedInvoiceNo`, `creditedInvoiceDate`, `creditedInvoiceId` — credit note references
- `bankAccountId`

**Important notes:**
- If `id` is set to an existing invoice draft ID, the draft is deleted and converted to invoice
- `isCreatedInOtherSystem: true` requires `invoiceNo` to be provided
- `invoiceDate` cannot be in the past (validation error: `invoiceDateInPastException`)
- Due date cannot be before invoice date (`dueDateBeforeInvoiceDateException`)

### Product (RouteV1ProductModel)

**Required:** `name`, `vatCode`, `bookkeepingAccountNo`

```json
{
  "name": "string (2-500 chars)",
  "bookkeepingAccountNo": "string (4 chars, e.g. '3000' for sales)",
  "vatCode": "input.no.vat | output.high | output.medium | output.low | output.zero.rate | output.exempted | output.export",
  "price": "number",
  "productNo": "string (max 50 chars)",
  "isActive": "boolean"
}
```

**Response includes additional fields:** `id`, `productImportId`, `displayIsIncludingVat`, `createdAt`

### Payment (RouteV1CreateSinglePaymentInputModel)

```json
{
  "date": "YYYY-MM-DD - when payment arrived in bank",
  "bankAccountId": "integer",
  "description": "string - used for accounting transaction",
  "amount": "number",
  "departmentId": "integer (optional)",
  "projectId": "integer (optional)",
  "returnPaymentDate": "YYYY-MM-DD (optional - if payment was returned)"
}
```

### Credit Note (RouteV1CreditFullInvoiceInputModel)

```json
{
  "removePaymentsIfExist": "boolean",
  "creditNoteDate": "YYYY-MM-DD",
  "invoiceRecipients": "array (same as invoice recipients)",
  "invoiceFileId": "integer (optional)",
  "invoiceNo": "integer (optional)",
  "isCreatedInOtherSystem": "boolean",
  "customerReference": "string",
  "ehfAdditionalDocumentReference": "string",
  "ehfContractDocumentReference": "string",
  "ehfOrderReference": "string"
}
```

## Enumerations

### Invoice VAT Codes (for invoice lines)

| Code | Description |
|------|-------------|
| `no.vat` | No VAT |
| `high` | High rate (25%) |
| `medium` | Medium rate (15%) |
| `low` | Low rate (12%) |
| `zero.rate` | Zero rate |
| `exempted` | Exempted |
| `export` | Export |

### Product VAT Codes (for products)

| Code | Description |
|------|-------------|
| `output.high` | Output high (25%) |
| `output.medium` | Output medium (15%) |
| `output.low` | Output low (12%) |
| `output.zero.rate` | Output zero rate |
| `output.exempted` | Output exempted |
| `output.export` | Output export |
| `output.no.vat` | Output no VAT |
| `input.no.vat` | Input no VAT |

### Invoice Statuses

| Status | Description |
|--------|-------------|
| `BEING_CREATED` | Being created |
| `INVOICE_CREATED` | Created / sent |
| `PAYMENT_REMINDER_CREATED` | Payment reminder sent |
| `CLOSED_BY_CREDIT_NOTE` | Closed by credit note |
| `CLOSED_BY_PAYMENT` | Fully paid |
| `CLOSED_FROM_REGNSKAP` | Closed from accounting |
| `WRITTEN_OFF_AS_LOSS` | Written off as loss |
| `SOLD_TO_EASYBANK` | Sold to EasyBank |
| `CLOSED_BY_EASYBANK` | Closed by EasyBank |
| `SENT_TO_KRAVIA` | Sent to debt collection |
| `BEING_SENT_TO_KRAVIA` | Being sent to debt collection |

### Invoice Types

| Type | Description |
|------|-------------|
| `NORMAL` | Standard invoice |
| `CASH` | Cash invoice |
| `CREDIT` | Credit note |

### Customer Types

| Type | Description |
|------|-------------|
| `INDIVIDUAL` | Individual person |
| `ORGANIZATION` | Company/organization |

### Delivery Methods

| Method | Description |
|--------|-------------|
| `EMAIL` | Send by email |
| `MAIL` | Send by postal mail |
| `EHF` | Electronic Handling Format (B2B Norway) |
| `EFAKTURA` | eFaktura (B2C Norway) |
| `INVOICE_SALE` | Invoice sale |
| `DO_NOT_DELIVER` | Do not deliver |
| `NONE` | None |

### Languages

| Code | Language |
|------|----------|
| `NO` | Norwegian |
| `EN` | English |

### Invoice Repeat Frequencies

`WEEKLY`, `BIWEEKLY`, `MONTHLY`, `BIMONTHLY`, `QUARTERLY`, `HALF_YEARLY`, `ANNUALLY`

## Pagination & Search

All list endpoints support:

| Parameter | Description |
|-----------|-------------|
| `q` | Full-text search (partial word matching) |
| `hits` | Items per page |
| `page` | Page number (0-based) |
| `sort` | Field name to sort by |

Example: `GET /invoice/organizations/11/customers?q=Lisa&hits=10&page=0&sort=name`

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad request |
| 401 | Unauthorized (invalid/missing API key) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Not found |
| 409 | Conflict |
| 422 | Unprocessable entity (validation error with details) |
| 500 | Internal server error |

## Common Validation Errors (422)

- `customerNotFoundException` — customer ID not found
- `invoiceSumTotalCanNotBeNegativeException` — total cannot be negative
- `invoiceDateInPastException` — invoice date is in the past
- `dueDateBeforeInvoiceDateException` — due date before invoice date
- `invoiceCanNotBeCreditedException` — invoice cannot be credited
- `invoiceHasPaymentsException` — invoice has payments (cannot modify)
- `wrongVatCodeException` — invalid VAT code
- `customerDoesNotHaveAddressException` — customer address missing
- `subscriptionInvoiceLimitException` — subscription limit reached
- `nextInvoiceNoNotSetException` — invoice numbering not configured

## WooCommerce Integration Flow

1. **Get organization ID:** `GET /invoice/organizations` → extract `id`
2. **Find/create customer:** Search `GET .../customers?q={email}`, if not found → `POST .../customers`
3. **Create invoice:** `POST .../invoices` with `customerId`, `invoiceLines`, dates
4. **Register payment (if paid):** `POST .../invoices/{id}/payments`
5. **Handle refunds:** `POST .../invoices/{id}/credit-note`

### Mapping WooCommerce → Conta Customer

| WooCommerce | Conta Field |
|-------------|-------------|
| `billing_first_name` + `billing_last_name` | `name` |
| `billing_company` (if set) | `name` (use ORGANIZATION type) |
| `billing_email` | `emailAddress` |
| `billing_phone` | `phoneNo` |
| `billing_address_1` | `customerAddressLine1` |
| `billing_address_2` | `customerAddressLine2` |
| `billing_postcode` | `customerAddressPostcode` |
| `billing_city` | `customerAddressCity` |
| `billing_country` | `customerAddressCountry` |

### Mapping WooCommerce Order → Conta Invoice

| WooCommerce | Conta Field |
|-------------|-------------|
| Order items | `invoiceLines[].description`, `.price`, `.quantity` |
| Item tax class | `invoiceLines[].vatCode` (needs mapping) |
| Order date | `invoiceDate` |
| Payment due date | `invoiceDueDate` |
| Order currency | `invoiceCurrency` |
| Shipping address | `deliveryAddress`, `deliveryPostcode`, `deliveryCity`, `deliveryCountry` |
| Order number | `customerReference` or `ehfOrderReference` |
