<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>API reference — {{ config('portal.brand_name') }}</title>
<link rel="stylesheet" href="{{ asset('css/base.css') }}">
<link rel="stylesheet" href="{{ asset('css/site.css') }}">
<style>
  .doc { max-width: 780px; margin: 0 auto; padding: 48px 24px 80px; }
  .doc h2 { margin-top: 46px; padding-top: 22px; border-top: 1px solid var(--line); }
  .doc h3 { margin-top: 30px; }
  .doc p { margin-top: 12px; color: var(--ink-soft); }
  .doc .code { margin-top: 16px; }
  .doc table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 0.92rem; }
  .doc th { text-align: left; font-weight: 500; font-size: 0.82rem; color: var(--muted); padding: 0 12px 8px 0; border-bottom: 1px solid var(--line); }
  .doc td { padding: 10px 12px 10px 0; border-bottom: 1px solid var(--line-soft); vertical-align: top; }
  .doc td:first-child { font-family: var(--mono); font-size: 0.88rem; white-space: nowrap; }
  .doc .req { color: var(--clay); font-size: 0.8rem; }
  .endpoint {
    display: inline-flex; gap: 8px; align-items: center; margin-top: 26px;
    font-family: var(--mono); font-size: 0.9rem;
    padding: 7px 12px; background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-control);
  }
  .endpoint b { color: var(--jade-deep); }
</style>
</head>
<body>

<header class="masthead">
  <div class="shell">
    <a class="wordmark" href="/"><span class="glyph">eS</span><span data-brand>{{ config('portal.brand_name') }}</span></a>
    <nav><a href="/">Home</a><a href="/dashboard" class="btn btn-sm">Dashboard</a></nav>
  </div>
</header>

<main class="doc">
  <h1>API reference</h1>
  <p>
    JSON in, JSON out, over HTTPS. Every request carries an API token you generate
    in the dashboard under API tokens.
  </p>

  <div class="code" style="margin-top:20px">
