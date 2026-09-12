/* Mirror of src/sms.js so the composer can count without a network round
   trip. The server always recounts before billing — this is display only. */
(function (global) {
  'use strict';

  var BASIC =
    '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?' +
    '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
  var EXTENDED = '^{}\\[~]|€';

  var basic = {};
  var extended = {};
  BASIC.split('').forEach(function (c) { basic[c] = true; });
  EXTENDED.split('').forEach(function (c) { extended[c] = true; });

  var LIMITS = {
    gsm: { single: 160, multi: 153 },
    unicode: { single: 70, multi: 67 }
  };

  function analyse(text) {
    var body = text || '';
    var chars = Array.from(body);
    var gsm = chars.every(function (c) { return basic[c] || extended[c]; });
    var limits = gsm ? LIMITS.gsm : LIMITS.unicode;

    var length = 0;
    if (gsm) {
      chars.forEach(function (c) { length += extended[c] ? 2 : 1; });
    } else {
      length = body.length; // UTF-16 code units
    }

    var segments = length === 0 ? 0 : length <= limits.single
      ? 1
      : Math.ceil(length / limits.multi);
    var capacity = segments <= 1 ? limits.single : limits.multi * segments;

    return {
      encoding: gsm ? 'gsm' : 'unicode',
      length: length,
      segments: segments,
      capacity: capacity,
      remaining: capacity - length
    };
  }

  function countRecipients(value) {
    var seen = {};
    var count = 0;
    var invalid = 0;
    String(value || '').split(/[,\n;]+/).forEach(function (part) {
      var raw = part.trim();
      if (!raw) return;
      var n = raw.replace(/[\s\-().+]/g, '');
      if (!/^\d+$/.test(n)) { invalid += 1; return; }
      if (n.indexOf('00') === 0) n = n.slice(2);
      if (n.charAt(0) === '0') n = '94' + n.slice(1);
      else if (n.length === 9) n = '94' + n;
      if (n.length < 9 || n.length > 15) { invalid += 1; return; }
      if (seen[n]) return;
      seen[n] = true;
      count += 1;
    });
    return { count: count, invalid: invalid };
  }

  global.Segments = { analyse: analyse, countRecipients: countRecipients, LIMITS: LIMITS };
})(window);
