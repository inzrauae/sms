/* Admin console controller. */
(function () {
  'use strict';

  var $ = UI.$, $$ = UI.$$;
  var state = { settings: {}, page: 1, lastPage: 1 };

  function initials(name) {
    var parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
  }

  async function boot() {
    try {
      var session = await UI.api('/auth/session');
      if (session.data.role !== 'admin') { location.href = '/dashboard'; return; }
      $('#who-name').textContent = session.data.name;
      $('#who-avatar').textContent = initials(session.data.name);
    } catch (err) {
      return;
    }

    UI.router({
      overview: { title: 'Overview', sub: 'The health of the whole portal.' },
      users: { title: 'Customers', sub: 'Balances, rates and access.' },
      senders: { title: 'Sender approvals', sub: 'Names customers want to send from.' },
      requests: { title: 'Credit requests', sub: 'Customers waiting on credits.' },
      traffic: { title: 'All traffic', sub: 'Every message across every account.' },
      settings: { title: 'Portal settings', sub: 'Branding and defaults.' }
    }, load);

    wire();
  }

  function load(view) {
    ({
      overview: loadOverview,
      users: loadUsers,
      senders: loadSenders,
      requests: loadRequests,
      traffic: loadTraffic,
      settings: loadSettings
    })[view]();
  }

  function table(headers, rows) {
    return '<table class="table"><thead><tr>' +
      headers.map(function (h) { return '<th>' + h + '</th>'; }).join('') +
      '</tr></thead><tbody>' +
      rows.map(function (cells) {
        return '<tr>' + cells.map(function (c) { return '<td>' + c + '</td>'; }).join('') + '</tr>';
      }).join('') + '</tbody></table>';
  }

  /* Overview ------------------------------------------------------------ */

  async function loadOverview() {
    try {
      var res = await UI.api('/admin/overview');
      var d = res.data;
      state.settings = d.settings;

      $('#m-tenants').textContent = UI.formatNumber(d.tenants);
      $('#m-pending-senders').textContent = d.pending_senders
        ? d.pending_senders + ' sender name(s) to review'
        : 'Nothing to review';
      $('#m-sold').textContent = UI.formatNumber(d.credits_sold);
      $('#m-traffic').textContent = UI.formatNumber(d.traffic.messages);
      $('#m-units').textContent = UI.formatNumber(d.traffic.units) + ' credits used';
      $('#m-revenue').textContent = UI.formatMoney(d.traffic.revenue);

      $('#upstream').textContent = d.upstream_units == null ? '—' : UI.formatNumber(d.upstream_units);
      $('#upstream').className = d.upstream_units != null && d.upstream_units < d.credits_sold ? 'low' : '';

      $('#nav-senders').hidden = !d.pending_senders;
      $('#nav-senders').textContent = d.pending_senders;
      $('#nav-requests').hidden = !d.topup_requests;
      $('#nav-requests').textContent = d.topup_requests;

      drawStatusBar(d.traffic);
      reconcile(d);
      queue(d);
    } catch (err) {
      UI.toast(err.message, 'bad');
    }
  }

  /* Icon paths sourced from Heroicons (heroicons.com), MIT licensed, 20px solid set. */
  var ICON_CHECK = '<path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"/>';
  var ICON_CLOCK = '<path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z"/>';
  var ICON_X = '<path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/>';

  function drawStatusBar(traffic) {
    var delivered = Number(traffic.delivered || 0);
    var pending = Number(traffic.pending || 0);
    var failed = Number(traffic.failed || 0);
    var total = delivered + pending + failed;

    var bar = $('#status-bar');
    var legend = $('#status-legend');

    if (!total) {
      bar.innerHTML = '';
      bar.setAttribute('aria-hidden', 'true');
      legend.innerHTML = '<li class="status-legend-item"><span>No messages sent this month yet.</span></li>';
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

  // The number that matters most to a reseller: have you sold more credits
  // than you actually hold at the gateway?
  function reconcile(d) {
    var host = $('#reconcile');
    if (d.upstream_error) {
      host.innerHTML = '<div class="notice notice-warn">Could not read the gateway balance: ' +
        UI.escapeHtml(d.upstream_error) + '</div>';
      return;
    }
    if (d.upstream_units == null) { host.innerHTML = ''; return; }

    var margin = d.upstream_units - d.credits_sold;
    if (margin < 0) {
      host.innerHTML = '<div class="notice notice-warn">You have sold ' +
        UI.formatNumber(Math.abs(margin)) + ' more credits than you hold at the gateway. ' +
        'Top up upstream before customers try to spend them.</div>';
    } else {
      host.innerHTML = '<div class="notice notice-info">Gateway balance covers every credit sold, with ' +
        UI.formatNumber(margin) + ' to spare.</div>';
    }
  }

  function queue(d) {
    var items = [];
    if (d.pending_senders) {
      items.push(['Sender names awaiting approval', d.pending_senders, '#senders']);
    }
    if (d.topup_requests) {
      items.push(['Customers waiting on credits', d.topup_requests, '#requests']);
    }
    if (!items.length) {
      $('#queue').innerHTML = UI.emptyState('Nothing waiting', 'Approvals and credit requests land here.');
      return;
    }
    $('#queue').innerHTML = table(['Task', 'Count', ''], items.map(function (item) {
      return [
        item[0],
        '<span class="num">' + item[1] + '</span>',
        '<div class="right"><a class="btn btn-sm btn-line" href="' + item[2] + '">Review</a></div>'
      ];
    }));
  }

  /* Customers ----------------------------------------------------------- */

  async function loadUsers() {
    $('#users-table').innerHTML = UI.loading;
    var search = $('#u-search').value.trim();

    try {
      var res = await UI.api('/admin/users' + (search ? '?search=' + encodeURIComponent(search) : ''));
      if (!res.data.length) {
        $('#users-table').innerHTML = UI.emptyState('No customers match', 'Try a different search.');
        return;
      }

      $('#users-table').innerHTML = table(
        ['Customer', 'Credits', 'Rate', 'Sent', 'Status', ''],
        res.data.map(function (u) {
          var statusPill = u.status === 'active' ? 'pill-ok' : 'pill-bad';
          return [
            '<strong>' + UI.escapeHtml(u.name) + '</strong>' +
              (u.company ? '<div style="font-size:0.84rem;color:var(--muted)">' + UI.escapeHtml(u.company) + '</div>' : '') +
              '<div style="font-size:0.84rem;color:var(--muted)">' + UI.escapeHtml(u.email) + '</div>',
            '<span class="num">' + UI.formatNumber(u.credits) + '</span>',
            '<span class="num">' + UI.formatMoney(u.rate) + '</span>',
            '<span class="num">' + UI.formatNumber(u.messages) + '</span>',
            '<span class="pill ' + statusPill + '">' + UI.escapeHtml(u.status) + '</span>' +
              (u.role === 'admin' ? ' <span class="pill pill-neutral">admin</span>' : ''),
            '<div class="right">' +
              '<button class="btn btn-ghost" data-credit="' + u.id + '" data-name="' + UI.escapeHtml(u.name) + '">Credits</button>' +
              '<button class="btn btn-ghost" data-edit="' + u.id + '" data-rate="' + u.rate +
                '" data-status="' + u.status + '">Edit</button>' +
            '</div>'
          ];
        })
      );
    } catch (err) {
      $('#users-table').innerHTML = UI.emptyState('Could not load customers', err.message);
    }
  }

  /* Sender approvals ---------------------------------------------------- */

  async function loadSenders() {
    $('#senders-table').innerHTML = UI.loading;
    try {
      var res = await UI.api('/admin/senders');
      if (!res.data.length) {
        $('#senders-table').innerHTML = UI.emptyState('No sender names yet', 'Requests appear here for review.');
        return;
      }

      $('#senders-table').innerHTML = table(
        ['Name', 'Customer', 'Fee', 'Requested', 'Status', ''],
        res.data.map(function (s) {
          var pill = s.status === 'approved' ? 'pill-ok' : s.status === 'rejected' ? 'pill-bad' : 'pill-wait';
          var actions = s.status === 'pending'
            ? '<button class="btn btn-sm" data-decide="' + s.id + '" data-decision="approved">Approve</button> ' +
              '<button class="btn btn-sm btn-line" data-decide="' + s.id + '" data-decision="rejected">Reject (refund)</button>'
            : '<button class="btn btn-ghost" data-decide="' + s.id + '" data-decision="' +
              (s.status === 'approved' ? 'rejected' : 'approved') + '">' +
              (s.status === 'approved' ? 'Revoke' : 'Approve') + '</button>';

          return [
            '<strong>' + UI.escapeHtml(s.mask) + '</strong>',
            UI.escapeHtml(s.company || s.name) + '<div style="font-size:0.84rem;color:var(--muted)">' +
              UI.escapeHtml(s.email) + '</div>',
            '<span class="num">' + (s.status === 'rejected' ? 'Refunded' : UI.formatMoney(s.fee_amount)) + '</span>',
            UI.formatDate(s.created_at),
            '<span class="pill ' + pill + '">' + UI.escapeHtml(s.status) + '</span>',
            '<div class="right">' + actions + '</div>'
          ];
        })
      );
    } catch (err) {
      $('#senders-table').innerHTML = UI.emptyState('Could not load sender names', err.message);
    }
  }

  /* Credit requests ----------------------------------------------------- */

  async function loadRequests() {
    $('#requests-table').innerHTML = UI.loading;
    try {
      var res = await UI.api('/admin/requests');
      if (!res.data.length) {
        $('#requests-table').innerHTML = UI.emptyState('No open requests', 'Customers can request credits from their dashboard.');
        return;
      }

      $('#requests-table').innerHTML = table(
        ['Customer', 'Requested', 'Value', 'Balance now', 'Note', ''],
        res.data.map(function (r) {
          return [
            '<strong>' + UI.escapeHtml(r.name) + '</strong>' +
              '<div style="font-size:0.84rem;color:var(--muted)">' + UI.escapeHtml(r.email) + '</div>',
            '<span class="num">' + UI.formatNumber(r.units) + '</span>',
            '<span class="num">' + UI.formatMoney(r.amount) + '</span>',
            '<span class="num">' + UI.formatNumber(r.credits) + '</span>',
            UI.escapeHtml(r.note || '—'),
            '<div class="right"><button class="btn btn-sm" data-credit="' + r.user_id +
              '" data-name="' + UI.escapeHtml(r.name) + '" data-units="' + r.units + '">Add credits</button></div>'
          ];
        })
      );
    } catch (err) {
      $('#requests-table').innerHTML = UI.emptyState('Could not load requests', err.message);
    }
  }

  /* Traffic ------------------------------------------------------------- */

  async function loadTraffic() {
    $('#traffic-table').innerHTML = UI.loading;
    try {
      var res = await UI.api('/admin/messages?page=' + state.page);
      var d = res.data;
      state.lastPage = Math.max(Math.ceil(d.total / d.per_page), 1);

      if (!d.data.length) {
        $('#traffic-table').innerHTML = UI.emptyState('No messages yet', 'Traffic across all accounts appears here.');
        $('#traffic-pager').hidden = true;
        return;
      }

      $('#traffic-table').innerHTML = table(
        ['Sent', 'Customer', 'To', 'From', 'Message', 'Via', 'Status'],
        d.data.map(function (m) {
          return [
            UI.formatDate(m.created_at),
            UI.escapeHtml(m.user_name),
            '<span class="num">' + UI.escapeHtml(m.recipient) + '</span>',
            UI.escapeHtml(m.sender_id),
            '<div class="truncate" style="max-width:240px">' + UI.escapeHtml(m.body) + '</div>',
            '<span class="pill pill-neutral">' + UI.escapeHtml(m.source) + '</span>',
            UI.statusPill(m.status)
          ];
        })
      );

      $('#traffic-pager').hidden = state.lastPage <= 1;
      $('#pager-label').textContent = 'Page ' + d.page + ' of ' + state.lastPage +
        ' — ' + UI.formatNumber(d.total) + ' messages';
      $('#pager-prev').disabled = d.page <= 1;
      $('#pager-next').disabled = d.page >= state.lastPage;
    } catch (err) {
      $('#traffic-table').innerHTML = UI.emptyState('Could not load traffic', err.message);
    }
  }

  /* Settings ------------------------------------------------------------ */

  async function loadSettings() {
    try {
      var res = await UI.api('/admin/overview');
      var s = res.data.settings;
      $('#s-brand').value = s.brand_name || '';
      $('#s-support').value = s.support_email || '';
      $('#s-rate').value = s.default_rate || '';
      $('#s-bonus').value = s.signup_bonus || '';
      $('#s-sender-fee').value = s.sender_id_fee || '';
      $('#s-paypal-rate').value = s.paypal_usd_rate || '';
    } catch (err) {
      UI.toast(err.message, 'bad');
    }
  }

  /* Events -------------------------------------------------------------- */

  function wire() {
    $('#signout').onclick = async function () {
      await UI.api('/auth/logout', { method: 'POST' });
      location.href = '/';
    };

    $('#u-apply').onclick = loadUsers;
    $('#u-search').addEventListener('keydown', function (e) { if (e.key === 'Enter') loadUsers(); });

    $('#pager-prev').onclick = function () { state.page = Math.max(1, state.page - 1); loadTraffic(); };
    $('#pager-next').onclick = function () { state.page = Math.min(state.lastPage, state.page + 1); loadTraffic(); };

    $('#s-save').onclick = async function () {
      try {
        await UI.api('/admin/settings', {
          method: 'POST',
          body: {
            brand_name: $('#s-brand').value,
            support_email: $('#s-support').value,
            default_rate: $('#s-rate').value,
            signup_bonus: $('#s-bonus').value,
            sender_id_fee: $('#s-sender-fee').value,
            paypal_usd_rate: $('#s-paypal-rate').value
          }
        });
        UI.toast('Settings saved.', 'ok');
      } catch (err) {
        UI.toast(err.message, 'bad');
      }
    };

    document.addEventListener('click', async function (event) {
      var credit = event.target.closest('[data-credit]');
      if (credit) {
        var result = await UI.modal({
          title: 'Adjust credits',
          sub: 'For ' + credit.dataset.name + '. Use a negative number to take credits back.',
          confirm: 'Apply',
          body: '<label class="field"><span>Credits</span><input class="input num" type="number" data-name="units" value="' +
                  (credit.dataset.units || 1000) + '"></label>' +
                '<label class="field"><span>Note</span><input class="input" data-name="note" placeholder="Bank transfer 12 Sep"></label>'
        });
        if (!result) return;
        try {
          await UI.api('/admin/users/' + credit.dataset.credit + '/credits', {
            method: 'POST',
            body: { units: Number(result.units), note: result.note }
          });
          UI.toast('Credits applied.', 'ok');
          load(location.hash.replace('#', '') || 'overview');
        } catch (err) {
          UI.toast(err.message, 'bad');
        }
        return;
      }

      var edit = event.target.closest('[data-edit]');
      if (edit) {
        var edited = await UI.modal({
          title: 'Edit customer',
          confirm: 'Save changes',
          body: '<label class="field"><span>Rate per SMS</span><input class="input num" type="number" step="0.01" data-name="rate" value="' +
                  edit.dataset.rate + '"></label>' +
                '<label class="field"><span>Status</span><select class="select" data-name="status">' +
                  '<option value="active"' + (edit.dataset.status === 'active' ? ' selected' : '') + '>Active</option>' +
                  '<option value="suspended"' + (edit.dataset.status === 'suspended' ? ' selected' : '') + '>Suspended</option>' +
                '</select></label>'
        });
        if (!edited) return;
        try {
          await UI.api('/admin/users/' + edit.dataset.edit, {
            method: 'PATCH',
            body: { rate: Number(edited.rate), status: edited.status }
          });
          UI.toast('Customer updated.', 'ok');
          loadUsers();
        } catch (err) {
          UI.toast(err.message, 'bad');
        }
        return;
      }

      var decide = event.target.closest('[data-decide]');
      if (decide) {
        try {
          await UI.api('/admin/senders/' + decide.dataset.decide + '/decision', {
            method: 'POST',
            body: { decision: decide.dataset.decision }
          });
          UI.toast('Sender name ' + decide.dataset.decision + '.', 'ok');
          loadSenders();
        } catch (err) {
          UI.toast(err.message, 'bad');
        }
      }
    });
  }

  boot();
})();
