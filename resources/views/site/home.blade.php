<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<x-seo
  title="SMS Gateway Sri Lanka — Bulk SMS & OTP API | {{ config('portal.brand_name') }}"
  description="{{ config('portal.brand_name') }} is a bulk SMS gateway in Sri Lanka, delivering SMS and OTPs directly to Dialog, Mobitel, Hutch and Airtel. Rs {{ config('portal.default_rate') }} per segment, {{ config('portal.signup_bonus') }} free credits, REST API included."
  keywords="sms gateway sri lanka, bulk sms sri lanka, sms api sri lanka, otp sms sri lanka, sms gateway, dialog sms gateway, mobitel sms gateway"
  region="LK"
/>
<link rel="stylesheet" href="{{ asset('css/base.css') }}">
<link rel="stylesheet" href="{{ asset('css/site.css') }}">
<link rel="stylesheet" href="{{ asset('css/home-gold.css') }}">
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "name": "{{ config('portal.brand_name') }}",
      "url": "{{ url('/') }}",
      "email": "{{ config('portal.support_email') }}",
      "address": { "@type": "PostalAddress", "addressCountry": "LK" },
      "areaServed": { "@type": "Country", "name": "Sri Lanka" },
      "knowsAbout": ["SMS gateway", "Bulk SMS", "OTP delivery", "SMS API", "Sinhala SMS", "Tamil SMS"]
    },
    {
      "@type": "WebSite",
      "name": "{{ config('portal.brand_name') }}",
      "url": "{{ url('/') }}"
    },
    {
      "@type": "Service",
      "name": "SMS gateway Sri Lanka — bulk SMS and OTP delivery",
      "url": "{{ url('/') }}",
      "provider": { "@type": "Organization", "name": "{{ config('portal.brand_name') }}" },
      "areaServed": { "@type": "Country", "name": "Sri Lanka" },
      "serviceType": "SMS gateway API",
      "keywords": "sms gateway sri lanka, bulk sms sri lanka, otp sms sri lanka",
      "offers": {
        "@type": "Offer",
        "priceCurrency": "{{ config('portal.currency') }}",
        "price": "{{ config('portal.default_rate') }}",
        "unitText": "per SMS segment"
      }
    },
    {
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "What is the best SMS gateway in Sri Lanka?",
          "acceptedAnswer": { "@type": "Answer", "text": "It depends on what you send, but look for direct routes to Dialog, Mobitel, Hutch and Airtel, honest Sinhala and Tamil pricing, and a transparent per-segment cost. {{ config('portal.brand_name') }} delivers directly to all four Sri Lankan networks at a flat Rs {{ config('portal.default_rate') }} per segment with no monthly minimum." }
        },
        {
          "@type": "Question",
          "name": "Is there an SMS gateway API for Sri Lanka?",
          "acceptedAnswer": { "@type": "Answer", "text": "Yes. {{ config('portal.brand_name') }} is a REST API for Sri Lanka: Bearer token auth, JSON in and out, with endpoints to send SMS, cost a message before sending, message a saved contact group, and check delivery status." }
        },
        {
          "@type": "Question",
          "name": "How much does an SMS cost?",
          "acceptedAnswer": { "@type": "Answer", "text": "Rs {{ config('portal.default_rate') }} per segment, flat rate, pay as you go. No plans and no monthly minimum, and the same price on Dialog, Mobitel, Hutch and Airtel." }
        },
        {
          "@type": "Question",
          "name": "Do you support Sinhala and Tamil messages?",
          "acceptedAnswer": { "@type": "Answer", "text": "Yes. Unicode messages such as Sinhala or Tamil fit 70 characters in a single segment and 67 in each segment after that, instead of the 160 a Latin (GSM-7) message gets." }
        },
        {
          "@type": "Question",
          "name": "How many free credits do I get when I sign up?",
          "acceptedAnswer": { "@type": "Answer", "text": "{{ config('portal.signup_bonus') }} free credits, enough to send a real test message to your own phone and see the delivery receipt come back." }
        },
        {
          "@type": "Question",
          "name": "Which networks do you deliver to?",
          "acceptedAnswer": { "@type": "Answer", "text": "Dialog, Mobitel, Hutch and Airtel, reached directly rather than through a shared shortcode." }
        },
        {
          "@type": "Question",
          "name": "How do I send an SMS from my own system?",
          "acceptedAnswer": { "@type": "Answer", "text": "Generate an API token in the dashboard, then send a POST request to /api/v3/sms/send with a recipient, an approved sender name and a message. Full details are in the API reference." }
        }
      ]
    }
  ]
}
</script>
</head>
<body class="home-gold">

