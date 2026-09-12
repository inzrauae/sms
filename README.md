# SMS reseller portal

A white-label portal that resells SMS over the Text.lk v3 gateway. You hold one
Text.lk account; your customers get their own logins, credit balances, sender
names, contact groups and API tokens.

Public site → sign-up → dashboard → admin console, plus a REST API your
customers can call from their own applications.

## Why the middle layer exists

Text.lk sees a single account. Everything that makes reselling work — who owns
which credits, who may send from which name, whose contact group is whose — is
enforced in this application before a request ever reaches the gateway.

- **Credits are reserved before the upstream call and refunded if it fails**, so
  a network timeout never bills a customer for nothing sent (`src/dispatch.js`).
- **Sender names are checked against an approval table on every send.** Without
  it, customer A could send as customer B's brand.
- **Contact groups are namespaced per tenant.** Upstream group IDs are mapped to
  a local owner, and any request for a group you do not own returns 404.
- **Segments are counted to GSM 03.38, not `string.length`** (`src/sms.js`).
  Sinhala and Tamil drop to 70 characters per segment, so a message that looks
  short can cost three credits. Miscount this and you eat the difference on
  every send.

## Setup

Requires Node 18 or newer.

```bash
npm install
cp .env.example .env
```

Fill in `.env`:

```bash
# a random secret for signing session cookies
node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"
```

Set `TEXTLK_API_TOKEN` to your Text.lk token and `SESSION_SECRET` to the value
you just generated. Then create your admin login:

```bash
node scripts/seed.js admin@yourcompany.lk "your strong passphrase"
npm start
```

Open http://localhost:3000.

Add `--demo` to the seed command for a sample customer
(`demo@example.lk` / `demo12345`, 2,500 credits, one approved sender name) if
you want to click through the dashboard before connecting a real token.

## Layout

```
server.js              routes, page guards, delivery-status poller
src/
  db.js                SQLite schema; adjustCredits() is the ledger primitive
  sms.js               GSM-7/UCS-2 segments, number normalising, sender rules
  textlk.js            Text.lk v3 client
  dispatch.js          reserve credits, send, refund on failure, sync statuses
  auth.js              sessions, password hashing, API token issue and verify
  routes/              auth, app (dashboard), admin, api (tenant-facing)
public/                landing, sign-in, dashboard, admin console, API docs
scripts/seed.js        first-run admin account
```

Data lives in `data/portal.db`. Back that file up — it holds every balance.

## Running it

**Customers** sign up, get welcome credits, request a sender name, and wait for
you to approve it. They can then send from the dashboard or generate an API
token and send from their own code.

**You** work in `/console`: approve sender names, add credits once payment
clears, set each customer's per-SMS rate, suspend accounts, and watch all
traffic. The overview reconciles credits you have sold against credits actually
held at the gateway and warns you when you are short.

## The tenant-facing API

Deliberately mirrors the Text.lk contract, so a customer already integrated with
Text.lk migrates by changing the base URL and token:

```bash
curl -X POST https://your-domain.lk/api/v3/sms/send \
  -H 'Authorization: Bearer 12|their-token' \
  -H 'Content-Type: application/json' \
  -d '{"recipient":"0712345678","sender_id":"TheirBrand","message":"Hello"}'
```

`POST /api/v3/sms/send`, `POST /api/v3/sms/campaign`,
`POST /api/v3/sms/estimate`, `GET /api/v3/sms`, `GET /api/v3/sms/{uid}`,
`GET /api/v3/balance`, `GET /api/v3/me`, `GET /api/v3/contacts`.
Full reference at `/docs`.

Local numbers are normalised (`0712345678` → `94712345678`) and duplicates in a
recipient list are dropped before billing.

## Before you take real money

This runs correctly but is not a finished commercial product. In rough order of
importance:

1. **Payments.** `POST /app/topup-request` only records that a customer wants
   credits; an admin adds them by hand. Wire in PayHere, Stripe or your bank's
   IPG and credit the account from the payment webhook.
2. **Delivery receipts.** The v3 docs publish no delivery webhook, so statuses
   are polled every 60 seconds (`SYNC_INTERVAL_MS`). If Text.lk offers a DLR
   callback on your plan, switch to it — polling does not scale past a few
   thousand in-flight messages.
3. **Email.** Nothing is sent: no verification, no password reset, no
   notification when a sender name is approved. Add an SMTP provider.
4. **Large campaigns.** Sends are synchronous. Past a few hundred recipients,
   move dispatch to a job queue so a slow gateway does not hold the request
   open.
5. **Postgres.** SQLite is fine for a single box and a few million rows. Move to
   Postgres before you run more than one instance — a WAL-mode SQLite file
   cannot be shared across servers.
6. **Rate limiting per tenant.** The API limits 120 requests a minute per token;
   there is no daily spend cap, so a compromised customer token can drain that
   customer's whole balance.

## Deploying

Put it behind nginx or Caddy with TLS and set `NODE_ENV=production`, which makes
the session cookie secure-only. Run under systemd or PM2 so it restarts on
failure. The token in `.env` can spend real money — keep the file at `600` and
out of version control.
