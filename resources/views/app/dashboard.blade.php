<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Dashboard — {{ config('portal.brand_name') }}</title>
<link rel="stylesheet" href="{{ asset('css/base.css') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="app">
  <aside class="side">
    <a class="wordmark" href="/">
      <span class="glyph" aria-hidden="true">LK</span>
      <span data-brand>{{ config('portal.brand_name') }}</span>
    </a>

    <nav id="nav">
      <a href="#overview" data-nav="overview">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span>Overview</span>
      </a>
      <a href="#compose" data-nav="compose">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
        <span>Send a message</span>
      </a>
      <a href="#messages" data-nav="messages">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        <span>Messages</span>
      </a>
      <a href="#groups" data-nav="groups">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span>Contact groups</span>
      </a>
      <a href="#senders" data-nav="senders">
        <div class="nav-item-left">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
          <span>Sender names</span>
        </div>
        <span class="tag" id="nav-senders" hidden>0</span>
      </a>
      <div class="divider"></div>
      <a href="#tokens" data-nav="tokens">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
        <span>API tokens</span>
      </a>
      <a href="#billing" data-nav="billing">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        <span>Credits & Billing</span>
      </a>
      <a href="#settings" data-nav="settings">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <span>Account</span>
      </a>
    </nav>

    <dl class="balance">
      <dt>Credits remaining</dt>
      <dd id="balance">—</dd>
      <a class="btn btn-sm btn-block" href="#billing">Top up credits</a>
    </dl>

    <div class="who">
      <span id="who-name">—</span>
      <button type="button" id="signout">Sign out</button>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div>
        <h1 id="view-title">Overview</h1>
        <p id="view-sub"></p>
      </div>
      <div class="actions" id="view-actions"></div>
    </div>

    <!-- Overview ------------------------------------------------------- -->
    <section class="view" id="v-overview">
      <div id="overview-notice"></div>

      <dl class="metrics">
        <div class="metric-card metric-credits">
          <div class="metric-header">
            <dt>Credits remaining</dt>
            <span class="metric-badge badge-green">Available</span>
          </div>
          <dd id="m-credits">—</dd>
          <div class="sub" id="m-rate"></div>
        </div>
        <div class="metric-card metric-sent">
          <div class="metric-header">
            <dt>Sent this month</dt>
            <span class="metric-badge badge-blue">This month</span>
          </div>
          <dd id="m-month">—</dd>
          <div class="sub" id="m-month-cost"></div>
        </div>
        <div class="metric-card metric-delivered">
          <div class="metric-header">
            <dt>Delivered</dt>
            <span class="metric-badge badge-teal">Success rate</span>
          </div>
          <dd id="m-delivered">—</dd>
          <div class="sub" id="m-delivered-rate"></div>
        </div>
        <div class="metric-card metric-pending">
          <div class="metric-header">
            <dt>In flight</dt>
            <span class="metric-badge badge-amber">Queued</span>
          </div>
          <dd id="m-pending">—</dd>
          <div class="sub" id="m-failed"></div>
        </div>
      </dl>

      <div class="panel">
        <header><h2>Messages over the last 14 days</h2></header>
        <div class="body">
          <div class="spark" id="spark"></div>
          <div class="spark-axis"><span id="spark-from"></span><span id="spark-to"></span></div>
        </div>
      </div>

      <div class="panel">
        <header>
          <h2>Latest messages</h2>
          <div class="actions"><a class="btn btn-sm btn-line" href="#messages">See all</a></div>
        </header>
        <div class="body flush"><div id="recent"></div></div>
      </div>
    </section>

    <!-- Compose -------------------------------------------------------- -->
    <section class="view" id="v-compose" hidden>
      <div id="compose-notice"></div>

      <div class="tabs" role="tablist">
        <button role="tab" aria-selected="true" data-mode="numbers">To phone numbers</button>
        <button role="tab" aria-selected="false" data-mode="group">To a contact group</button>
      </div>

      <div class="composer">
        <div class="panel">
          <div class="body">
            <label class="field" id="field-numbers">
              <span>Recipients <span class="hint">one per line, or comma separated</span></span>
              <textarea class="textarea" id="c-recipients" style="min-height:82px" placeholder="0712345678&#10;0771234567"></textarea>
            </label>

            <label class="field" id="field-group" hidden>
              <span>Contact group</span>
              <select class="select" id="c-group"></select>
            </label>

            <label class="field">
              <span>Send from</span>
              <select class="select" id="c-sender"></select>
            </label>

            <div class="field">
              <span>Message</span>
              <textarea class="textarea composer-body" id="c-message" placeholder="Type your message"></textarea>
              <div class="meter">
                <div class="meter-labels">
                  <span><b id="c-chars">0</b> of <span id="c-capacity">160</span> characters</span>
                  <span id="c-encoding" class="encoding-tag">GSM-7</span>
                </div>
                <div class="meter-bar">
                  <div class="meter-progress" id="c-meter-progress" style="width: 0%"></div>
                </div>
              </div>
            </div>

            <label class="field">
              <span>Schedule <span class="hint">optional, leave empty to send now</span></span>
              <input class="input" type="datetime-local" id="c-schedule">
            </label>
          </div>
        </div>

        <div class="panel">
          <header><h2>Before you send</h2></header>
          <div class="body">
            <dl class="summary">
              <div><dt>Recipients</dt><dd id="s-recipients">0</dd></div>
              <div><dt>Segments each</dt><dd id="s-segments">0</dd></div>
              <div><dt>Encoding</dt><dd id="s-encoding">GSM-7</dd></div>
              <div class="total"><dt>Credits needed</dt><dd id="s-units">0</dd></div>
              <div><dt>Balance after</dt><dd id="s-after">—</dd></div>
            </dl>
            <p id="s-warning" class="notice notice-warn" style="margin:16px 0 0" hidden></p>
            <button class="btn btn-block" id="c-send" style="margin-top:18px">Send message</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Messages ------------------------------------------------------- -->
    <section class="view" id="v-messages" hidden>
      <div class="panel">
        <div class="body">
          <div class="filters">
            <label class="field grow">
              <span>Search</span>
              <input class="input" id="f-search" placeholder="Number or message text">
            </label>
            <label class="field">
              <span>Status</span>
              <select class="select" id="f-status">
                <option value="">All</option>
                <option>Delivered</option>
                <option>Queued</option>
                <option>Sent</option>
                <option>Scheduled</option>
                <option>Failed</option>
                <option>Rejected</option>
              </select>
            </label>
            <label class="field">
              <span>From</span>
              <input class="input" type="date" id="f-start">
            </label>
            <label class="field">
              <span>To</span>
              <input class="input" type="date" id="f-end">
            </label>
            <button class="btn btn-line" id="f-apply">Apply</button>
            <button class="btn btn-ghost" id="f-clear">Clear</button>
          </div>
        </div>
      </div>

      <div class="panel">
        <header>
          <h2>Message log</h2>
          <div class="actions">
            <button class="btn btn-sm btn-line" id="m-sync">Refresh statuses</button>
            <button class="btn btn-sm btn-line" id="m-export">Export CSV</button>
          </div>
        </header>
        <div class="body flush"><div id="messages-table"></div></div>
        <div class="pager" id="messages-pager" hidden>
          <span id="pager-label"></span>
          <button class="btn btn-sm btn-line" id="pager-prev">Previous</button>
          <button class="btn btn-sm btn-line" id="pager-next">Next</button>
        </div>
      </div>
    </section>

    <!-- Groups --------------------------------------------------------- -->
    <section class="view" id="v-groups" hidden>
      <div class="panel">
        <header>
          <h2>Contact groups</h2>
          <div class="actions"><button class="btn btn-sm" id="g-new">New group</button></div>
        </header>
        <div class="body flush"><div id="groups-table"></div></div>
      </div>

      <div class="panel" id="contacts-panel" hidden>
        <header>
          <h2 id="contacts-title">Contacts</h2>
          <div class="actions">
            <button class="btn btn-sm btn-line" id="c-import">Paste a list</button>
            <button class="btn btn-sm" id="c-add">Add contact</button>
          </div>
        </header>
        <div class="body flush"><div id="contacts-table"></div></div>
      </div>
    </section>

    <!-- Sender names --------------------------------------------------- -->
    <section class="view" id="v-senders" hidden>
      <div class="panel">
        <header>
          <h2>Sender names</h2>
          <p>Up to 11 characters. Operators must clear each name before it can send.
             A <span class="num" id="sender-fee-hint">—</span> registration fee applies per request, refunded in full if rejected.</p>
        </header>
        <div class="body">
          <div class="filters">
            <label class="field grow">
              <span>Request a sender name</span>
              <input class="input" id="sender-mask" maxlength="11" placeholder="YourBrand">
            </label>
            <button class="btn" id="sender-add">Request approval</button>
          </div>
        </div>
        <div class="body flush"><div id="senders-table"></div></div>
      </div>
    </section>

    <!-- API tokens ----------------------------------------------------- -->
    <section class="view" id="v-tokens" hidden>
      <div id="token-reveal"></div>

      <div class="panel">
        <header>
          <h2>API tokens</h2>
          <div class="actions"><button class="btn btn-sm" id="t-new">Create token</button></div>
        </header>
        <div class="body flush"><div id="tokens-table"></div></div>
      </div>

      <div class="panel">
        <header><h2>Sending from your own application</h2></header>
        <div class="body">
          <p style="color:var(--muted);font-size:0.92rem;margin-bottom:14px">
            Keep the token on your server. Anyone holding it can spend your credits.
          </p>
          <pre class="code" style="background:var(--ink-deep);border-radius:var(--r-panel);padding:18px;overflow-x:auto;color:#d6e6e6;font-family:var(--mono);font-size:0.84rem;line-height:1.7"><code id="token-sample"></code></pre>
          <p style="margin-top:14px;font-size:0.9rem"><a href="/docs">Full API reference</a></p>
        </div>
      </div>
    </section>

    <!-- Billing -------------------------------------------------------- -->
    <section class="view" id="v-billing" hidden>
      <div class="panel">
        <header>
          <h2>Credit packages</h2>
          <p>Buy in bulk for a lower rate per SMS.</p>
        </header>
        <div class="body flush"><div id="packages-table"></div></div>
      </div>

      <div class="panel">
        <header>
          <h2>Request credits</h2>
          <p>Credits are added once your payment clears.</p>
        </header>
        <div class="body">
          <div class="filters">
            <label class="field">
              <span>Package</span>
              <select class="select" id="b-package"></select>
            </label>
            <label class="field" id="b-units-field" hidden>
              <span>Credits</span>
              <input class="input num" id="b-units" type="number" min="100" step="100" value="1000">
            </label>
            <label class="field grow">
              <span>Note <span class="hint">optional</span></span>
              <input class="input" id="b-note" placeholder="Bank transfer reference">
            </label>
            <button class="btn" id="b-request">Send request</button>
          </div>
          <p style="color:var(--muted);font-size:0.88rem" id="b-total-line"></p>
        </div>
      </div>

      <div class="panel">
        <header><h2>Credit ledger</h2></header>
        <div class="body flush"><div id="ledger-table"></div></div>
      </div>
    </section>

    <!-- Account -------------------------------------------------------- -->
    <section class="view" id="v-settings" hidden>
      <div class="panel">
        <header><h2>Your details</h2></header>
        <div class="body">
          <div class="row">
            <label class="field"><span>Name</span><input class="input" id="p-name"></label>
            <label class="field"><span>Business name</span><input class="input" id="p-company"></label>
          </div>
          <div class="row">
            <label class="field"><span>Mobile</span><input class="input" id="p-phone"></label>
            <label class="field"><span>Email</span><input class="input" id="p-email" disabled></label>
          </div>
          <button class="btn" id="p-save">Save changes</button>
        </div>
      </div>

      <div class="panel">
        <header><h2>Change password</h2></header>
        <div class="body">
          <div class="row">
            <label class="field"><span>Current password</span><input class="input" type="password" id="p-current" autocomplete="current-password"></label>
            <label class="field"><span>New password</span><input class="input" type="password" id="p-new" autocomplete="new-password"></label>
          </div>
          <button class="btn" id="p-password">Change password</button>
        </div>
      </div>
    </section>
  </div>
</div>

<div class="modal" id="modal" hidden>
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <h2 id="modal-title"></h2>
    <p class="sub" id="modal-sub"></p>
    <div id="modal-body"></div>
    <div class="foot">
      <button class="btn btn-line" id="modal-cancel">Cancel</button>
      <button class="btn" id="modal-ok">Confirm</button>
    </div>
  </div>
</div>

<div id="toast" aria-live="polite"></div>

<script src="{{ asset('js/segments.js') }}"></script>
<script src="{{ asset('js/ui.js') }}"></script>
<script src="{{ asset('js/dashboard.js') }}"></script>
</body>
</html>