<header class="masthead">
  <div class="shell">
    <a class="wordmark" href="/">
      <span class="glyph" aria-hidden="true">eS</span>
      <span data-brand>{{ config('portal.brand_name') }}</span>
    </a>
    <nav>
      <a href="#rates" class="hide-sm">Rates</a>
      <a href="#developers" class="hide-sm">Developers</a>
      <a href="#faq" class="hide-sm">FAQ</a>
      <a href="/login">Sign in</a>
      <a href="/register" class="btn btn-sm">Create account</a>
    </nav>
  </div>
</header>

<main>
  <section class="hero">
    <div class="hero-envelopes" aria-hidden="true">
      <span class="hero-envelope e1"><x-icon name="envelope" /></span>
      <span class="hero-envelope e2"><x-icon name="envelope" /></span>
      <span class="hero-envelope e3"><x-icon name="envelope" /></span>
      <span class="hero-envelope e4"><x-icon name="envelope" /></span>
      <span class="hero-envelope e5"><x-icon name="envelope" /></span>
    </div>
    <div class="shell">
      <div>
        <h1>Sri Lanka's SMS gateway, costed before you send.</h1>
        <p class="lede">
          Bulk SMS, OTPs and scheduled campaigns to Dialog, Mobitel, Hutch and Airtel —
          direct routes, not a shared aggregator. You see the segment count and the rupee
          cost while you type, and a delivery receipt for every number afterwards.
        </p>
        <div class="actions">
          <a class="btn" href="/register">Create an account</a>
          <a class="btn btn-line" href="#developers">Read the API</a>
        </div>

        <div class="assurances">
          <div><b>10</b>free credits when you sign up</div>
          <div><b>4</b>networks reached directly</div>
          <div><b>160</b>characters per credit</div>
        </div>
      </div>

      <div class="hero-right">
        <!-- The counter is the product in miniature, so it runs for real. -->
        <div class="demo">
          <header>
            <strong>Cost this message</strong>
            <span id="demo-encoding">GSM-7</span>
          </header>
          <label class="sr-only" for="demo-text">Message to cost</label>
          <textarea id="demo-text" spellcheck="false">Your OTP is 4821. It expires in 5 minutes. Do not share this code with anyone.</textarea>
          <dl class="readout">
            <div><dt>Characters</dt><dd id="demo-chars">0</dd></div>
            <div><dt>Credits each</dt><dd id="demo-segments">0</dd></div>
            <div><dt>Cost at Rs 0.99</dt><dd id="demo-cost">Rs 0.00</dd></div>
          </dl>
          <div class="segment-bar" id="demo-segbar" role="img" aria-label="Segment usage"></div>
          <p class="note" id="demo-note">A credit covers 160 GSM characters. Longer messages split into segments and bill per segment.</p>
        </div>

        <div class="price-card price-card-hero">
          <div class="price-card-figure">
            <span class="price-card-currency">Rs</span>
            <span class="price-card-amount">0.99</span>
            <span class="price-card-unit">/ SMS segment</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="operators" aria-label="Networks reached directly">
    <p class="operators-label shell">Delivering directly to every major network in Sri Lanka</p>
    <div class="operators-row shell">
      <span class="op-badge op-logo"><img src="{{ asset('images/operators/dialog.png') }}" alt="Dialog" loading="lazy"></span>
      <span class="op-badge op-logo"><img src="{{ asset('images/operators/mobitel.png') }}" alt="Mobitel" loading="lazy"></span>
      <span class="op-badge op-logo"><img src="{{ asset('images/operators/hutch.png') }}" alt="Hutch" loading="lazy"></span>
      <span class="op-badge op-logo"><img src="{{ asset('images/operators/airtel.png') }}" alt="Airtel" loading="lazy"></span>
    </div>
  </section>

  <section class="band" id="sri-lanka">
    <div class="shell">
      <h2>The SMS gateway built specifically for Sri Lanka</h2>
      <p class="sub">
        Not a global platform with Sri Lanka bolted on. Every part of this SMS gateway —
        encoding, pricing, number formats — is built around how messages actually move on
        Sri Lankan networks.
      </p>

      <div class="capabilities">
        <div>
          <h3>Direct routes to all four networks</h3>
          <p>Dialog, Mobitel, Hutch and Airtel — messages go straight to the network, not through a shared aggregator that adds latency and drops sender names.</p>
        </div>
        <div>
          <h3>Local numbers, not just international format</h3>
          <p>Send to 0712345678 or 94712345678 — both are accepted and normalised automatically, so you don't have to reformat every number your customers give you.</p>
        </div>
        <div>
          <h3>Sinhala and Tamil counted correctly</h3>
          <p>Unicode messages are priced honestly at 70 characters a segment, not silently billed as if they were English.</p>
        </div>
        <div>
          <h3>Priced in rupees, one flat rate</h3>
          <p>Rs 0.99 a segment, whether you send ten messages or ten thousand. No USD billing, no volume tiers to negotiate.</p>
        </div>
      </div>

      <p class="sub" style="margin-top:28px">
        Used as the SMS gateway for OTP verification, delivery and appointment reminders, and
        marketing campaigns by e-commerce, fintech, healthcare and logistics businesses across
        Sri Lanka.
      </p>
    </div>
  </section>

  <section class="band" id="what">
    <div class="shell">
      <h2>Built for the sends that cannot quietly fail</h2>
      <p class="sub">
        An OTP that arrives four minutes late is a lost customer. Everything here exists
        to make delivery visible rather than assumed.
      </p>

      <div class="capabilities">
        <div>
          <span class="cap-icon c-purple" aria-hidden="true"><x-icon name="language" /></span>
          <h3>Sinhala and Tamil, counted honestly</h3>
          <p>
            Non-Latin text drops to 70 characters per segment. The composer shows that
            the moment you type, so a Sinhala campaign never costs triple what you budgeted.
          </p>
        </div>
        <div>
          <span class="cap-icon c-green" aria-hidden="true"><x-icon name="check-circle" /></span>
          <h3>Delivery receipts per number</h3>
          <p>
            Filter by status, sender name or date range, then export the lot to CSV for
            your reconciliation.
          </p>
        </div>
        <div>
          <span class="cap-icon c-amber" aria-hidden="true"><x-icon name="tag" /></span>
          <h3>Registered sender names</h3>
          <p>
            Messages arrive from your brand, not a shortcode. Submit a sender name and
            we clear it with the operators.
          </p>
        </div>
        <div>
          <span class="cap-icon c-indigo" aria-hidden="true"><x-icon name="user-group" /></span>
          <h3>Contact groups</h3>
          <p>
            Paste a list, send to the whole group in one call, and keep the group in
            sync from your own system through the API.
          </p>
        </div>
        <div>
          <span class="cap-icon c-rose" aria-hidden="true"><x-icon name="clock" /></span>
          <h3>Scheduled sends</h3>
          <p>
            Queue a campaign for Monday at 9am. Credits are held when you schedule, so
            the send cannot fail for want of balance.
          </p>
        </div>
        <div>
          <span class="cap-icon c-gold" aria-hidden="true"><x-icon name="banknotes" /></span>
          <h3>Credits that reconcile</h3>
          <p>
            Every debit, refund and top-up lands in a ledger with a running balance.
            Failed sends return the credits automatically.
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="band" id="journey">
    <div class="shell">
      <h2>From your server to a Sri Lankan handset</h2>
      <p class="sub">Four steps, typically seconds apart. Every hop is visible in your dashboard.</p>

      <div class="journey">
        <div class="journey-step">
          <span class="journey-icon" aria-hidden="true"><x-icon name="paper-airplane" /></span>
          <h3>You call the API</h3>
          <p>One POST with a recipient, sender name and message. Cost comes back in the same response.</p>
        </div>
        <div class="journey-step">
          <span class="journey-icon c-gold" aria-hidden="true"><x-icon name="banknotes" /></span>
          <h3>Credits are held</h3>
          <p>The segment count is priced instantly and credits are reserved before anything sends.</p>
        </div>
        <div class="journey-step">
          <span class="journey-icon c-indigo" aria-hidden="true"><x-icon name="chat-bubble-left-right" /></span>
          <h3>Routed to the network</h3>
          <p>Delivered directly to Dialog, Mobitel, Hutch or Airtel — whichever the number belongs to.</p>
        </div>
        <div class="journey-step">
          <span class="journey-icon c-amber" aria-hidden="true"><x-icon name="check-circle" /></span>
          <h3>Receipt returns</h3>
          <p>A delivery status lands against the message ID, visible in the log or by webhook.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="band alt" id="rates">
    <div class="shell">
      <h2>One flat rate. Pay as you go.</h2>
      <p class="sub">
        No plans, no monthly minimums, no volume tiers to negotiate. One credit sends
        one segment, and every segment costs the same, however many you send.
      </p>

      <div class="price-card">
        <div class="price-card-figure">
          <span class="price-card-currency">Rs</span>
          <span class="price-card-amount">0.99</span>
          <span class="price-card-unit">/ SMS segment</span>
        </div>
        <ul class="price-card-list">
          <li>Buy credits whenever you like — they never expire</li>
          <li>The same rate on Dialog, Mobitel, Hutch and Airtel</li>
          <li>No setup fee, no monthly commitment</li>
          <li>10 free credits the moment you sign up</li>
        </ul>
        <a class="btn btn-block" href="/register">Create an account</a>
      </div>
    </div>
  </section>

  <section class="band" id="developers">
    <div class="shell">
      <h2>One POST and you are sending</h2>
      <div class="devgrid">
        <div>
          <p class="sub">
            Bearer token, JSON in, JSON out. Generate a token in the dashboard and keep
            it on your server.
          </p>
          <ul>
            <li>Send to one number or a comma-separated list</li>
            <li>Send to a saved contact group in a single call</li>
            <li>Check a message by its ID, or filter the log by date and status</li>
            <li>Read your remaining credits before a large batch</li>
            <li>Cost a message with <code>/sms/estimate</code> without sending it</li>
          </ul>
        </div>
        <pre class="code"><code><span class="c"># Send a message</span>
