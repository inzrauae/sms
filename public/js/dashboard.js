/* Dashboard controller. */
(function () {
  'use strict';

  var $ = UI.$, $$ = UI.$$;

  /* Icon paths sourced from Heroicons (heroicons.com), MIT licensed, 20px solid set. */
  var ICON_CHECK = '<path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"/>';
  var ICON_CLOCK = '<path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z"/>';
  var ICON_X = '<path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/>';

  var state = {
    me: null,
    rate: 1.1,
    senders: [],
    groups: [],
    mode: 'numbers',
    activeGroup: null,
    page: 1,
    lastPage: 1,
    senderIdFee: 1000,
    paypalUsdRate: 300,
    paypalRendered: false
  };

  /* Boot ---------------------------------------------------------------- */

  async function boot() {
    try {
      var session = await UI.api('/auth/session');
      state.me = session.data;
      state.rate = session.data.rate;
      $('#who-name').textContent = session.data.name;
      $('#who-avatar').textContent = initials(session.data.name);
      setBalance(session.data.credits);
      fillAccount(session.data);
    } catch (err) {
      return; // api() already redirected
    }

    fetch('/config').then(function (r) { return r.json(); }).then(function (payload) {
      if (payload.status !== 'success') return;
      $$('[data-brand]').forEach(function (el) { el.textContent = payload.data.brand_name; });
      document.title = 'Dashboard — ' + payload.data.brand_name;
      state.senderIdFee = payload.data.sender_id_fee || 1000;
      $('#sender-fee-hint').textContent = UI.formatMoney(state.senderIdFee);
      state.paypalUsdRate = payload.data.paypal_usd_rate || 300;
      updatePaypalTotal();
    }).catch(function () {});

    var firstName = (state.me.name || '').split(' ')[0] || 'there';
    var hour = new Date().getHours();
    var greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

    UI.router({
      overview: { title: greeting + ', ' + firstName, sub: 'Here is your account at a glance.' },
      compose: { title: 'Send a message', sub: 'Costed before it leaves.' },
      messages: { title: 'Messages', sub: 'Every message and what happened to it.' },
      groups: { title: 'Contact groups', sub: 'Saved lists you can send to in one call.' },
      senders: { title: 'Sender names', sub: 'The names your messages arrive from.' },
      tokens: { title: 'API tokens', sub: 'For sending from your own application.' },
      billing: { title: 'Credits', sub: 'Top-ups, debits and refunds.' },
      settings: { title: 'Account', sub: 'Your details and password.' }
    }, load);

    wire();
  }

  function initials(name) {
    var parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
  }

  function setBalance(credits) {
    var node = $('#balance');
    node.textContent = UI.formatNumber(credits);
    node.className = credits < 20 ? 'low' : '';
    if (state.me) state.me.credits = credits;
    updateSummary();
  }

  function load(view) {
    ({
      overview: loadOverview,
      compose: loadCompose,
      messages: loadMessages,
      groups: loadGroups,
      senders: loadSenders,
      tokens: loadTokens,
      billing: loadBilling,
      settings: function () {}
    })[view]();
  }

  /* Overview ------------------------------------------------------------ */

  async function loadOverview() {
    try {
      var res = await UI.api('/app/overview');
      var d = res.data;
      state.rate = d.rate;
      setBalance(d.credits);

      $('#m-credits').textContent = UI.formatNumber(d.credits);
      $('#m-rate').textContent = UI.formatMoney(d.rate) + ' per SMS';
      $('#m-month').textContent = UI.formatNumber(d.month.sent);
      $('#m-month-cost').textContent = UI.formatMoney(d.month.cost) + ' this month';

      var delivered = d.totals.delivered || 0;
      var total = d.totals.total || 0;
      $('#m-delivered').textContent = UI.formatNumber(delivered);
      $('#m-delivered-rate').textContent = total
        ? Math.round((delivered / total) * 100) + '% of all sends'
        : 'No messages yet';
      $('#m-pending').textContent = UI.formatNumber(d.totals.pending || 0);
      $('#m-failed').textContent = UI.formatNumber(d.totals.failed || 0) + ' failed';

      drawStatusBar(d.totals);
      drawSpark(d.daily);
      drawRecent(d.recent);
      noticeFor(d);
    } catch (err) {
      UI.toast(err.message, 'bad');
    }
  }

  function drawStatusBar(totals) {
    var delivered = totals.delivered || 0;
    var pending = totals.pending || 0;
    var failed = totals.failed || 0;
    var total = delivered + pending + failed;

    var bar = $('#status-bar');
    var legend = $('#status-legend');

    if (!total) {
      bar.innerHTML = '';
      bar.setAttribute('aria-hidden', 'true');
      legend.innerHTML = '<li class="status-legend-item"><span>No messages sent yet — this fills in once you start sending.</span></li>';
      return;
    }

    var segments = [
      { key: 's-ok', label: 'Delivered', count: delivered, icon: ICON_CHECK },
      { key: 's-wait', label: 'In flight', count: pending, icon: ICON_CLOCK },
      { key: 's-bad', label: 'Failed', count: failed, icon: ICON_X }
    ];

    bar.setAttribute('role', 'img');
    bar.setAttribute('aria-label', segments.map(function (s) {
      return s.label + ' ' + s.count + ' of ' + total;
    }).join(', '));

    bar.innerHTML = segments
      .filter(function (s) { return s.count > 0; })
      .map(function (s) {
        var pct = (s.count / total) * 100;
        return '<div class="status-bar-seg ' + s.key + '" style="flex-basis:' + pct.toFixed(2) + '%"></div>';
      })
      .join('');

    legend.innerHTML = segments.map(function (s) {
      var pct = Math.round((s.count / total) * 100);
      return '<li class="status-legend-item">' +
        '<span class="status-swatch ' + s.key + '"></span>' +
        '<svg class="status-legend-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">' + s.icon + '</svg>' +
        '<span class="status-legend-label">' + s.label + '</span>' +
        '<span class="status-legend-value">' + UI.formatNumber(s.count) + ' (' + pct + '%)</span>' +
        '</li>';
    }).join('');
  }

  function drawSpark(daily) {
    var byDay = {};
    daily.forEach(function (row) { byDay[row.day] = row.count; });

    var days = [];
    for (var i = 13; i >= 0; i--) {
      var d = new Date();
      d.setDate(d.getDate() - i);
      var key = d.toISOString().slice(0, 10);
      days.push({ key: key, count: byDay[key] || 0 });
    }

    $('#spark-from').textContent = days[0].key.slice(5);
    $('#spark-to').textContent = 'today';

    var activeDays = days.filter(function (d) { return d.count > 0; }).length;
    if (!activeDays) {
      var emptyHost = $('#spark');
      emptyHost.classList.add('is-empty');
      emptyHost.innerHTML = '<div class="spark-empty">' +
        '<strong>No messages in the last 14 days</strong>' +
        '<span>Send your first message to see activity here.</span></div>';
      return;
    }

    var peak = Math.max.apply(null, days.map(function (d) { return d.count; }).concat([1]));
    var W = 100, H = 80, gap = 2;
    var barW = (W - gap * (days.length - 1)) / days.length;

    var defs = '<defs><linearGradient id="sg" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0%" stop-color="#0e7c6b" stop-opacity="1"/>' +
      '<stop offset="100%" stop-color="#0e7c6b" stop-opacity="0.35"/>' +
      '</linearGradient></defs>';

    var bars = days.map(function (d, i) {
      var pct = d.count ? Math.max((d.count / peak) * H, 4) : 2;
      var x = i * (barW + gap);
      var y = H - pct;
      var opacity = d.count ? 1 : 0.18;
      var label = d.key.slice(5) + ' — ' + UI.formatNumber(d.count) + ' SMS';
      return '<rect x="' + x.toFixed(2) + '" y="' + y.toFixed(2) + '" ' +
        'width="' + barW.toFixed(2) + '" height="' + pct.toFixed(2) + '" ' +
        'rx="1.5" fill="url(#sg)" opacity="' + opacity + '" ' +
        'class="spark-bar" data-label="' + label + '">' +
        '<title>' + label + '</title></rect>';
    }).join('');

    var svgEl = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svgEl.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svgEl.setAttribute('preserveAspectRatio', 'none');
    svgEl.style.cssText = 'width:100%;height:80px;display:block;';
    svgEl.innerHTML = defs + bars;

    /* tooltip */
    var tip = document.createElement('div');
    tip.className = 'spark-tooltip';
    tip.hidden = true;

    svgEl.addEventListener('mousemove', function (e) {
      var rect = svgEl.getBoundingClientRect();
      var relX = e.clientX - rect.left;
      var idx = Math.min(Math.floor((relX / rect.width) * days.length), days.length - 1);
      if (idx < 0) return;
      tip.textContent = days[idx].key.slice(5) + ': ' + UI.formatNumber(days[idx].count) + ' messages';
      tip.hidden = false;
      tip.style.left = Math.min(relX, rect.width - 120) + 'px';
    });
    svgEl.addEventListener('mouseleave', function () { tip.hidden = true; });

    var host = $('#spark');
    host.classList.remove('is-empty');
    host.innerHTML = '';
    host.style.position = 'relative';
    host.appendChild(svgEl);
    host.appendChild(tip);
  }

  function drawRecent(rows) {
    if (!rows.length) {
      $('#recent').innerHTML = UI.emptyState('Nothing sent yet', 'Your first message will show up here.');
      return;
    }
    $('#recent').innerHTML = table(
      ['Sent', 'To', 'From', 'Message', 'Status'],
      rows.map(function (m) {
        return [
          UI.formatDate(m.created_at),
          '<span class="num">' + UI.escapeHtml(m.recipient) + '</span>',
          UI.escapeHtml(m.sender_id),
          '<div class="truncate">' + UI.escapeHtml(m.body) + '</div>',
          UI.statusPill(m.status)
        ];
      })
    );
  }

  function noticeFor(d) {
    var host = $('#overview-notice');
    var approved = d.senders.filter(function (s) { return s.status === 'approved'; });

    if (!d.senders.length) {
      host.innerHTML = '<div class="notice notice-warn">Before you can send, request a sender name. ' +
        '<a href="#senders">Request one now</a>.</div>';
    } else if (!approved.length) {
      host.innerHTML = '<div class="notice notice-warn">Your sender name is waiting for operator approval. ' +
        'Sending unlocks as soon as it clears.</div>';
    } else if (d.credits < 20) {
      host.innerHTML = '<div class="notice notice-warn">You have ' + UI.formatNumber(d.credits) +
        ' credits left. <a href="#billing">Top up</a> to keep sending.</div>';
    } else {
      host.innerHTML = '';
    }
  }

  /* Composer ------------------------------------------------------------ */

  async function loadCompose() {
    await Promise.all([refreshSenders(), refreshGroups()]);

    var approved = state.senders.filter(function (s) { return s.status === 'approved'; });
    var select = $('#c-sender');
    select.innerHTML = approved.length
      ? approved.map(function (s) { return '<option>' + UI.escapeHtml(s.mask) + '</option>'; }).join('')
      : '<option value="">No approved sender name</option>';
    select.disabled = !approved.length;

    $('#c-group').innerHTML = state.groups.length
      ? state.groups.map(function (g) {
          return '<option value="' + UI.escapeHtml(g.provider_uid) + '" data-contacts="' + g.contacts + '">' +
            UI.escapeHtml(g.name) + ' (' + g.contacts + ' contacts)</option>';
        }).join('')
      : '<option value="">No groups yet</option>';

    var notice = $('#compose-notice');
    if (!approved.length) {
      notice.innerHTML = '<div class="notice notice-warn">You need an approved sender name before sending. ' +
        '<a href="#senders">Request one</a>.</div>';
    } else {
      notice.innerHTML = '';
    }

    $('#c-send').disabled = !approved.length;
    updateSummary();
  }

  function updateSummary() {
    var analysis = Segments.analyse($('#c-message').value);
    var recipients;

    if (state.mode === 'group') {
      var option = $('#c-group').selectedOptions[0];
      recipients = option ? Number(option.dataset.contacts || 0) : 0;
    } else {
      recipients = Segments.countRecipients($('#c-recipients').value).count;
    }

    var units = analysis.segments * Math.max(recipients, 0);
    var balance = state.me ? state.me.credits : 0;

    $('#c-chars').textContent = analysis.length;
    $('#c-capacity').textContent = analysis.capacity;

    /* encoding badge — toggle .unicode class and update text */
    var encEl = $('#c-encoding');
    if (encEl) {
      var isUnicode = analysis.encoding === 'unicode';
      encEl.textContent = isUnicode ? 'Unicode' : 'GSM-7';
      encEl.classList.toggle('unicode', isUnicode);
    }

    $('#s-recipients').textContent = UI.formatNumber(recipients);
    $('#s-segments').textContent = analysis.segments;
    $('#s-encoding').textContent = analysis.encoding === 'gsm' ? 'GSM-7' : 'Unicode';
    $('#s-units').textContent = UI.formatNumber(units);

    /* animate meter progress bar */
    var progress = $('#c-meter-progress');
    if (progress) {
      var pct = analysis.capacity > 0
        ? Math.min(Math.round((analysis.length / analysis.capacity) * 100), 100)
        : 0;
      progress.style.width = pct + '%';
      progress.classList.toggle('warn', pct >= 90);
    }

    var after = balance - units;
    $('#s-after').textContent = UI.formatNumber(after);
    $('#s-after').parentElement.classList.toggle('warn', after < 0);

    var warning = $('#s-warning');
    if (units > balance) {
      warning.hidden = false;
      warning.innerHTML = 'This send needs ' + UI.formatNumber(units) + ' credits and you have ' +
        UI.formatNumber(balance) + '. <a href="#billing">Add credits</a>.';
    } else if (analysis.encoding === 'unicode' && analysis.length > 0) {
      warning.hidden = false;
      warning.textContent = 'Unicode text fits ' + Segments.LIMITS.unicode.single +
        ' characters in the first segment, so this costs more than Latin text of the same length.';
    } else {
      warning.hidden = true;
    }
  }

  async function send() {
    var button = $('#c-send');
    var message = $('#c-message').value.trim();
    if (!message) return UI.toast('Write a message first.', 'bad');

    var schedule = $('#c-schedule').value ? $('#c-schedule').value.replace('T', ' ').slice(0, 16) : null;
    var body = {
      sender_id: $('#c-sender').value,
      message: message,
      schedule_time: schedule
    };

    var endpoint;
    if (state.mode === 'group') {
      endpoint = '/app/campaign';
      body.group_uid = $('#c-group').value;
      if (!body.group_uid) return UI.toast('Pick a contact group.', 'bad');
    } else {
      endpoint = '/app/send';
      body.recipients = $('#c-recipients').value;
      if (!body.recipients.trim()) return UI.toast('Add at least one number.', 'bad');
    }

    button.disabled = true;
    button.textContent = 'Sending…';

    try {
      var res = await UI.api(endpoint, { method: 'POST', body: body });
      setBalance(res.data.credits);
      var count = res.data.recipients ? res.data.recipients.length : res.data.contacts;

      UI.toast(
        schedule
          ? 'Scheduled for ' + schedule + ' to ' + UI.formatNumber(count) + ' recipients.'
          : 'Sent to ' + UI.formatNumber(count) + ' recipient' + (count === 1 ? '' : 's') + '.',
        'ok'
      );

      if (res.data.invalid && res.data.invalid.length) {
        UI.toast(res.data.invalid.length + ' number(s) were skipped as invalid.', 'bad');
      }

      $('#c-message').value = '';
      $('#c-recipients').value = '';
      $('#c-schedule').value = '';
      updateSummary();
    } catch (err) {
      UI.toast(err.message, 'bad');
    } finally {
      button.disabled = false;
      button.textContent = 'Send message';
    }
  }

  /* Messages ------------------------------------------------------------ */

  function filterQuery() {
    var params = new URLSearchParams();
    if ($('#f-search').value.trim()) params.set('search', $('#f-search').value.trim());
    if ($('#f-status').value) params.set('status', $('#f-status').value);
    if ($('#f-start').value) params.set('start_date', $('#f-start').value);
    if ($('#f-end').value) params.set('end_date', $('#f-end').value);
    return params;
  }

  async function loadMessages() {
    $('#messages-table').innerHTML = UI.loading;
    var params = filterQuery();
    params.set('page', state.page);

    try {
      var res = await UI.api('/app/messages?' + params.toString());
      var d = res.data;
      state.lastPage = d.last_page;

      if (!d.data.length) {
        $('#messages-table').innerHTML = UI.emptyState(
          'No messages match',
          state.page > 1 ? 'Go back a page.' : 'Send one from the composer and it will appear here.'
        );
        $('#messages-pager').hidden = true;
        return;
      }

      $('#messages-table').innerHTML = table(
        ['Sent', 'To', 'From', 'Message', 'Credits', 'Status'],
        d.data.map(function (m) {
          return [
            UI.formatDate(m.scheduled_at || m.created_at),
            '<span class="num">' + UI.escapeHtml(m.recipient) + '</span>',
            UI.escapeHtml(m.sender_id),
            '<div class="truncate">' + UI.escapeHtml(m.body) + '</div>',
            '<span class="num">' + m.units + '</span>',
            UI.statusPill(m.status)
          ];
        })
      );

      $('#messages-pager').hidden = d.last_page <= 1;
      $('#pager-label').textContent = 'Page ' + d.page + ' of ' + d.last_page +
        ' — ' + UI.formatNumber(d.total) + ' messages';
      $('#pager-prev').disabled = d.page <= 1;
      $('#pager-next').disabled = d.page >= d.last_page;
    } catch (err) {
      $('#messages-table').innerHTML = UI.emptyState('Could not load messages', err.message);
    }
  }

  /* Groups and contacts ------------------------------------------------- */

  async function refreshGroups() {
    try {
      var res = await UI.api('/app/groups');
      state.groups = res.data;
    } catch (err) {
      state.groups = [];
    }
  }

  async function loadGroups() {
    $('#groups-table').innerHTML = UI.loading;
    await refreshGroups();

    if (!state.groups.length) {
      $('#groups-table').innerHTML = UI.emptyState('No groups yet', 'Create one to send to a saved list.');
      $('#contacts-panel').hidden = true;
      return;
    }

    $('#groups-table').innerHTML = table(
      ['Group', 'Contacts', 'Created', ''],
      state.groups.map(function (g) {
        return [
          UI.escapeHtml(g.name),
          '<span class="num">' + UI.formatNumber(g.contacts) + '</span>',
          UI.formatDate(g.created_at),
          '<div class="right">' +
            '<button class="btn btn-ghost" data-group-open="' + UI.escapeHtml(g.provider_uid) + '">Open</button>' +
            '<button class="btn btn-ghost" data-group-delete="' + UI.escapeHtml(g.provider_uid) + '">Delete</button>' +
          '</div>'
        ];
      })
    );

    if (state.activeGroup) openGroup(state.activeGroup);
  }

  async function openGroup(uid) {
    state.activeGroup = uid;
    var group = state.groups.filter(function (g) { return g.provider_uid === uid; })[0];
    if (!group) return;

    $('#contacts-panel').hidden = false;
    $('#contacts-title').textContent = group.name;
    $('#contacts-table').innerHTML = UI.loading;

    try {
      var res = await UI.api('/app/groups/' + uid + '/contacts');
      var rows = (res.data && res.data.data) || [];

      if (!rows.length) {
        $('#contacts-table').innerHTML = UI.emptyState('No contacts in this group', 'Add one or paste a list.');
        return;
      }

      $('#contacts-table').innerHTML = table(
        ['Phone', 'First name', 'Last name', ''],
        rows.map(function (c) {
          var fields = c || {};
          return [
            '<span class="num">' + UI.escapeHtml(fields.PHONE || fields.phone || '') + '</span>',
            UI.escapeHtml(fields.FIRST_NAME || fields.first_name || '—'),
            UI.escapeHtml(fields.LAST_NAME || fields.last_name || '—'),
            '<div class="right"><button class="btn btn-ghost" data-contact-delete="' +
              UI.escapeHtml(fields.uid || '') + '">Remove</button></div>'
          ];
        })
      );
    } catch (err) {
      $('#contacts-table').innerHTML = UI.emptyState('Could not load contacts', err.message);
    }
  }

  /* Sender names -------------------------------------------------------- */

  async function refreshSenders() {
    try {
      var res = await UI.api('/app/senders');
      state.senders = res.data;
      var pending = state.senders.filter(function (s) { return s.status === 'pending'; }).length;
      var tag = $('#nav-senders');
      tag.hidden = !pending;
      tag.textContent = pending;
    } catch (err) {
      state.senders = [];
    }
  }

  async function loadSenders() {
    $('#senders-table').innerHTML = UI.loading;
    await refreshSenders();

    if (!state.senders.length) {
      $('#senders-table').innerHTML = UI.emptyState(
        'No sender names yet',
        'Request one above. Approval usually takes a business day.'
      );
      return;
    }

    $('#senders-table').innerHTML = table(
      ['Name', 'Status', 'Fee', 'Requested', 'Note', ''],
      state.senders.map(function (s) {
        var pill = s.status === 'approved' ? 'pill-ok' : s.status === 'rejected' ? 'pill-bad' : 'pill-wait';
        return [
          '<strong>' + UI.escapeHtml(s.mask) + '</strong>',
          '<span class="pill ' + pill + '">' + UI.escapeHtml(s.status) + '</span>',
          '<span class="num">' + (s.status === 'rejected' ? 'Refunded' : UI.formatMoney(s.fee_amount)) + '</span>',
          UI.formatDate(s.created_at),
          UI.escapeHtml(s.note || '—'),
          '<div class="right"><button class="btn btn-ghost" data-sender-delete="' + s.id + '">Remove</button></div>'
        ];
      })
    );
  }

  /* Tokens -------------------------------------------------------------- */

  async function loadTokens() {
    $('#tokens-table').innerHTML = UI.loading;
    $('#token-sample').textContent =
      'curl -X POST ' + location.origin + '/api/v3/sms/send \\\n' +
      "  -H 'Authorization: Bearer YOUR_TOKEN' \\\n" +
      "  -H 'Content-Type: application/json' \\\n" +
      "  -H 'Accept: application/json' \\\n" +
      "  -d '{\n" +
      '    "recipient": "94710000000",\n' +
      '    "sender_id": "' + (state.senders[0] ? state.senders[0].mask : 'YourBrand') + '",\n' +
      '    "type": "plain",\n' +
      '    "message": "Your order is on the way."\n' +
      "  }'";

    try {
      var res = await UI.api('/app/tokens');
      if (!res.data.length) {
        $('#tokens-table').innerHTML = UI.emptyState('No tokens yet', 'Create one to send from your own code.');
        return;
      }
      $('#tokens-table').innerHTML = table(
        ['Name', 'Token', 'Last used', 'Created', ''],
        res.data.map(function (t) {
          return [
            UI.escapeHtml(t.name),
            '<span class="num">' + t.id + '|' + UI.escapeHtml(t.prefix) + '…</span>',
            t.last_used_at ? UI.formatDate(t.last_used_at) : 'Never',
            UI.formatDate(t.created_at),
            '<div class="right"><button class="btn btn-ghost" data-token-revoke="' + t.id + '">Revoke</button></div>'
          ];
        })
      );
    } catch (err) {
      $('#tokens-table').innerHTML = UI.emptyState('Could not load tokens', err.message);
    }
  }

  /* Billing ------------------------------------------------------------- */

  async function loadBilling() {
    $('#ledger-table').innerHTML = UI.loading;
    updateTopupTotal();
    updatePaypalTotal();
    initPaypalButtons();

    try {
      var res = await UI.api('/app/transactions');
      if (!res.data.length) {
        $('#ledger-table').innerHTML = UI.emptyState('No movements yet', 'Top-ups and debits will be listed here.');
        return;
      }
      $('#ledger-table').innerHTML = table(
        ['When', 'Type', 'Credits', 'Balance after', 'Note'],
        res.data.map(function (t) {
          var sign = t.units > 0 ? '+' : '';
          var colour = t.units > 0 ? 'var(--jade-deep)' : 'var(--ink)';
          return [
            UI.formatDate(t.created_at),
            '<span class="pill pill-neutral">' + UI.escapeHtml(t.type) + '</span>',
            '<span class="num" style="color:' + colour + '">' + sign + UI.formatNumber(t.units) + '</span>',
            '<span class="num">' + UI.formatNumber(t.balance_after) + '</span>',
            UI.escapeHtml(t.note || '—')
          ];
        })
      );
    } catch (err) {
      $('#ledger-table').innerHTML = UI.emptyState('Could not load the ledger', err.message);
    }
  }

  function updateTopupTotal() {
    var units = Number($('#b-units').value || 0);
    $('#b-total-line').textContent = 'At the flat rate of ' + UI.formatMoney(state.rate) +
      ' per SMS, that is ' + UI.formatMoney(units * state.rate) + '.';
  }

  function updatePaypalTotal() {
    var units = Number($('#pp-units').value || 0);
    var lkr = units * state.rate;
    var usd = lkr / Math.max(state.paypalUsdRate, 1);
    $('#pp-total-line').textContent = UI.formatMoney(lkr) + ' ≈ $' + usd.toFixed(2) + ' USD';
  }

  function initPaypalButtons() {
    var host = $('#paypal-buttons');
    var notice = $('#paypal-notice');

    if (typeof window.paypal === 'undefined') {
      notice.innerHTML = '<div class="notice notice-warn">PayPal is not set up yet. Ask an admin to add PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET.</div>';
      return;
    }
    notice.innerHTML = '';

    if (state.paypalRendered) return;
    state.paypalRendered = true;

    window.paypal.Buttons({
      style: { layout: 'horizontal', height: 40, tagline: false },

      createOrder: function () {
        var units = Number($('#pp-units').value || 0);
        if (units < 100) {
          UI.toast('Buy at least 100 credits.', 'bad');
          return Promise.reject(new Error('units too low'));
        }
        return UI.api('/app/paypal/orders', { method: 'POST', body: { units: units } })
          .then(function (res) { return res.data.order_id; })
          .catch(function (err) { UI.toast(err.message, 'bad'); throw err; });
      },

      onApprove: function (data) {
        return UI.api('/app/paypal/orders/' + data.orderID + '/capture', { method: 'POST' })
          .then(function (res) {
            UI.toast(res.message, 'ok');
            setBalance(res.data.credits);
            loadBilling();
          })
          .catch(function (err) { UI.toast(err.message, 'bad'); });
      },

      onError: function () {
        UI.toast('PayPal could not complete that payment.', 'bad');
      }
    }).render(host);
  }

  /* Account ------------------------------------------------------------- */

  function fillAccount(me) {
    $('#p-name').value = me.name || '';
    $('#p-company').value = me.company || '';
    $('#p-phone').value = me.phone || '';
    $('#p-email').value = me.email || '';
  }

  /* Table helper -------------------------------------------------------- */

  function table(headers, rows) {
    return '<table class="table"><thead><tr>' +
      headers.map(function (h) { return '<th>' + h + '</th>'; }).join('') +
      '</tr></thead><tbody>' +
      rows.map(function (cells) {
        return '<tr>' + cells.map(function (c) { return '<td>' + c + '</td>'; }).join('') + '</tr>';
      }).join('') +
      '</tbody></table>';
  }

  /* Events -------------------------------------------------------------- */

  function wire() {
    $('#signout').onclick = async function () {
      await UI.api('/auth/logout', { method: 'POST' });
      location.href = '/';
    };

    // Composer
    ['#c-message', '#c-recipients'].forEach(function (selector) {
      $(selector).addEventListener('input', updateSummary);
    });
    $('#c-group').addEventListener('change', updateSummary);
    $('#c-send').onclick = send;

    $$('.tabs button').forEach(function (tab) {
      tab.onclick = function () {
        state.mode = tab.dataset.mode;
        $$('.tabs button').forEach(function (b) {
          b.setAttribute('aria-selected', String(b === tab));
        });
        $('#field-numbers').hidden = state.mode !== 'numbers';
        $('#field-group').hidden = state.mode !== 'group';
        updateSummary();
      };
    });

    // Messages
    $('#f-apply').onclick = function () { state.page = 1; loadMessages(); };
    $('#f-clear').onclick = function () {
      ['#f-search', '#f-status', '#f-start', '#f-end'].forEach(function (s) { $(s).value = ''; });
      state.page = 1;
      loadMessages();
    };
    $('#f-search').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { state.page = 1; loadMessages(); }
    });
    $('#pager-prev').onclick = function () { state.page = Math.max(1, state.page - 1); loadMessages(); };
    $('#pager-next').onclick = function () { state.page = Math.min(state.lastPage, state.page + 1); loadMessages(); };
    $('#m-export').onclick = function () { location.href = '/app/messages/export?' + filterQuery().toString(); };
    $('#m-sync').onclick = async function () {
      var button = this;
      button.disabled = true;
      button.textContent = 'Checking…';
      try {
        var res = await UI.api('/app/messages/sync', { method: 'POST' });
        UI.toast(res.data.updated + ' of ' + res.data.checked + ' updated.', 'ok');
        loadMessages();
      } catch (err) {
        UI.toast(err.message, 'bad');
      } finally {
        button.disabled = false;
        button.textContent = 'Refresh statuses';
      }
    };

    // Groups
    $('#g-new').onclick = async function () {
      var result = await UI.modal({
        title: 'New contact group',
        sub: 'Give it a name you will recognise in the composer.',
        confirm: 'Create group',
        body: '<label class="field"><span>Group name</span><input class="input" data-name="name" placeholder="Colombo customers"></label>'
      });
      if (!result || !result.name) return;
      try {
        await UI.api('/app/groups', { method: 'POST', body: { name: result.name } });
        UI.toast('Group created.', 'ok');
        loadGroups();
      } catch (err) {
        UI.toast(err.message, 'bad');
      }
    };

    $('#c-add').onclick = async function () {
      if (!state.activeGroup) return;
      var result = await UI.modal({
        title: 'Add contact',
        confirm: 'Add contact',
        body: '<label class="field"><span>Phone</span><input class="input" data-name="PHONE" placeholder="0712345678"></label>' +
              '<div class="row">' +
              '<label class="field"><span>First name</span><input class="input" data-name="FIRST_NAME"></label>' +
              '<label class="field"><span>Last name</span><input class="input" data-name="LAST_NAME"></label>' +
              '</div>'
      });
      if (!result || !result.PHONE) return;
      try {
        await UI.api('/app/groups/' + state.activeGroup + '/contacts', { method: 'POST', body: result });
        UI.toast('Contact added.', 'ok');
        loadGroups();
      } catch (err) {
        UI.toast(err.message, 'bad');
      }
    };

    $('#c-import').onclick = async function () {
      if (!state.activeGroup) return;
      var result = await UI.modal({
        title: 'Paste a list',
        sub: 'One contact per line: phone, first name, last name. Up to 500 at a time.',
        confirm: 'Import contacts',
        body: '<label class="field"><span>Contacts</span><textarea class="textarea" data-name="rows" placeholder="0712345678, Nimal, Perera&#10;0771234567, Kamala, Silva"></textarea></label>'
      });
      if (!result || !result.rows) return;
      try {
        var res = await UI.api('/app/groups/' + state.activeGroup + '/import', {
          method: 'POST', body: { rows: result.rows }
        });
        UI.toast(res.data.added + ' added, ' + res.data.skipped.length + ' skipped.', 'ok');
        loadGroups();
      } catch (err) {
        UI.toast(err.message, 'bad');
      }
    };

    // Sender names
    $('#sender-add').onclick = async function () {
      var mask = $('#sender-mask').value.trim();
      if (!mask) return UI.toast('Enter a sender name.', 'bad');
      try {
        var res = await UI.api('/app/senders', { method: 'POST', body: { mask: mask } });
        $('#sender-mask').value = '';
        if (res.data && typeof res.data.credits === 'number') setBalance(res.data.credits);
        UI.toast('Submitted for approval.', 'ok');
        loadSenders();
      } catch (err) {
        UI.toast(err.message, 'bad');
      }
    };

    // Tokens
    $('#t-new').onclick = async function () {
      var result = await UI.modal({
        title: 'Create an API token',
        sub: 'The token is shown once. Store it somewhere safe.',
        confirm: 'Create token',
        body: '<label class="field"><span>What is it for?</span><input class="input" data-name="name" placeholder="Checkout server"></label>'
      });
      if (!result) return;
      try {
        var res = await UI.api('/app/tokens', { method: 'POST', body: { name: result.name } });
        $('#token-reveal').innerHTML =
          '<div class="token-reveal"><p>Copy this token now. It will not be shown again.</p>' +
          '<div class="value"><code id="new-token">' + UI.escapeHtml(res.data.token) + '</code>' +
          '<button class="btn btn-sm" id="copy-token">Copy</button></div></div>';
        $('#copy-token').onclick = function () {
          navigator.clipboard.writeText(res.data.token).then(function () {
            UI.toast('Token copied.', 'ok');
          });
        };
        loadTokens();
      } catch (err) {
        UI.toast(err.message, 'bad');
      }
    };

    // Billing
    $('#b-units').addEventListener('input', updateTopupTotal);
    $('#pp-units').addEventListener('input', updatePaypalTotal);
    $('#b-request').onclick = async function () {
      try {
        var body = { units: Number($('#b-units').value), note: $('#b-note').value };
        var res = await UI.api('/app/topup-request', { method: 'POST', body: body });
        UI.toast(res.message, 'ok');
        loadBilling();
      } catch (err) {
        UI.toast(err.message, 'bad');
      }
    };

    // Account
    $('#p-save').onclick = async function () {
      try {
        await UI.api('/app/profile', {
          method: 'PATCH',
          body: { name: $('#p-name').value, company: $('#p-company').value, phone: $('#p-phone').value }
        });
        $('#who-name').textContent = $('#p-name').value;
        UI.toast('Profile saved.', 'ok');
      } catch (err) {
        UI.toast(err.message, 'bad');
      }
    };

    $('#p-password').onclick = async function () {
      try {
        await UI.api('/app/password', {
          method: 'POST',
          body: { current_password: $('#p-current').value, new_password: $('#p-new').value }
        });
        $('#p-current').value = '';
        $('#p-new').value = '';
        UI.toast('Password changed.', 'ok');
      } catch (err) {
        UI.toast(err.message, 'bad');
      }
    };

    // Delegated row actions
    document.addEventListener('click', async function (event) {
      var open = event.target.closest('[data-group-open]');
      if (open) return openGroup(open.dataset.groupOpen);

      var removeGroup = event.target.closest('[data-group-delete]');
      if (removeGroup) {
        var confirmed = await UI.modal({
          title: 'Delete this group?',
          sub: 'The contacts in it are deleted too. This cannot be undone.',
          confirm: 'Delete group',
          danger: true
        });
        if (!confirmed) return;
        try {
          await UI.api('/app/groups/' + removeGroup.dataset.groupDelete, { method: 'DELETE' });
          if (state.activeGroup === removeGroup.dataset.groupDelete) {
            state.activeGroup = null;
            $('#contacts-panel').hidden = true;
          }
          UI.toast('Group deleted.', 'ok');
          loadGroups();
        } catch (err) {
          UI.toast(err.message, 'bad');
        }
        return;
      }

      var removeContact = event.target.closest('[data-contact-delete]');
      if (removeContact && state.activeGroup) {
        try {
          await UI.api('/app/groups/' + state.activeGroup + '/contacts/' + removeContact.dataset.contactDelete, {
            method: 'DELETE'
          });
          UI.toast('Contact removed.', 'ok');
          loadGroups();
        } catch (err) {
          UI.toast(err.message, 'bad');
        }
        return;
      }

      var removeSender = event.target.closest('[data-sender-delete]');
      if (removeSender) {
        try {
          await UI.api('/app/senders/' + removeSender.dataset.senderDelete, { method: 'DELETE' });
          loadSenders();
        } catch (err) {
          UI.toast(err.message, 'bad');
        }
        return;
      }

      var revoke = event.target.closest('[data-token-revoke]');
      if (revoke) {
        var ok = await UI.modal({
          title: 'Revoke this token?',
          sub: 'Any application using it stops sending immediately.',
          confirm: 'Revoke token',
          danger: true
        });
        if (!ok) return;
        try {
          await UI.api('/app/tokens/' + revoke.dataset.tokenRevoke, { method: 'DELETE' });
          UI.toast('Token revoked.', 'ok');
          loadTokens();
        } catch (err) {
          UI.toast(err.message, 'bad');
        }
      }
    });
  }

  boot();
})();
