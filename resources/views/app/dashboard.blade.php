<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<x-seo
  title="Dashboard — {{ config('portal.brand_name') }}"
  description="Send SMS, manage sender names, contact groups and API tokens."
  robots="noindex, nofollow"
  :og="false"
/>
<link rel="stylesheet" href="{{ asset('css/base.css') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="app">
  <aside class="side">
    <a class="wordmark" href="/">
      <span class="glyph" aria-hidden="true">eS</span>
      <span data-brand>{{ config('portal.brand_name') }}</span>
    </a>

    <nav id="nav">
      <a href="#overview" data-nav="overview">
        <x-icon name="home" class="nav-icon" />
        <span>Overview</span>
      </a>
      <a href="#compose" data-nav="compose">
        <x-icon name="paper-airplane" class="nav-icon" />
        <span>Send a message</span>
      </a>
      <a href="#messages" data-nav="messages">
        <x-icon name="chat-bubble-left-right" class="nav-icon" />
        <span>Messages</span>
      </a>
      <a href="#groups" data-nav="groups">
        <x-icon name="user-group" class="nav-icon" />
        <span>Contact groups</span>
      </a>
      <a href="#senders" data-nav="senders">
        <div class="nav-item-left">
          <x-icon name="tag" class="nav-icon" />
          <span>Sender names</span>
        </div>
        <span class="tag" id="nav-senders" hidden>0</span>
      </a>
      <div class="divider"></div>
      <a href="#tokens" data-nav="tokens">
        <x-icon name="key" class="nav-icon" />
        <span>API tokens</span>
      </a>
      <a href="#billing" data-nav="billing">
        <x-icon name="credit-card" class="nav-icon" />
        <span>Credits & Billing</span>
      </a>
      <a href="#settings" data-nav="settings">
        <x-icon name="cog-6-tooth" class="nav-icon" />
        <span>Account</span>
      </a>
    </nav>

    <dl class="balance">
      <dt>Credits remaining</dt>
      <dd id="balance">—</dd>
      <a class="btn btn-sm btn-block" href="#billing">Top up credits</a>
    </dl>

    <div class="who">
      <div class="who-id">
        <span class="who-avatar" id="who-avatar"></span>
        <span id="who-name">—</span>
      </div>
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

      <div class="quick-actions">
        <a class="quick-action" href="#compose">
          <span class="quick-action-icon qa-jade" aria-hidden="true"><x-icon name="paper-airplane" /></span>
          <span>Send a message</span>
        </a>
        <a class="quick-action" href="#senders">
          <span class="quick-action-icon qa-indigo" aria-hidden="true"><x-icon name="tag" /></span>
          <span>Request sender name</span>
        </a>
        <a class="quick-action" href="#groups">
          <span class="quick-action-icon qa-amber" aria-hidden="true"><x-icon name="user-group" /></span>
          <span>Create contact group</span>
        </a>
        <a class="quick-action" href="#billing">
          <span class="quick-action-icon qa-green" aria-hidden="true"><x-icon name="credit-card" /></span>
          <span>Top up credits</span>
        </a>
      </div>

      <dl class="metrics">
        <div class="metric-card metric-credits">
          <span class="metric-icon" aria-hidden="true"><x-icon name="wallet" /></span>
          <div class="metric-header">
            <dt>Credits remaining</dt>
            <span class="metric-badge badge-green">Available</span>
          </div>
          <dd id="m-credits">—</dd>
          <div class="sub" id="m-rate"></div>
        </div>
        <div class="metric-card metric-sent">
          <span class="metric-icon" aria-hidden="true"><x-icon name="paper-airplane" /></span>
          <div class="metric-header">
            <dt>Sent this month</dt>
            <span class="metric-badge badge-blue">This month</span>
          </div>
          <dd id="m-month">—</dd>
          <div class="sub" id="m-month-cost"></div>
        </div>
        <div class="metric-card metric-delivered">
          <span class="metric-icon" aria-hidden="true"><x-icon name="shield-check" /></span>
          <div class="metric-header">
            <dt>Delivered</dt>
            <span class="metric-badge badge-teal">Success rate</span>
          </div>
          <dd id="m-delivered">—</dd>
          <div class="sub" id="m-delivered-rate"></div>
        </div>
        <div class="metric-card metric-pending">
          <span class="metric-icon" aria-hidden="true"><x-icon name="clock" /></span>
          <div class="metric-header">
            <dt>In flight</dt>
            <span class="metric-badge badge-amber">Queued</span>
          </div>
          <dd id="m-pending">—</dd>
          <div class="sub" id="m-failed"></div>
        </div>
      </dl>

      <div class="panel">
        <header>
          <span class="panel-icon icon-jade" aria-hidden="true"><x-icon name="shield-check" /></span>
          <h2>Delivery status</h2>
        </header>
        <div class="body">
          <div class="status-bar" id="status-bar"></div>
          <ul class="status-legend" id="status-legend"></ul>
        </div>
      </div>

      <div class="panel">
        <header>
          <span class="panel-icon icon-jade" aria-hidden="true"><x-icon name="chart-bar" /></span>
          <h2>Messages over the last 14 days</h2>
        </header>
        <div class="body">
          <div class="spark" id="spark"></div>
          <div class="spark-axis"><span id="spark-from"></span><span id="spark-to"></span></div>
        </div>
      </div>

      <div class="panel">
        <header>
          <span class="panel-icon icon-indigo" aria-hidden="true"><x-icon name="chat-bubble-left-right" /></span>
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
          <span class="panel-icon icon-indigo" aria-hidden="true"><x-icon name="chat-bubble-left-right" /></span>
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
          <span class="panel-icon icon-amber" aria-hidden="true"><x-icon name="user-group" /></span>
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
          <span class="panel-icon icon-indigo" aria-hidden="true"><x-icon name="tag" /></span>
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
          <span class="panel-icon icon-jade" aria-hidden="true"><x-icon name="key" /></span>
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
          <pre class="code" style="background:var(--ink-deep);border-radius:var(--r-panel);padding:18px;overflow-x:auto;color:#d7e3f5;font-family:var(--mono);font-size:0.84rem;line-height:1.7"><code id="token-sample"></code></pre>
          <p style="margin-top:14px;font-size:0.9rem"><a href="/docs">Full API reference</a></p>
        </div>
      </div>
    </section>

    <!-- Billing -------------------------------------------------------- -->
    <section class="view" id="v-billing" hidden>
      <div class="panel">
        <header>
          <span class="panel-icon icon-indigo" aria-hidden="true"><x-icon name="credit-card" /></span>
          <h2>Pay with PayPal</h2>
          <p>Instant — credits land the moment payment clears. Charged in USD.</p>
        </header>
        <div class="body">
          <div id="paypal-notice"></div>
          <div class="filters">
            <label class="field">
              <span>Credits</span>
              <input class="input num" id="pp-units" type="number" min="100" step="100" value="1000">
            </label>
            <div class="field grow">
              <span>Total</span>
              <p id="pp-total-line" style="margin-top:10px;color:var(--muted);font-size:0.9rem"></p>
            </div>
          </div>
          <div id="paypal-buttons" style="max-width:320px;margin-top:14px"></div>
        </div>
      </div>

      <div class="panel">
        <header>
          <span class="panel-icon icon-green" aria-hidden="true"><x-icon name="banknotes" /></span>
          <h2>Request credits</h2>
          <p>Bank transfer — an admin adds the credits once payment clears.</p>
        </header>
        <div class="body">
          <div class="filters">
            <label class="field">
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
        <header>
          <span class="panel-icon icon-green" aria-hidden="true"><x-icon name="banknotes" /></span>
          <h2>Credit ledger</h2>
        </header>
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

@if (config('services.paypal.client_id'))
<script src="https://www.paypal.com/sdk/js?client-id={{ config('services.paypal.client_id') }}&currency=USD&intent=capture"></script>
@endif
<script src="{{ asset('js/segments.js') }}"></script>
<script src="{{ asset('js/ui.js') }}"></script>
<script src="{{ asset('js/dashboard.js') }}"></script>
</body>
</html>
