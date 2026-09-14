<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Console — {{ config('portal.brand_name') }}</title>
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

    <nav>
      <a href="#overview" data-nav="overview">
        <x-icon name="home" class="nav-icon" />
        <span>Overview</span>
      </a>
      <a href="#users" data-nav="users">
        <x-icon name="user-group" class="nav-icon" />
        <span>Customers</span>
      </a>
      <a href="#senders" data-nav="senders">
        <div class="nav-item-left">
          <x-icon name="tag" class="nav-icon" />
          <span>Sender approvals</span>
        </div>
        <span class="tag" id="nav-senders" hidden>0</span>
      </a>
      <a href="#requests" data-nav="requests">
        <div class="nav-item-left">
          <x-icon name="banknotes" class="nav-icon" />
          <span>Credit requests</span>
        </div>
        <span class="tag" id="nav-requests" hidden>0</span>
      </a>
      <a href="#traffic" data-nav="traffic">
        <x-icon name="chart-bar" class="nav-icon" />
        <span>All traffic</span>
      </a>
      <div class="divider"></div>
      <a href="#settings" data-nav="settings">
        <x-icon name="cog-6-tooth" class="nav-icon" />
        <span>Portal settings</span>
      </a>
      <a href="/dashboard">
        <x-icon name="arrow-right-circle" class="nav-icon" />
        <span>My own dashboard</span>
      </a>
    </nav>

    <dl class="balance">
      <dt>Credits held upstream</dt>
      <dd id="upstream">—</dd>
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
      <div id="reconcile"></div>

      <dl class="metrics">
        <div class="metric-card metric-tenants">
          <span class="metric-icon" aria-hidden="true"><x-icon name="user-group" /></span>
          <div class="metric-header"><dt>Customers</dt></div>
          <dd id="m-tenants">—</dd>
          <div class="sub" id="m-pending-senders"></div>
        </div>
        <div class="metric-card metric-sold">
          <span class="metric-icon" aria-hidden="true"><x-icon name="wallet" /></span>
          <div class="metric-header"><dt>Credits sold</dt></div>
          <dd id="m-sold">—</dd>
          <div class="sub">Owed to customers</div>
        </div>
        <div class="metric-card metric-traffic">
          <span class="metric-icon" aria-hidden="true"><x-icon name="paper-airplane" /></span>
          <div class="metric-header"><dt>Sent this month</dt></div>
          <dd id="m-traffic">—</dd>
          <div class="sub" id="m-units"></div>
        </div>
        <div class="metric-card metric-revenue">
          <span class="metric-icon" aria-hidden="true"><x-icon name="banknotes" /></span>
          <div class="metric-header"><dt>Billed this month</dt></div>
          <dd id="m-revenue">—</dd>
          <div class="sub">At customer rates</div>
        </div>
      </dl>

      <div class="panel">
        <header>
          <span class="panel-icon icon-jade" aria-hidden="true"><x-icon name="shield-check" /></span>
          <h2>Delivery status this month</h2>
        </header>
        <div class="body">
          <div class="status-bar" id="status-bar"></div>
          <ul class="status-legend" id="status-legend"></ul>
        </div>
      </div>

      <div class="panel">
        <header>
          <span class="panel-icon icon-amber" aria-hidden="true"><x-icon name="bell" /></span>
          <h2>Waiting on you</h2>
        </header>
        <div class="body flush"><div id="queue"></div></div>
      </div>
    </section>

    <!-- Customers ------------------------------------------------------ -->
    <section class="view" id="v-users" hidden>
      <div class="panel">
        <div class="body">
          <div class="filters">
            <label class="field grow">
              <span>Search</span>
              <input class="input" id="u-search" placeholder="Name, email or business">
            </label>
            <button class="btn btn-line" id="u-apply">Search</button>
          </div>
        </div>
      </div>
      <div class="panel">
        <header>
          <span class="panel-icon icon-indigo" aria-hidden="true"><x-icon name="user-group" /></span>
          <h2>Customers</h2>
        </header>
        <div class="body flush"><div id="users-table"></div></div>
      </div>
    </section>

    <!-- Sender approvals ----------------------------------------------- -->
    <section class="view" id="v-senders" hidden>
      <div class="panel">
        <header>
          <span class="panel-icon icon-indigo" aria-hidden="true"><x-icon name="tag" /></span>
          <h2>Sender names</h2>
          <p>Approve only names the customer is entitled to use.</p>
        </header>
        <div class="body flush"><div id="senders-table"></div></div>
      </div>
    </section>

    <!-- Credit requests ------------------------------------------------ -->
    <section class="view" id="v-requests" hidden>
      <div class="panel">
        <header>
          <span class="panel-icon icon-green" aria-hidden="true"><x-icon name="banknotes" /></span>
          <h2>Credit requests</h2>
          <p>Add the credits once payment has cleared. The request clears itself.</p>
        </header>
        <div class="body flush"><div id="requests-table"></div></div>
      </div>
    </section>

    <!-- Traffic -------------------------------------------------------- -->
    <section class="view" id="v-traffic" hidden>
      <div class="panel">
        <header>
          <span class="panel-icon icon-jade" aria-hidden="true"><x-icon name="chat-bubble-left-right" /></span>
          <h2>All messages</h2>
        </header>
        <div class="body flush"><div id="traffic-table"></div></div>
        <div class="pager" id="traffic-pager" hidden>
          <span id="pager-label"></span>
          <button class="btn btn-sm btn-line" id="pager-prev">Previous</button>
          <button class="btn btn-sm btn-line" id="pager-next">Next</button>
        </div>
      </div>
    </section>

    <!-- Settings --------------------------------------------------- -->
    <section class="view" id="v-settings" hidden>
      <div class="panel">
        <header>
          <span class="panel-icon icon-jade" aria-hidden="true"><x-icon name="cog-6-tooth" /></span>
          <h2>Portal settings</h2>
          <p>These apply to new accounts and the public site.</p>
        </header>
        <div class="body">
          <div class="row">
            <label class="field"><span>Brand name</span><input class="input" id="s-brand"></label>
            <label class="field"><span>Support email</span><input class="input" id="s-support"></label>
          </div>
          <div class="row">
            <label class="field">
              <span>Flat pay-as-you-go rate <span class="hint">rupees per SMS for new accounts</span></span>
              <input class="input num" id="s-rate" type="number" step="0.01" min="0">
            </label>
            <label class="field">
              <span>Welcome credits <span class="hint">granted at sign-up</span></span>
              <input class="input num" id="s-bonus" type="number" step="1" min="0">
            </label>
          </div>
          <div class="row">
            <label class="field">
              <span>Sender ID fee <span class="hint">rupees, charged per request, refunded if rejected</span></span>
              <input class="input num" id="s-sender-fee" type="number" step="1" min="0">
            </label>
            <label class="field">
              <span>PayPal exchange rate <span class="hint">rupees per US dollar — PayPal cannot settle in LKR</span></span>
              <input class="input num" id="s-paypal-rate" type="number" step="0.01" min="1">
            </label>
          </div>
          <button class="btn" id="s-save">Save settings</button>
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

<script src="{{ asset('js/ui.js') }}"></script>
<script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>
