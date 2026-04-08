# Conta.no REST API - Analiza dokumentacji

## Środowisko

- **Produkcja:** `https://api.gateway.conta.no`
- **Sandbox:** `https://api.gateway.conta-sandbox.no`
- **Swagger UI:** `https://docs.gateway.conta.no`

## Autentykacja

- API Key wysyłany jako header HTTP: `apiKey`
- Klucz tworzony w Conta: Settings → User account → Manage API keys
- Dostęp odpowiada uprawnieniom użytkownika tworzącego klucz

## Kluczowe endpointy dla integracji WooCommerce → Conta

### 1. Organizations
- `GET /organizations` — lista dostępnych organizacji (potrzebne do uzyskania `opContextOrgId`)

### 2. Customers (Klienci)
- `GET /invoice/organizations/{orgId}/customers` — lista klientów (z wyszukiwaniem: `?q=`)
- `POST /invoice/organizations/{orgId}/customers` — tworzenie klienta
- `GET /invoice/organizations/{orgId}/customers/{id}` — odczyt klienta
- `PUT /invoice/organizations/{orgId}/customers/{id}` — aktualizacja klienta

#### Customer Model (request body):
```json
{
  "name": "string",
  "customerType": "INDIVIDUAL | ORGANIZATION",
  "orgNo": "string",
  "phoneNo": "string",
  "emailAddress": "user@example.com",
  "dateOfBirth": "2026-04-08",
  "daysUntilPaymentReminder": 365,
  "daysUntilEstimateOverdue": 365,
  "defaultInvoiceDiscount": 100,
  "customerAddressLine1": "string",
  "customerAddressLine2": "string",
  "customerAddressPostcode": "string",
  "customerAddressCity": "string",
  "customerAddressCountry": "string",
  "mailingAddressLine1": "string",
  "mailingAddressLine2": "string",
  "mailingAddressPostcode": "string",
  "mailingAddressCity": "string",
  "mailingAddressCountry": "string",
  "deliveryAddressLine1": "string",
  "deliveryAddressLine2": "string",
  "deliveryAddressPostcode": "string",
  "deliveryAddressCity": "string",
  "deliveryAddressCountry": "string",
  "invoiceDeliveryMethod": "EMAIL",
  "isActive": true,
  "contacts": [
    {
      "name": "string",
      "phoneNo": "string",
      "emailAddress": "user@example.com",
      "title": "string"
    }
  ]
}
```

### 3. Products (Produkty)
- `GET /invoice/organizations/{orgId}/products` — lista produktów
- `POST /invoice/organizations/{orgId}/products` — tworzenie produktu
- `GET /invoice/organizations/{orgId}/products/{id}` — odczyt produktu
- `PUT /invoice/organizations/{orgId}/products/{id}` — aktualizacja produktu

#### Product Model (request body):
```json
{
  "name": "string",
  "bookkeepingAccountNo": "string (4 chars)",
  "vatCode": "input.no.vat",
  "price": 0,
  "productNo": "string",
  "isActive": true
}
```

#### Product Response (zawiera dodatkowe pola):
```json
{
  "id": 0,
  "productImportId": 0,
  "name": "string",
  "bookkeepingAccountNo": "string",
  "displayIsIncludingVat": true,
  "createdAt": "2026-04-08T20:26:21.802Z",
  "vatCode": "input.no.vat",
  "price": 0,
  "productNo": "string",
  "isActive": true
}
```

### 4. Invoices (Faktury) — KLUCZOWY ENDPOINT
- `GET /invoice/organizations/{orgId}/invoices` — lista faktur
- `POST /invoice/organizations/{orgId}/invoices` — **tworzenie faktury**
- `GET /invoice/organizations/{orgId}/invoices/{id}` — odczyt faktury
- `GET /invoice/organizations/{orgId}/invoices/unpaid` — nieopłacone faktury
- `GET /invoice/organizations/{orgId}/invoices/allowed-delivery-methods` — dostępne metody dostarczenia
- `POST /invoice/organizations/{orgId}/invoices/{id}/credit-note` — nota kredytowa
- `POST /invoice/organizations/{orgId}/invoices/{id}/payments` — rejestracja płatności
- `POST /invoice/organizations/{orgId}/invoices/{invoiceId}/payments/foreign-currency` — płatność w obcej walucie

