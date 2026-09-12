<?php

namespace App\Services;

use App\Exceptions\DispatchException;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\TextLkException;
use App\Models\Campaign;
use App\Models\Group;
use App\Models\Message;
use App\Models\SenderId;
use App\Models\User;
use App\Support\Uid;
use Illuminate\Support\Facades\DB;

class Dispatch
{
    public function __construct(private readonly TextLk $textlk)
    {
    }

    private function approvedSender(int $userId, string $mask): ?SenderId
    {
        return SenderId::where('user_id', $userId)
            ->where('mask', $mask)
            ->where('status', 'approved')
            ->first();
    }

    /**
     * Text.lk returns either one message object or a list, depending on how
     * many recipients were in the request. Flatten both shapes into a lookup
     * keyed by destination number so each of our rows gets its own provider uid.
     *
     * @return array{byNumber: array<string, array>, first: ?string}
     */
    private function indexProviderMessages(array $payload): array
    {
        $out = ['byNumber' => [], 'first' => null];
        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            return $out;
        }

        $rows = array_is_list($data) ? $data : (isset($data['data']) && is_array($data['data']) ? $data['data'] : [$data]);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!$out['first'] && !empty($row['uid'])) {
                $out['first'] = $row['uid'];
            }
            $to = Sms::normaliseNumber($row['to'] ?? $row['recipient'] ?? $row['phone'] ?? null);
            if ($to && !empty($row['uid'])) {
                $out['byNumber'][$to] = $row;
            }
        }

        return $out;
    }

    /** `Y-m-d H:i` is what the API documents; reject anything else early. */
    private function validateSchedule(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $trimmed = substr(str_replace('T', ' ', trim($value)), 0, 16);
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $trimmed)) {
            throw new DispatchException('Use the format YYYY-MM-DD HH:MM for the send time.', 'BAD_SCHEDULE');
        }

        $timestamp = strtotime(str_replace(' ', 'T', $trimmed));
        if ($timestamp < time() - 60) {
            throw new DispatchException('Pick a send time in the future.', 'BAD_SCHEDULE');
        }

        return $trimmed;
    }

    /**
     * Send to one or more explicit numbers.
     *
     * Credits are taken before the upstream call and returned if that call
     * fails, so a crash mid-flight can never leave a tenant billed for
     * nothing sent.
     */
    public function sendToNumbers(User $user, array $params): array
    {
        $message = $params['message'] ?? null;
        if (!$message || trim($message) === '') {
            throw new DispatchException('Write a message first.', 'EMPTY_MESSAGE');
        }

        $senderId = trim($params['sender_id'] ?? '');
        if (!$this->approvedSender($user->id, $senderId)) {
            throw new DispatchException("\"{$senderId}\" is not an approved sender name on your account.", 'SENDER_NOT_APPROVED');
        }

        ['valid' => $valid, 'invalid' => $invalid] = Sms::parseRecipients($params['recipients'] ?? '');
        if (!count($valid)) {
            throw new DispatchException('No valid phone numbers in that list.', 'NO_RECIPIENTS', ['invalid' => $invalid]);
        }
        if (count($valid) > 1000) {
            throw new DispatchException('Send to at most 1000 numbers at a time. Use a contact group for larger sends.', 'TOO_MANY');
        }

        $schedule = $this->validateSchedule($params['schedule_time'] ?? null);
        $analysis = Sms::analyse($message);
        $units = $analysis['segments'] * count($valid);
        $source = $params['source'] ?? 'dashboard';

        try {
            CreditLedger::adjust($user->id, -$units, [
                'type' => 'debit',
                'note' => count($valid) . ' recipient' . (count($valid) > 1 ? 's' : '') . ' x ' . $analysis['segments'] . ' segment' . ($analysis['segments'] > 1 ? 's' : ''),
                'actor' => $source,
            ]);
        } catch (InsufficientCreditsException $e) {
            throw new DispatchException(
                "This send needs {$units} credits and you have {$e->available}.",
                'INSUFFICIENT_CREDITS',
                ['required' => $units, 'available' => $e->available]
            );
        }

        try {
            $payload = $this->textlk->sendSms([
                'recipient' => implode(',', $valid),
                'sender_id' => $senderId,
                'type' => $analysis['type'],
                'message' => $message,
                'schedule_time' => $schedule,
                'dlt_template_id' => $params['dlt_template_id'] ?? null,
            ]);
        } catch (TextLkException $e) {
            CreditLedger::adjust($user->id, $units, ['type' => 'refund', 'note' => 'Refund: upstream send failed', 'actor' => $source]);
            throw new DispatchException($e->getMessage() ?: 'The gateway rejected this send.', 'UPSTREAM_FAILED');
        }

        $index = $this->indexProviderMessages($payload);
        $status = $schedule ? 'Scheduled' : 'Queued';
        $rate = $user->rate ?? 0;

        $uids = DB::transaction(function () use ($valid, $index, $user, $senderId, $message, $analysis, $status, $source, $schedule, $rate) {
            $ids = [];
            foreach ($valid as $number) {
                $match = $index['byNumber'][$number] ?? null;
                $localUid = Uid::make();

                Message::create([
                    'uid' => $localUid,
                    'provider_uid' => $match['uid'] ?? (count($valid) === 1 ? $index['first'] : null),
                    'user_id' => $user->id,
                    'recipient' => $number,
                    'sender_id' => $senderId,
                    'body' => $message,
                    'sms_type' => $analysis['type'],
                    'segments' => $analysis['segments'],
                    'units' => $analysis['segments'],
                    'cost' => $analysis['segments'] * $rate,
                    'status' => $match['status'] ?? $status,
                    'source' => $source,
                    'scheduled_at' => $schedule,
                ]);

                $ids[] = $localUid;
            }

            return $ids;
        });

        return [
            'uids' => $uids,
            'recipients' => $valid,
            'invalid' => $invalid,
            'units' => $units,
            'segments' => $analysis['segments'],
            'encoding' => $analysis['encoding'],
            'cost' => $units * $rate,
            'status' => $status,
            'scheduled_at' => $schedule,
        ];
    }

    /**
     * Send to every contact in a group. The group must belong to the caller
     * — Text.lk cannot tell our tenants apart, so ownership is checked here.
     */
    public function sendToGroup(User $user, array $params): array
    {
        $group = Group::where('provider_uid', $params['group_uid'] ?? '')->where('user_id', $user->id)->first();
        if (!$group) {
            throw new DispatchException('That contact group is not on your account.', 'GROUP_NOT_FOUND');
        }

        $senderId = trim($params['sender_id'] ?? '');
        if (!$this->approvedSender($user->id, $senderId)) {
            throw new DispatchException("\"{$senderId}\" is not an approved sender name on your account.", 'SENDER_NOT_APPROVED');
        }

        $message = $params['message'] ?? null;
        if (!$message || trim($message) === '') {
            throw new DispatchException('Write a message first.', 'EMPTY_MESSAGE');
        }

        $schedule = $this->validateSchedule($params['schedule_time'] ?? null);
        $analysis = Sms::analyse($message);
        $source = $params['source'] ?? 'dashboard';

        // Ask the gateway how many contacts are really in the group; the
        // cached count on our side can be stale if contacts were added
        // through the API.
        $contacts = $group->contacts;
        try {
            $listed = $this->textlk->listContacts($group->provider_uid, 1);
            $total = $listed['data']['total'] ?? null;
            if (is_int($total)) {
                $contacts = $total;
                $group->update(['contacts' => $total]);
            }
        } catch (TextLkException) {
            // fall back to the cached count
        }

        if (!$contacts) {
            throw new DispatchException('That group has no contacts yet.', 'EMPTY_GROUP');
        }

        $units = $analysis['segments'] * $contacts;
        try {
            CreditLedger::adjust($user->id, -$units, [
                'type' => 'debit',
                'note' => "Campaign to {$group->name} ({$contacts} contacts)",
                'actor' => $source,
            ]);
        } catch (InsufficientCreditsException $e) {
            throw new DispatchException(
                "This campaign needs {$units} credits and you have {$e->available}.",
                'INSUFFICIENT_CREDITS',
                ['required' => $units, 'available' => $e->available]
            );
        }

        try {
            $payload = $this->textlk->sendCampaign([
                'contact_list_id' => $group->provider_uid,
                'sender_id' => $senderId,
                'type' => $analysis['type'],
                'message' => $message,
                'schedule_time' => $schedule,
                'dlt_template_id' => $params['dlt_template_id'] ?? null,
            ]);
        } catch (TextLkException $e) {
            CreditLedger::adjust($user->id, $units, ['type' => 'refund', 'note' => 'Refund: campaign failed', 'actor' => $source]);
            throw new DispatchException($e->getMessage() ?: 'The gateway rejected this campaign.', 'UPSTREAM_FAILED');
        }

        $campaignUid = Uid::make();
        $rate = $user->rate ?? 0;

        Campaign::create([
            'uid' => $campaignUid,
            'user_id' => $user->id,
            'name' => $params['name'] ?? "Campaign to {$group->name}",
            'group_uid' => $group->provider_uid,
            'group_name' => $group->name,
            'sender_id' => $senderId,
            'body' => $message,
            'sms_type' => $analysis['type'],
            'contacts' => $contacts,
            'units' => $units,
            'cost' => $units * $rate,
            'status' => $schedule ? 'Scheduled' : 'Queued',
            'provider_ref' => $this->textlk->extractUid($payload),
            'scheduled_at' => $schedule,
        ]);

        return ['uid' => $campaignUid, 'contacts' => $contacts, 'units' => $units, 'segments' => $analysis['segments'], 'cost' => $units * $rate];
    }

    /**
     * Refresh delivery status for messages still in flight.
     * Text.lk has no delivery webhook in the v3 docs, so this polls instead.
     */
    public function syncStatuses(int $limit = 40): array
    {
        $pending = Message::whereNotNull('provider_uid')
            ->whereNotIn('status', Message::FINAL_STATUSES)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'provider_uid']);

        $updated = 0;
        foreach ($pending as $row) {
            try {
                $payload = $this->textlk->viewSms($row->provider_uid);
                $data = $payload['data'] ?? null;
                $record = is_array($data) && array_is_list($data) ? ($data[0] ?? null) : ($data['data'] ?? $data);

                if (!empty($record['status'])) {
                    $row->update(['status' => $record['status'], 'updated_at' => now()]);
                    $updated++;
                }
            } catch (TextLkException) {
                // leave the row pending and try again next cycle
            }
        }

        return ['checked' => $pending->count(), 'updated' => $updated];
    }
}
