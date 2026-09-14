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

  /* Icon paths sourced from Heroicons (heroicons.com), MIT licensed, 20px solid set. */
  var ICON_CHECK = '<path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"/>';
  var ICON_CLOCK = '<path fill-rule="evenodd" clip-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z"/>';
  var ICON_PLANE = '<path d="M3.105 2.288a.75.75 0 0 0-.826.95l1.414 4.926A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.897 28.897 0 0 0 15.293-7.155.75.75 0 0 0 0-1.114A28.897 28.897 0 0 0 3.105 2.288Z"/>';
  var ICON_CALENDAR = '<path fill-rule="evenodd" clip-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z"/>';
  var ICON_X = '<path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/>';

  var STATUS_ICON = {
    Delivered: ICON_CHECK,
    Sent: ICON_PLANE,
    Queued: ICON_CLOCK,
    Pending: ICON_CLOCK,
    Scheduled: ICON_CALENDAR,
    Failed: ICON_X,
    Rejected: ICON_X,
    Undelivered: ICON_X,
    Expired: ICON_X
  };

  function statusPill(status) {
    var cls = STATUS_CLASS[status] || 'pill-neutral';
    var path = STATUS_ICON[status] || ICON_CLOCK;
    var icon = '<svg class="pill-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">' + path + '</svg>';
    return '<span class="pill ' + cls + '">' + icon + escapeHtml(status || 'Unknown') + '</span>';
  }

  var EMPTY_ICON = '<svg class="empty-icon" viewBox="0 0 120 120" fill="none" aria-hidden="true">' +
    '<circle cx="60" cy="60" r="54" fill="#e3f1ee"/>' +
    '<circle cx="96" cy="28" r="7" fill="#fbbf24"/>' +
    '<circle cx="22" cy="86" r="5" fill="#6366f1"/>' +
    '<circle cx="92" cy="92" r="4.5" fill="#0e7c6b"/>' +
    '<path d="M32 58h56l-9 30H41l-9-30Z" fill="#ffffff" stroke="#0e7c6b" stroke-width="3.5" stroke-linejoin="round"/>' +
    '<path d="M32 58 43 30h34l11 28" fill="none" stroke="#0e7c6b" stroke-width="3.5" stroke-linejoin="round"/>' +
    '<path d="M32 58h56" stroke="#0e7c6b" stroke-width="3.5"/>' +
    '<circle cx="60" cy="58" r="4.5" fill="#0e7c6b"/>' +
    '</svg>';

  var ERROR_ICON = '<svg class="empty-icon" viewBox="0 0 120 120" fill="none" aria-hidden="true">' +
    '<circle cx="60" cy="60" r="54" fill="#f8e6e3"/>' +
    '<circle cx="94" cy="30" r="6" fill="#f2b8ac"/>' +
    '<circle cx="24" cy="88" r="4.5" fill="#a3342a" opacity="0.5"/>' +
    '<path d="M60 32 96 92H24L60 32Z" fill="#ffffff" stroke="#a3342a" stroke-width="3.5" stroke-linejoin="round"/>' +
    '<path d="M60 54v16" stroke="#a3342a" stroke-width="4.5" stroke-linecap="round"/>' +
    '<circle cx="60" cy="78" r="2.8" fill="#a3342a"/>' +
    '</svg>';

  function emptyState(title, hint) {
    var isError = /could not|error|failed/i.test(title);
    var icon = isError ? ERROR_ICON : EMPTY_ICON;
    return '<div class="empty">' + icon + '<strong>' + escapeHtml(title) + '</strong>' +
      (hint ? '<span>' + escapeHtml(hint) + '</span>' : '') + '</div>';
  }

  var SKELETON_ROW = '<div class="skeleton-row">' +
    '<span class="skeleton-bar w-25"></span>' +
    '<span class="skeleton-bar w-45"></span>' +
    '<span class="skeleton-bar w-15"></span>' +
    '<span class="skeleton-bar w-10"></span>' +
    '</div>';
  var loading = '<div class="skeleton" aria-hidden="true">' +
    SKELETON_ROW + SKELETON_ROW + SKELETON_ROW + SKELETON_ROW +
    '</div>';

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
      if (section) {
        section.hidden = false;
        section.classList.remove('view-enter');
        void section.offsetWidth; /* restart the animation on repeat visits */
        section.classList.add('view-enter');
      }

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
