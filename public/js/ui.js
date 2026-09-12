/* Shared helpers for the dashboard and admin console. */
(function (global) {
  'use strict';

  var $ = function (selector, root) { return (root || document).querySelector(selector); };
  var $$ = function (selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  };

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* Network ------------------------------------------------------------- */

  async function api(path, options) {
    options = options || {};
    var config = {
      method: options.method || 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    };
    if (config.method !== 'GET') {
      var meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) config.headers['X-CSRF-TOKEN'] = meta.content;
    }
    if (options.body !== undefined) {
      config.headers['Content-Type'] = 'application/json';
      config.body = JSON.stringify(options.body);
    }

    var response = await fetch(path, config);

    if (response.status === 401) {
      location.href = '/login?next=' + encodeURIComponent(location.pathname);
      throw new Error('Signed out');
    }

    var payload;
    try {
      payload = await response.json();
    } catch (err) {
      throw new Error('The server sent back something unreadable.');
    }

    if (!response.ok || payload.status === 'error') {
      var error = new Error(payload.message || 'That request did not work.');
      error.payload = payload;
      error.status = response.status;
      throw error;
    }
    return payload;
  }

  /* Toast --------------------------------------------------------------- */

  function toast(message, kind) {
    var host = $('#toast');
    if (!host) return;
    var node = document.createElement('div');
    node.className = 'toast' + (kind ? ' toast-' + kind : '');
    node.textContent = message;
    host.appendChild(node);
    setTimeout(function () { node.remove(); }, kind === 'bad' ? 6000 : 3800);
  }

  /* Formatting ---------------------------------------------------------- */

  function formatDate(value) {
    if (!value) return '—';
    var date = new Date(String(value).replace(' ', 'T') + (String(value).length <= 19 ? 'Z' : ''));
    if (isNaN(date)) return value;
    return date.toLocaleString('en-GB', {
      day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false
    });
  }

  function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-LK');
  }

  function formatMoney(value) {
    return 'Rs ' + Number(value || 0).toLocaleString('en-LK', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
  }

  var STATUS_CLASS = {
    Delivered: 'pill-ok',
    Sent: 'pill-wait',
    Queued: 'pill-wait',
    Pending: 'pill-wait',
    Scheduled: 'pill-wait',
    Failed: 'pill-bad',
    Rejected: 'pill-bad',
    Undelivered: 'pill-bad',
    Expired: 'pill-bad'
  };

  function statusPill(status) {
    var cls = STATUS_CLASS[status] || 'pill-neutral';
    return '<span class="pill ' + cls + '">' + escapeHtml(status || 'Unknown') + '</span>';
  }

  function emptyState(title, hint) {
    return '<div class="empty"><strong>' + escapeHtml(title) + '</strong>' + escapeHtml(hint || '') + '</div>';
  }

  var loading = '<div class="loading">Loading…</div>';

  /* Modal --------------------------------------------------------------- */

  function modal(options) {
    return new Promise(function (resolve) {
      var host = $('#modal');
      $('#modal-title').textContent = options.title || '';
      $('#modal-sub').textContent = options.sub || '';
      $('#modal-body').innerHTML = options.body || '';
      $('#modal-ok').textContent = options.confirm || 'Confirm';
      $('#modal-ok').className = 'btn' + (options.danger ? ' btn-danger' : '');
      host.hidden = false;

      var first = $('#modal-body input, #modal-body textarea, #modal-body select');
      if (first) first.focus(); else $('#modal-ok').focus();

      function close(result) {
        host.hidden = true;
        document.removeEventListener('keydown', onKey);
        $('#modal-ok').onclick = null;
        $('#modal-cancel').onclick = null;
        host.onclick = null;
        resolve(result);
      }

      function values() {
        var out = {};
        $$('#modal-body [data-name]').forEach(function (el) { out[el.dataset.name] = el.value; });
        return out;
      }

      function onKey(event) {
        if (event.key === 'Escape') close(null);
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') close(values());
      }

      document.addEventListener('keydown', onKey);
      $('#modal-ok').onclick = function () { close(values()); };
      $('#modal-cancel').onclick = function () { close(null); };
      host.onclick = function (event) { if (event.target === host) close(null); };
    });
  }

  /* Hash router --------------------------------------------------------- */

  function router(views, onChange) {
    function apply() {
      var name = location.hash.replace('#', '') || Object.keys(views)[0];
      if (!views[name]) name = Object.keys(views)[0];

      $$('.view').forEach(function (section) { section.hidden = true; });
      var section = document.getElementById('v-' + name);
      if (section) section.hidden = false;

      $$('[data-nav]').forEach(function (link) {
        if (link.dataset.nav === name) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
      });

      var meta = views[name];
      $('#view-title').textContent = meta.title;
      $('#view-sub').textContent = meta.sub || '';
      onChange(name);
    }

    global.addEventListener('hashchange', apply);
    apply();
  }

  global.UI = {
    $: $, $$: $$,
    api: api,
    toast: toast,
    modal: modal,
    router: router,
    escapeHtml: escapeHtml,
    formatDate: formatDate,
    formatNumber: formatNumber,
    formatMoney: formatMoney,
    statusPill: statusPill,
    emptyState: emptyState,
    loading: loading
  };
})(window);