#### Invoice Model (request body):
```json
{
  "invoiceLines": [
    {
      "productId": 0,
      "description": "string",
      "price": 0.00,
      "quantity": 0.00,
      "discount": 0,
      "vatCode": "no.vat",
      "lineNo": 0
    }
  ],
  "invoiceRecipients": [
    {
      "type": "EMAIL | EHF",
      "emailAddress": "user@example.com",
      "emailRecipientType": "NOT_EMAIL | EMAIL_TO",
      "emailSubject": "string",
      "emailContent": "string",
      "includeInvoiceInfo": true,
      "includeInvoiceAttachment": true,
      "includeDefaultAttachment": true,
      "name": "string",
      "customerId": 0,
      "mailAddressLine1": "string",
      "mailAddressLine2": "string",
      "mailAddressPostcode": "string",
      "mailAddressCity": "string",
      "mailAddressCountryCode": "string"
    }
  ],
  "invoiceDate": "2026-04-08",
  "invoiceDueDate": "2026-04-08",
  "status": "BEING_CREATED",
  "type": "NORMAL",
  "customerId": 0,
  "invoiceLanguage": "NO",
  "invoiceCurrency": "NOK",
  "exchangeRate": 1,
  "personalMessage": "string",
  "customerReference": "string",
  "isCreatedInOtherSystem": false,
  "kid": "string",
  "deliveryDate": "2026-04-08",
  "deliveryAddress": "string",
  "deliveryPostcode": "string",
  "deliveryCity": "string",
  "deliveryCountry": "string",
  "departmentId": 0,
  "projectId": 0,
  "showDiscount": true,
  "ehfOrderReference": "string",
  "orgReference": "string"
}
```

#### Invoice Response (zawiera dodatkowe pola):
- `invoiceNo` — numer faktury
- `sumVAT`, `sumNet`, `sumRemaining`, `sumTotal` — kwoty
- `followUpDate` — data follow-up
- `invoiceFileId` — plik PDF faktury
- `creditedInvoiceNo`, `creditedInvoiceDate`, `creditedInvoiceId`
- `bankAccountId`
- `invoicePaymentReminder` — przypomnienie o płatności

### 5. Invoice Drafts (Szkice faktur)
- `GET /invoice/organizations/{orgId}/invoice-drafts` — lista szkiców
- `POST /invoice/organizations/{orgId}/invoice-drafts` — tworzenie szkicu
- `GET /invoice/organizations/{orgId}/invoice-drafts/{id}` — odczyt szkicu

### 6. Payments (Płatności)
- `GET /invoice/organizations/{orgId}/invoices/{id}/payments` — płatności faktury
- `POST /invoice/organizations/{orgId}/invoices/{id}/payments` — rejestracja płatności
- `DELETE /invoice/organizations/{orgId}/payments/{id}` — usunięcie płatności
- `GET /invoice/organizations/{orgId}/invoice-payments` — wyszukiwanie płatności

### 7. Inne przydatne endpointy
- `GET /invoice/organizations/{orgId}/bank-accounts` — konta bankowe
- `GET /invoice/organizations/{orgId}/departments` — działy
- `GET /invoice/organizations/{orgId}/projects` — projekty
- `GET /invoice/organizations/{orgId}/subscription-plan` — plan subskrypcji
- `POST /invoice/organizations/{orgId}/invoices/files` — upload załącznika do faktury
- `GET /invoice/conta-ehf/recipients/{recipientId}` — sprawdzenie odbiorcy EHF

### 8. Accounting (Księgowość)
- `GET /accounting/organizations/{orgId}/bookkeeping-accounts` — konta księgowe
- `GET /accounting/organizations/{orgId}/income-and-expenses/{year}` — przychody i wydatki
- Raporty: balance, profit-loss, accounts-receivable, supplier-ledger

## Wymagane pola (Required) — ze Swagger JSON

### Invoice (POST) — required: `customerId`
Jedyne wymagane pole to `customerId`. Reszta jest opcjonalna, ale w praktyce potrzebne będą też `invoiceLines`, `invoiceDate`, `invoiceDueDate`.

### Customer (POST) — required: `name`, `customerType`, `customerAddressLine1`, `customerAddressPostcode`, `customerAddressCity`

### Product (POST) — required: `name`, `vatCode`, `bookkeepingAccountNo`

## VAT Codes