curl -X POST https://esms.lk/api/v3/sms/send \
  -H <span class="s">'Authorization: Bearer 12|your-token-here'</span> \
  -H <span class="s">'Content-Type: application/json'</span> \
  -H <span class="s">'Accept: application/json'</span> \
  -d <span class="s">'{
    "recipient": "94710000000",
    "sender_id": "YourBrand",
    "type": "plain",
    "message": "Your order is out for delivery."
  }'</span>

<span class="c"># Response</span>
{
  <span class="k">"status"</span>: <span class="s">"success"</span>,
  <span class="k">"data"</span>: {
    <span class="k">"uid"</span>: <span class="s">"a91f3c7d2e04"</span>,
    <span class="k">"to"</span>: <span class="s">"94710000000"</span>,
    <span class="k">"sms_count"</span>: 1,
    <span class="k">"cost"</span>: 1,
    <span class="k">"status"</span>: <span class="s">"Queued"</span>
  }
}</code></pre>
      </div>
    </div>
  </section>

  <section class="band" id="faq" aria-label="Frequently asked questions">
    <div class="shell">
      <h2>Frequently asked questions</h2>
      <div class="faq-list">
        <details>
          <summary>What is the best SMS gateway in Sri Lanka?</summary>
          <p>It depends on what you send, but look for direct routes to Dialog, Mobitel, Hutch and Airtel, honest Sinhala and Tamil pricing, and a transparent per-segment cost. e-SMS delivers directly to all four Sri Lankan networks at a flat Rs 0.99 per segment with no monthly minimum.</p>
        </details>
        <details>
          <summary>Is there an SMS gateway API for Sri Lanka?</summary>
          <p>Yes. e-SMS is a REST API for Sri Lanka: Bearer token auth, JSON in and out, with endpoints to send SMS, cost a message before sending, message a saved contact group, and check delivery status. See the <a href="/docs">API reference</a>.</p>
        </details>
        <details>
          <summary>How much does an SMS cost?</summary>
          <p>Rs 0.99 per segment, flat rate, pay as you go. No plans and no monthly minimum, and the same price on Dialog, Mobitel, Hutch and Airtel.</p>
        </details>
        <details>
          <summary>Do you support Sinhala and Tamil messages?</summary>
          <p>Yes. Unicode messages such as Sinhala or Tamil fit 70 characters in a single segment and 67 in each segment after that, instead of the 160 a Latin (GSM-7) message gets.</p>
        </details>
        <details>
          <summary>How many free credits do I get when I sign up?</summary>
          <p>10 free credits, enough to send a real test message to your own phone and see the delivery receipt come back.</p>
        </details>
        <details>
          <summary>Which networks do you deliver to?</summary>
          <p>Dialog, Mobitel, Hutch and Airtel, reached directly rather than through a shared shortcode.</p>
        </details>
        <details>
          <summary>How do I send an SMS from my own system?</summary>
          <p>Generate an API token in the dashboard, then send a POST request to <code>/api/v3/sms/send</code> with a recipient, an approved sender name and a message. Full details are in the <a href="/docs">API reference</a>.</p>
        </details>
      </div>
    </div>
  </section>

  <section class="closer">
    <div class="shell">
      <h2>Start with 10 free credits</h2>
      <p>No card needed. Add a sender name, send a test message to your own phone, and see the receipt come back.</p>
      <a class="btn" href="/register">Create an account</a>
    </div>
  </section>
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

