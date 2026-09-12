'use strict';

const { db, uid, adjustCredits } = require('./db');
const textlk = require('./textlk');
const sms = require('./sms');

const FINAL_STATUSES = new Set(['Delivered', 'Failed', 'Rejected', 'Undelivered', 'Expired']);

class DispatchError extends Error {
  constructor(message, code = 'SEND_FAILED', extra = {}) {
    super(message);
    this.code = code;
    Object.assign(this, extra);
  }
}

function approvedSender(userId, mask) {
  return db
    .prepare("SELECT * FROM sender_ids WHERE user_id = ? AND mask = ? AND status = 'approved'")
    .get(userId, mask);
}

/**
 * Text.lk returns either one message object or a list, depending on how many
 * recipients were in the request. Flatten both shapes into a lookup keyed by
 * destination number so each of our rows gets its own provider uid.
 */
function indexProviderMessages(payload) {
  const out = { byNumber: new Map(), first: null };
  const data = payload && payload.data;
  if (!data || typeof data !== 'object') return out;

  const rows = Array.isArray(data) ? data : Array.isArray(data.data) ? data.data : [data];
  for (const row of rows) {
    if (!row || typeof row !== 'object') continue;
    if (!out.first && row.uid) out.first = row.uid;
    const to = sms.normaliseNumber(row.to || row.recipient || row.phone);
    if (to && row.uid) out.byNumber.set(to, row);
  }
  return out;
}

/** `Y-m-d H:i` is what the API documents; reject anything else early. */
function validateSchedule(value) {
  if (!value) return null;
  const trimmed = String(value).trim().replace('T', ' ').slice(0, 16);
  if (!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(trimmed)) {
    throw new DispatchError('Use the format YYYY-MM-DD HH:MM for the send time.', 'BAD_SCHEDULE');
  }
  if (new Date(trimmed.replace(' ', 'T')).getTime() < Date.now() - 60_000) {
    throw new DispatchError('Pick a send time in the future.', 'BAD_SCHEDULE');
  }
  return trimmed;
}

/**
 * Send to one or more explicit numbers.
 *
 * Credits are taken before the upstream call and returned if that call fails,
 * so a crash mid-flight can never leave a tenant billed for nothing sent.
 */
async function sendToNumbers({ user, recipients, senderId, message, scheduleTime, dltTemplateId, source = 'dashboard' }) {
  if (!message || !String(message).trim()) {
    throw new DispatchError('Write a message first.', 'EMPTY_MESSAGE');
  }
  if (!approvedSender(user.id, senderId)) {
    throw new DispatchError(
      `"${senderId}" is not an approved sender name on your account.`,
      'SENDER_NOT_APPROVED'
    );
  }

  const { valid, invalid } = sms.parseRecipients(recipients);
  if (!valid.length) {
    throw new DispatchError('No valid phone numbers in that list.', 'NO_RECIPIENTS', { invalid });
  }
  if (valid.length > 1000) {
    throw new DispatchError('Send to at most 1000 numbers at a time. Use a contact group for larger sends.', 'TOO_MANY');
  }

  const schedule = validateSchedule(scheduleTime);
  const analysis = sms.analyse(message);
  const units = analysis.segments * valid.length;

  // Reserve credits up front.
  try {
    adjustCredits(user.id, -units, {
      type: 'debit',
      note: `${valid.length} recipient${valid.length > 1 ? 's' : ''} x ${analysis.segments} segment${analysis.segments > 1 ? 's' : ''}`,
      actor: source,
    });
  } catch (err) {
    if (err.code === 'INSUFFICIENT_CREDITS') {
      throw new DispatchError(
        `This send needs ${units} credits and you have ${err.available}.`,
        'INSUFFICIENT_CREDITS',
        { required: units, available: err.available }
      );
    }
    throw err;
  }

  let payload;
  try {
    payload = await textlk.sendSms({
      recipient: valid.join(','),
      sender_id: senderId,
      type: analysis.type,
      message,
      schedule_time: schedule,
      dlt_template_id: dltTemplateId,
    });
  } catch (err) {
    adjustCredits(user.id, units, {
      type: 'refund',
      note: 'Refund: upstream send failed',
      actor: source,
    });
    throw new DispatchError(err.message || 'The gateway rejected this send.', 'UPSTREAM_FAILED');
  }

  const index = indexProviderMessages(payload);
  const status = schedule ? 'Scheduled' : 'Queued';
  const rate = user.rate || 0;

  const insert = db.prepare(
    `INSERT INTO messages
      (uid, provider_uid, user_id, recipient, sender_id, body, sms_type, segments, units, cost, status, source, scheduled_at)
     VALUES (@uid, @provider_uid, @user_id, @recipient, @sender_id, @body, @sms_type, @segments, @units, @cost, @status, @source, @scheduled_at)`
  );

  const saved = db.transaction((numbers) => {
    const ids = [];
    for (const number of numbers) {
      const match = index.byNumber.get(number);
      const localUid = uid();
      insert.run({
        uid: localUid,
        provider_uid: match ? match.uid : numbers.length === 1 ? index.first : null,
        user_id: user.id,
        recipient: number,
        sender_id: senderId,
        body: message,
        sms_type: analysis.type,
        segments: analysis.segments,
        units: analysis.segments,
        cost: analysis.segments * rate,
        status: (match && match.status) || status,
        source,
        scheduled_at: schedule,
      });
      ids.push(localUid);
    }
    return ids;
  })(valid);

  return {
    uids: saved,
    recipients: valid,
    invalid,
    units,
    segments: analysis.segments,
    encoding: analysis.encoding,
    cost: units * rate,
    status,
    scheduled_at: schedule,
  };
}