### Na fakturze (invoice lines) — enumRouteV1VatCodeTypeConst:
- `no.vat` — bez VAT
- `high` — wysoka stawka (25%)
- `medium` — średnia stawka (15%)
- `low` — niska stawka (12%)
- `zero.rate` — stawka 0%
- `exempted` — zwolniony
- `export` — eksport

### Na produkcie — pełna lista vatCode:
- `output.high` (25%), `output.medium` (15%), `output.low` (12%)
- `output.zero.rate`, `output.exempted`, `output.export`
- `output.no.vat`, `input.no.vat`
- oraz wiele kodów input/import/cost

## Statusy faktury (enumInvoiceStatusEnum)
- `BEING_CREATED` — tworzona
- `INVOICE_CREATED` — utworzona
- `PAYMENT_REMINDER_CREATED` — przypomnienie o płatności
- `CLOSED_BY_CREDIT_NOTE` — zamknięta notą kredytową
- `CLOSED_BY_PAYMENT` — zamknięta płatnością
- `CLOSED_FROM_REGNSKAP` — zamknięta z księgowości
- `WRITTEN_OFF_AS_LOSS` — odpisana jako strata
- `SOLD_TO_EASYBANK`, `CLOSED_BY_EASYBANK`
- `SENT_TO_KRAVIA`, `BEING_SENT_TO_KRAVIA`

## Typy faktur (enumInvoiceTypeEnum)
- `NORMAL` — zwykła faktura
- `CASH` — faktura gotówkowa
- `CREDIT` — nota kredytowa

## Typy klientów (enumCustomerTypeEnum)
- `INDIVIDUAL` — osoba fizyczna
- `ORGANIZATION` — firma

## Metody dostarczenia (enumDeliveryMethodTypeEnum)
- `EMAIL`, `MAIL`, `EHF`, `EFAKTURA`, `INVOICE_SALE`, `DO_NOT_DELIVER`, `UNKNOWN`, `NONE`

## Języki (enumLanguageEnum)
- `NO` — norweski
- `EN` — angielski

## Paginacja i wyszukiwanie
- `q` — pełnotekstowe wyszukiwanie
- `hits` — ilość wyników na stronę
- `page` — numer strony
- `sort` — sortowanie po polu

## Źródło danych: Swagger JSON

Pełna specyfikacja OpenAPI 3.0.3 dostępna w `docs/conta-external-api.json`:
- 301 schematów (modele, enumy, wyjątki)
- Wszystkie endpointy z pełnymi opisami pól, walidacjami (min/max length), typami danych
- Opisy wyjątków (np. `invoiceSumTotalCanNotBeNegativeException`, `customerNotFoundException`)
- **Znacznie lepsza niż strona hjelp.conta.no/api/** — zawiera required fields, walidacje, enumy

## Ocena: Czy dokumentacja jest wystarczająca?

### ✅ TAK — dokumentacja jest w pełni wystarczająca

**Swagger JSON (`conta-external-api.json`) jest kompletną specyfikacją** zawierającą:
1. Pełny CRUD na klientach z required fields i walidacjami
2. Pełny CRUD na produktach z kodami VAT
3. Tworzenie faktur z liniami zamówienia — jedyne required to `customerId`
4. Rejestracja płatności + płatności w obcych walutach
5. Noty kredytowe — obsługa zwrotów
6. Szkice faktur (invoice drafts) — opcjonalnie do dwuetapowego procesu
7. Wyszukiwanie i paginacja
8. Sandbox do testów
9. Prosta autentykacja (API Key)
10. 301 schematów z walidacjami, enumami i opisami wyjątków

**Flow integracji WooCommerce → Conta:**
1. Zamówienie złożone w WooCommerce
2. Sprawdź/utwórz klienta w Conta (wyszukaj po email, required: name, type, address)
3. Utwórz fakturę z liniami zamówienia (required: customerId + invoiceLines z description, price, quantity, vatCode)
4. Opcjonalnie: zarejestruj płatność jeśli zamówienie jest opłacone
5. Zapisz ID faktury Conta w meta zamówienia WooCommerce

**Potencjalne wyzwania:**
- Kody VAT wymagają mapowania (norweski system: high=25%, medium=15%, low=12%)
- `bookkeepingAccountNo` na produkcie wymaga znajomości norweskiego planu kont (np. 3000 = sprzedaż)
- Brak dokumentacji o rate limiting
- `isCreatedInOtherSystem: true` wymaga podania `invoiceNo` — trzeba ustalić czy chcemy tego flagę
