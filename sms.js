'use strict';

/**
 * Message encoding + segment maths.
 *
 * Billing depends entirely on this file, so it follows GSM 03.38 rather than
 * approximating with message.length. Getting it wrong means you either eat the
 * cost of extra segments or overcharge your customers.
 */

// GSM 03.38 basic character set. Every character here costs 1 septet.
const GSM_BASIC =
  '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?' +
  '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

// Extension table. Each of these costs 2 septets (escape + character).
const GSM_EXTENDED = '^{}\\[~]|€';

const BASIC_SET = new Set(GSM_BASIC.split(''));
const EXTENDED_SET = new Set(GSM_EXTENDED.split(''));

const LIMITS = {
  gsm: { single: 160, multi: 153 },
  unicode: { single: 70, multi: 67 },
};

/** True when every character can be carried by the 7-bit GSM alphabet. */
function isGsm7(text) {
  for (const char of text) {
    if (!BASIC_SET.has(char) && !EXTENDED_SET.has(char)) return false;
  }
  return true;
}

/** Length of the message in septets, counting extended characters twice. */
function gsm7Length(text) {
  let length = 0;
  for (const char of text) length += EXTENDED_SET.has(char) ? 2 : 1;
  return length;
}

/**
 * Work out encoding, length and segment count for a message body.
 * Sinhala and Tamil both fall outside GSM-7, so they bill as unicode at
 * 70/67 characters per segment. Warn your users about that in the composer.
 */
function analyse(text) {
  const body = typeof text === 'string' ? text : '';
  const gsm = isGsm7(body);
  const encoding = gsm ? 'gsm' : 'unicode';
  const limits = LIMITS[encoding];

  // Unicode length is counted in UTF-16 code units: an emoji outside the BMP
  // is a surrogate pair and eats two.
  const length = gsm ? gsm7Length(body) : body.length;

  let segments;
  if (length === 0) segments = 0;
  else if (length <= limits.single) segments = 1;
  else segments = Math.ceil(length / limits.multi);

  const capacity = segments <= 1 ? limits.single : limits.multi * segments;

  return {
    encoding,
    type: gsm ? 'plain' : 'unicode', // maps to the Text.lk `type` parameter
    length,
    segments,
    capacity,
    remaining: capacity - length,
  };
}

/** Credits consumed by one send: segments x recipients. */
function unitsFor(text, recipientCount = 1) {
  const { segments } = analyse(text);
  return Math.max(segments, 1) * Math.max(recipientCount, 1);
}

/**
 * Normalise a Sri Lankan number to the 94XXXXXXXXX form Text.lk expects.
 * Accepts 0712345678, +94712345678, 94 71 234 5678, etc. Numbers that already
 * carry another country code are passed through with punctuation stripped.
 */
function normaliseNumber(input, defaultCountry = '94') {
  if (input == null) return null;
  let n = String(input).trim().replace(/[\s\-().]/g, '');
  if (n.startsWith('+')) n = n.slice(1);
  if (!/^\d+$/.test(n)) return null;

  if (n.startsWith('00')) n = n.slice(2);
  if (n.startsWith('0')) n = defaultCountry + n.slice(1);
  else if (n.length === 9) n = defaultCountry + n; // 712345678

  if (n.length < 9 || n.length > 15) return null;
  return n;
}

/** Split and clean a recipient list. Returns { valid, invalid }. */
function parseRecipients(input, defaultCountry = '94') {
  const raw = Array.isArray(input) ? input : String(input || '').split(/[,\n;]+/);
  const valid = [];
  const invalid = [];
  const seen = new Set();

  for (const item of raw) {
    const trimmed = String(item).trim();
    if (!trimmed) continue;
    const number = normaliseNumber(trimmed, defaultCountry);
    if (!number) {
      invalid.push(trimmed);
      continue;
    }
    if (seen.has(number)) continue; // never bill twice for the same number
    seen.add(number);
    valid.push(number);
  }

  return { valid, invalid };
}

/** Sender IDs are alphanumeric, 11 characters maximum, or a plain number. */
function validateSenderMask(mask) {
  const value = String(mask || '').trim();
  if (!value) return { ok: false, error: 'Enter a sender name.' };
  if (/^\d{6,15}$/.test(value)) return { ok: true, value };
  if (value.length > 11) {
    return { ok: false, error: 'Sender names are limited to 11 characters.' };
  }
  if (!/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/.test(value)) {
    return { ok: false, error: 'Use letters, numbers, spaces, dots, hyphens or underscores.' };
  }
  return { ok: true, value };
}

module.exports = {
  analyse,
  unitsFor,
  isGsm7,
  normaliseNumber,
  parseRecipients,
  validateSenderMask,
  LIMITS,
};