/**
 * Send to every contact in a group. The group must belong to the caller —
 * Text.lk cannot tell our tenants apart, so ownership is checked here.
 */
async function sendToGroup({ user, groupUid, senderId, message, scheduleTime, dltTemplateId, name, source = 'dashboard' }) {
  const group = db
    .prepare('SELECT * FROM groups WHERE provider_uid = ? AND user_id = ?')
    .get(groupUid, user.id);
  if (!group) throw new DispatchError('That contact group is not on your account.', 'GROUP_NOT_FOUND');
  if (!approvedSender(user.id, senderId)) {
    throw new DispatchError(`"${senderId}" is not an approved sender name on your account.`, 'SENDER_NOT_APPROVED');
  }
  if (!message || !String(message).trim()) {
    throw new DispatchError('Write a message first.', 'EMPTY_MESSAGE');
  }

  const schedule = validateSchedule(scheduleTime);
  const analysis = sms.analyse(message);

  // Ask the gateway how many contacts are really in the group; the cached
  // count on our side can be stale if contacts were added through the API.
  let contacts = group.contacts;
  try {
    const listed = await textlk.listContacts(groupUid, 1);
    const total = listed && listed.data && listed.data.total;
    if (Number.isInteger(total)) {
      contacts = total;
      db.prepare('UPDATE groups SET contacts = ? WHERE id = ?').run(total, group.id);
    }
  } catch {
    /* fall back to the cached count */
  }

  if (!contacts) throw new DispatchError('That group has no contacts yet.', 'EMPTY_GROUP');

  const units = analysis.segments * contacts;
  try {
    adjustCredits(user.id, -units, {
      type: 'debit',
      note: `Campaign to ${group.name} (${contacts} contacts)`,
      actor: source,
    });
  } catch (err) {
    if (err.code === 'INSUFFICIENT_CREDITS') {
      throw new DispatchError(
        `This campaign needs ${units} credits and you have ${err.available}.`,
        'INSUFFICIENT_CREDITS',
        { required: units, available: err.available }
      );
    }
    throw err;
  }

  let payload;
  try {
    payload = await textlk.sendCampaign({
      contact_list_id: groupUid,
      sender_id: senderId,
      type: analysis.type,
      message,
      schedule_time: schedule,
      dlt_template_id: dltTemplateId,
    });
  } catch (err) {
    adjustCredits(user.id, units, { type: 'refund', note: 'Refund: campaign failed', actor: source });
    throw new DispatchError(err.message || 'The gateway rejected this campaign.', 'UPSTREAM_FAILED');
  }

  const campaignUid = uid();
  db.prepare(
    `INSERT INTO campaigns
      (uid, user_id, name, group_uid, group_name, sender_id, body, sms_type, contacts, units, cost, status, provider_ref, scheduled_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)`
  ).run(
    campaignUid,
    user.id,
    name || `Campaign to ${group.name}`,
    groupUid,
    group.name,
    senderId,
    message,
    analysis.type,
    contacts,
    units,
    units * (user.rate || 0),
    schedule ? 'Scheduled' : 'Queued',
    textlk.extractUid(payload),
    schedule
  );

  return { uid: campaignUid, contacts, units, segments: analysis.segments, cost: units * (user.rate || 0) };
}

/**
 * Refresh delivery status for messages still in flight.
 * Text.lk has no delivery webhook in the v3 docs, so this polls instead.
 */
async function syncStatuses(limit = 40) {
  const pending = db
    .prepare(
      `SELECT id, provider_uid FROM messages
       WHERE provider_uid IS NOT NULL
         AND status NOT IN ('Delivered','Failed','Rejected','Undelivered','Expired')
       ORDER BY created_at DESC LIMIT ?`
    )
    .all(limit);

  let updated = 0;
  for (const row of pending) {
    try {
      const payload = await textlk.viewSms(row.provider_uid);
      const data = payload && payload.data;
      const record = Array.isArray(data) ? data[0] : data && data.data ? data.data : data;
      if (record && record.status) {
        db.prepare("UPDATE messages SET status = ?, updated_at = datetime('now') WHERE id = ?").run(
          record.status,
          row.id
        );
        updated += 1;
      }
    } catch {
      /* leave the row pending and try again next cycle */
    }
  }
  return { checked: pending.length, updated };
}

module.exports = { sendToNumbers, sendToGroup, syncStatuses, DispatchError, FINAL_STATUSES };