Authorization: Bearer 12|your-token-here
Content-Type: application/json
Accept: application/json</div>

  <p style="margin-top:16px">
    Successful responses carry <code>"status": "success"</code> and a <code>data</code>
    object. Failures carry <code>"status": "error"</code> and a readable
    <code>message</code>. A <code>402</code> means you ran out of credits.
  </p>

  <h2>Send a message</h2>
  <div class="endpoint"><b>POST</b> /api/v3/sms/send</div>

  <table>
    <thead><tr><th>Parameter</th><th>Description</th></tr></thead>
    <tbody>
      <tr><td>recipient <span class="req">required</span></td><td>One number, or several separated by commas. Local formats such as 0712345678 are accepted and normalised to 94712345678.</td></tr>
      <tr><td>sender_id <span class="req">required</span></td><td>An approved sender name on your account.</td></tr>
      <tr><td>type</td><td><code>plain</code> or <code>unicode</code>. Left out, the encoding is detected from the message.</td></tr>
      <tr><td>message <span class="req">required</span></td><td>The body of the SMS.</td></tr>
      <tr><td>schedule_time</td><td>Send later, in <code>YYYY-MM-DD HH:MM</code>.</td></tr>
    </tbody>
  </table>

  <div class="code">curl -X POST https://esms.lk/api/v3/sms/send \
  -H 'Authorization: Bearer 12|your-token-here' \
  -H 'Content-Type: application/json' \
  -d '{
    "recipient": "94710000000,94770000000",
    "sender_id": "YourBrand",
    "message": "Your order #4821 is out for delivery."
  }'</div>

  <h3>Response</h3>
  <div class="code">{
  "status": "success",
  "data": {
    "uid": "a91f3c7d2e04",
    "uids": ["a91f3c7d2e04", "b72e1d9a5c83"],
    "to": "94710000000,94770000000",
    "sms_count": 1,
    "cost": 2,
    "status": "Queued"
  }
}</div>
  <p>
    <code>sms_count</code> is the segments in one message; <code>cost</code> is the
    total credits taken, which is segments multiplied by recipients.
  </p>

  <h2>Cost a message without sending</h2>
  <div class="endpoint"><b>POST</b> /api/v3/sms/estimate</div>
  <p>
    Same body as a send. Returns the segment count, encoding and credits required.
    Useful before a large batch.
  </p>

  <h2>Send to a contact group</h2>
  <div class="endpoint"><b>POST</b> /api/v3/sms/campaign</div>
  <table>
    <thead><tr><th>Parameter</th><th>Description</th></tr></thead>
    <tbody>
      <tr><td>contact_list_id <span class="req">required</span></td><td>The group ID from <code>GET /api/v3/contacts</code>.</td></tr>
      <tr><td>sender_id <span class="req">required</span></td><td>An approved sender name.</td></tr>
      <tr><td>message <span class="req">required</span></td><td>The body of the SMS.</td></tr>
      <tr><td>schedule_time</td><td>Send later, in <code>YYYY-MM-DD HH:MM</code>.</td></tr>
    </tbody>
  </table>

  <h2>Look up one message</h2>
  <div class="endpoint"><b>GET</b> /api/v3/sms/{uid}</div>
  <div class="code">{
  "status": "success",
  "data": {
    "uid": "a91f3c7d2e04",
    "to": "94710000000",
    "from": "YourBrand",
    "message": "Your order #4821 is out for delivery.",
    "sms_type": "plain",
    "status": "Delivered",
    "sms_count": 1,
    "cost": 1,
    "sent_at": "2026-09-12 14:22:08"
  }
}</div>

  <h2>List messages</h2>
  <div class="endpoint"><b>GET</b> /api/v3/sms</div>
  <table>
    <thead><tr><th>Query</th><th>Description</th></tr></thead>
    <tbody>
      <tr><td>start_date</td><td>Filter from this date and time.</td></tr>
      <tr><td>end_date</td><td>Filter up to this date and time.</td></tr>
      <tr><td>sms_type</td><td><code>plain</code> or <code>unicode</code>.</td></tr>
      <tr><td>direction</td><td><code>api</code> or <code>dashboard</code>.</td></tr>
      <tr><td>page</td><td>Page number. 25 per page by default.</td></tr>
    </tbody>
  </table>

  <h2>Check your credits</h2>
  <div class="endpoint"><b>GET</b> /api/v3/balance</div>
  <div class="code">{
  "status": "success",
  "data": { "remaining_sms_unit": 4820, "rate": 0.99, "currency": "LKR" }
}</div>

  <h2>Your contact groups</h2>
  <div class="endpoint"><b>GET</b> /api/v3/contacts</div>
  <p>Returns the groups on your account with their IDs and contact counts.</p>

  <h2>Rate limits</h2>
  <p>
    {{ config('portal.api_rate_limit') }} requests a minute per token. Beyond that you get a <code>429</code> with a
    <code>RateLimit-Reset</code> header telling you how long to wait.
  </p>

  <h2>Errors</h2>
  <table>
    <thead><tr><th>Code</th><th>Meaning</th></tr></thead>
    <tbody>
      <tr><td>401</td><td>The token is missing, malformed or revoked.</td></tr>
      <tr><td>402</td><td>Not enough credits for this send.</td></tr>
      <tr><td>422</td><td>Something in the request is wrong; the message says what.</td></tr>
      <tr><td>429</td><td>Rate limited.</td></tr>
      <tr><td>500</td><td>Our side failed. The credits are not taken.</td></tr>
    </tbody>
  </table>
</main>

<footer class="foot">
  <div class="shell foot-grid">
    <div class="foot-brand">
      <strong data-brand>{{ config('portal.brand_name') }}</strong>
      <p>Bulk SMS, OTPs and scheduled campaigns — delivered directly to Dialog, Mobitel, Hutch and Airtel, with a receipt for every number.</p>
      <a href="mailto:{{ config('portal.support_email') }}">{{ config('portal.support_email') }}</a>
    </div>
    <div class="foot-col">
      <h3>Product</h3>
      <a href="/#what">What you get</a>
      <a href="/#journey">How it works</a>
      <a href="/#rates">Rates</a>
    </div>
    <div class="foot-col">
      <h3>Developers</h3>
      <a href="/docs">API reference</a>
      <a href="/register">Create account</a>
      <a href="/login">Sign in</a>
    </div>
    <div class="foot-col">
      <h3>Company</h3>
      <a href="mailto:{{ config('portal.support_email') }}">Support</a>
      <a href="/dashboard">Dashboard</a>
    </div>
  </div>
  <div class="shell foot-bottom">
    <span>&copy; <span id="year">{{ date('Y') }}</span> <span data-brand>{{ config('portal.brand_name') }}</span>. Delivered over the Text.lk gateway.</span>
    <span>Dialog &middot; Mobitel &middot; Hutch &middot; Airtel</span>
  </div>
</footer>

<script>
fetch('/config').then(function (r) { return r.json(); }).then(function (payload) {
  if (payload.status !== 'success') return;
  Array.prototype.forEach.call(document.querySelectorAll('[data-brand]'), function (el) {
    el.textContent = payload.data.brand_name;
  });
}).catch(function () {});
</script>
</body>
</html>