<script src="{{ asset('js/segments.js') }}"></script>
<script>
(function () {
  var RATE = 0.99;
  var input = document.getElementById('demo-text');
  var chars = document.getElementById('demo-chars');
  var segs = document.getElementById('demo-segments');
  var cost = document.getElementById('demo-cost');
  var encoding = document.getElementById('demo-encoding');
  var note = document.getElementById('demo-note');
  var segbar = document.getElementById('demo-segbar');

  function renderSegmentBar(a) {
    segbar.innerHTML = '';
    var total = Math.max(a.segments, 1);
    var shown = Math.min(total, 6);
    for (var i = 0; i < shown; i++) {
      var block = document.createElement('span');
      block.className = 'seg-block' + (i < a.segments ? ' filled' : '') + (a.segments > 1 ? ' warn' : '');
      segbar.appendChild(block);
    }
    if (total > shown) {
      var more = document.createElement('span');
      more.className = 'seg-more';
      more.textContent = '+' + (total - shown) + ' more';
      segbar.appendChild(more);
    }
  }

  function update() {
    var a = Segments.analyse(input.value);
    chars.textContent = a.length + ' / ' + a.capacity;
    segs.textContent = a.segments;
    cost.textContent = 'Rs ' + (a.segments * RATE).toFixed(2);
    encoding.textContent = a.encoding === 'gsm' ? 'GSM-7' : 'Unicode';
    renderSegmentBar(a);

    if (a.encoding === 'unicode') {
      note.className = 'note warn';
      note.textContent = 'Sinhala, Tamil and emoji switch the message to Unicode, which fits only ' +
        Segments.LIMITS.unicode.single + ' characters in the first credit and ' +
        Segments.LIMITS.unicode.multi + ' in each one after.';
    } else if (a.segments > 1) {
      note.className = 'note warn';
      note.textContent = 'This message spans ' + a.segments + ' segments, so each recipient costs ' +
        a.segments + ' credits. Trimming to 160 characters would cost one.';
    } else {
      note.className = 'note';
      note.textContent = 'A credit covers 160 GSM characters. Longer messages split into segments and bill per segment.';
    }
  }

  input.addEventListener('input', update);
  update();
  document.getElementById('year').textContent = new Date().getFullYear();

  fetch('/config').then(function (r) { return r.json(); }).then(function (payload) {
    if (payload.status !== 'success') return;
    Array.prototype.forEach.call(document.querySelectorAll('[data-brand]'), function (el) {
      el.textContent = payload.data.brand_name;
    });
  }).catch(function () {});
})();
</script>
</body>
</html>
