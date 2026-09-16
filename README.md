# e-SMS — Reseller Portal

A white-label SMS reseller portal built with **Laravel 12** and **PHP 8.2+** that resells SMS over the **Text.lk v3 gateway**. You hold a single master Text.lk gateway account; your customers get their own individual logins, credit balances, sender names, contact groups, and API tokens.

Public site & marketing landing page → Sign-up / Sign-in → Customer Dashboard → Admin Console → Full REST API mirroring the Text.lk contract.

---

## Core Architecture & Business Logic

Text.lk sees only a single master gateway account. All multitenancy and reseller controls are strictly isolated and enforced locally:

1. **Atomic Credit Ledger**: Credits are reserved before the upstream gateway call and refunded automatically if the gateway rejects or drops the request. Concurrent requests are locked via database row-level locking (`CreditLedger::adjust`).
2. **Strict Sender ID Enforcement**: Messages can only be dispatched under approved sender IDs. Unapproved names are rejected before reaching the gateway.
3. **Namespaced Contact Groups**: Contact groups are mapped per tenant. Tenants can manage and import contacts without interfering with each other.
4. **GSM 03.38 & Unicode Segmenting**: Character sets and segments are counted per GSM 03.38 specifications. Latin text allows 160 chars/segment (153 multi-segment); Sinhala, Tamil, and emojis drop to 70 chars/segment (67 multi-segment).
5. **Reconciliation Engine**: The admin overview reconciles credits sold to customers against actual balance held upstream at Text.lk and warns if there is an upstream shortfall.
6. **Automated Status Polling**: In-flight delivery statuses are scheduled and polled periodically via `php artisan sms:sync-statuses`.

---

## Quick Start & Setup

### Requirements
- PHP 8.2 or newer with SQLite / MySQL / PostgreSQL extension
- Composer
- Node.js 18+ & npm (for building front-end assets)

### 1. Installation

```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
```

### 2. Configure Environment (`.env`)

```env
# Upstream Text.lk Gateway
TEXTLK_API_TOKEN=your-real-textlk-api-token
TEXTLK_BASE_URL=https://app.text.lk/api/v3
TEXTLK_TIMEOUT_MS=20000

# Portal Settings
BRAND_NAME="e-SMS"
DEFAULT_RATE=1.10
SIGNUP_BONUS=10
SUPPORT_EMAIL=support@esms.lk
```

### 3. Database Migration & Seeding

Run migrations and seed default portal settings, the admin account, and demo customer:

```bash
php artisan migrate --seed
```

Or seed/promote a custom admin account:
```bash
php artisan app:seed-admin admin@yourcompany.lk "YourSecurePassword" --demo
```

Default credentials seeded:
- **Admin**: `admin@example.lk` / `AdminPass123!` (Console at `/console`)
- **Demo Customer**: `demo@example.lk` / `demo12345` (Dashboard at `/dashboard`, with 2,500 credits and approved sender `SpiceLK`)

### 4. Run the Development Server

```bash
php artisan serve
```
Open **http://localhost:8000** in your browser.

---

## Background Services & Scheduler

To keep delivery receipts updated from the gateway in the background, run the Laravel scheduler:

```bash
php artisan schedule:work
```

Or manually trigger a status sync:
```bash
php artisan sms:sync-statuses
```

On production Linux servers, add this single cron entry:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Tenant-Facing REST API (`/api/v3/*`)

The tenant API mirrors the Text.lk v3 contract. Customers can authenticate using Bearer tokens created in their dashboard under **API Tokens**.

### Endpoints
- `POST /api/v3/sms/send`: Send SMS to one or more numbers
- `POST /api/v3/sms/campaign`: Send campaign to a contact group
- `POST /api/v3/sms/estimate`: Calculate segments and credit cost
- `GET /api/v3/sms/{uid}`: Retrieve single message details & delivery status
- `GET /api/v3/sms`: Paginated list of sent messages (supports date filters)
- `GET /api/v3/balance`: Check credit balance and SMS rate
- `GET /api/v3/me`: Customer profile
- `GET /api/v3/contacts`: List customer contact groups

#### Sample Request: Send SMS
```bash
curl -X POST http://localhost:8000/api/v3/sms/send \
  -H "Authorization: Bearer 1|your-tenant-api-token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "recipient": "0712345678",
    "sender_id": "SpiceLK",
    "message": "Your order #1082 is ready for pickup!"
  }'
```

---

## Running the Automated Test Suite

The test suite covers unit mathematics (GSM-7, Unicode, phone normalisation, ledger atomicity) and feature tests (auth, customer dashboard, admin console, API v3, scheduler):

```bash
php artisan test
```
