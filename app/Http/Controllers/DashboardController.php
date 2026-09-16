<?php

namespace App\Http\Controllers;

use App\Exceptions\DispatchException;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\TextLkException;
use App\Models\AdminNotification;
use App\Models\Group;
use App\Models\Message;
use App\Models\SenderId;
use App\Services\CreditLedger;
use App\Services\Dispatch;
use App\Services\Settings;
use App\Services\Sms;
use App\Services\TextLk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    public function __construct(
        private readonly Dispatch $dispatch,
        private readonly TextLk $textlk,
    ) {
    }

    private function fail(string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message, ...$extra], $status);
    }

    private function dispatchError(DispatchException $e): JsonResponse
    {
        $status = $e->code === 'INSUFFICIENT_CREDITS' ? 402 : 422;

        return response()->json([
            'status' => 'error',
            'code' => $e->code,
            'message' => $e->getMessage(),
            ...$e->extra,
        ], $status);
    }

    /* --------------------------------------------------------------- overview */

    // Uses SQLite's date() function directly; revisit these two queries if
    // this ever moves to MySQL.
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        $totals = DB::table('messages')->where('user_id', $user->id)->selectRaw(
            "COUNT(*) AS total,
             COALESCE(SUM(units), 0) AS units,
             SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) AS delivered,
             SUM(CASE WHEN status IN ('Failed','Rejected','Undelivered') THEN 1 ELSE 0 END) AS failed,
             SUM(CASE WHEN status IN ('Queued','Sent','Pending','Scheduled') THEN 1 ELSE 0 END) AS pending"
        )->first();

        $month = DB::table('messages')->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COUNT(*) AS sent, COALESCE(SUM(units),0) AS units, COALESCE(SUM(cost),0) AS cost')
            ->first();

        $daily = DB::table('messages')->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw("date(created_at) AS day, COUNT(*) AS count")
            ->groupBy('day')->orderBy('day')->get();

        $recent = Message::where('user_id', $user->id)
            ->orderByDesc('id')->limit(8)
            ->get(['uid', 'recipient', 'sender_id', 'body', 'status', 'segments', 'units', 'created_at']);

        $senders = SenderId::where('user_id', $user->id)->orderBy('mask')->get(['mask', 'status']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'credits' => $user->credits,
                'rate' => $user->rate,
                'totals' => $totals,
                'month' => $month,
                'daily' => $daily,
                'recent' => $recent,
                'senders' => $senders,
                'groups' => Group::where('user_id', $user->id)->count(),
            ],
        ]);
    }

    /* ---------------------------------------------------------------- compose */

    // Live segment counter for the composer. Cheap, local, no upstream call.
    public function preview(Request $request): JsonResponse
    {
        $analysis = Sms::analyse((string) $request->input('message', ''));
        ['valid' => $valid, 'invalid' => $invalid] = Sms::parseRecipients($request->input('recipients', ''));
        $units = $analysis['segments'] * max(count($valid), 1);

        return response()->json(['status' => 'success', 'data' => [
            ...$analysis,
            'recipients' => count($valid),
            'invalid' => $invalid,
            'units' => $units,
            'cost' => round($units * $request->user()->rate, 2),
            'affordable' => $units <= $request->user()->credits,
        ]]);
    }

    public function send(Request $request): JsonResponse
    {
        try {
            $result = $this->dispatch->sendToNumbers($request->user(), [
                'recipients' => $request->input('recipients'),
                'sender_id' => trim((string) $request->input('sender_id', '')),
                'message' => $request->input('message'),
                'schedule_time' => $request->input('schedule_time'),
                'dlt_template_id' => $request->input('dlt_template_id'),
                'source' => 'dashboard',
            ]);
        } catch (DispatchException $e) {
            return $this->dispatchError($e);
        }

        return response()->json(['status' => 'success', 'data' => [
            ...$result,
            'credits' => $request->user()->fresh()->credits,
        ]]);
    }

    public function campaign(Request $request): JsonResponse
    {
        try {
            $result = $this->dispatch->sendToGroup($request->user(), [
                'group_uid' => (string) $request->input('group_uid', ''),
                'sender_id' => trim((string) $request->input('sender_id', '')),
                'message' => $request->input('message'),
                'schedule_time' => $request->input('schedule_time'),
                'name' => $request->input('name'),
                'source' => 'dashboard',
            ]);
        } catch (DispatchException $e) {
            return $this->dispatchError($e);
        }

        return response()->json(['status' => 'success', 'data' => [
            ...$result,
            'credits' => $request->user()->fresh()->credits,
        ]]);
    }

    /* --------------------------------------------------------------- messages */

    private function messageQuery(Request $request)
    {
        $query = Message::where('user_id', $request->user()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('sender')) {
            $query->where('sender_id', $request->input('sender'));
        }
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(fn ($q) => $q->where('recipient', 'like', $search)->orWhere('body', 'like', $search));
        }
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', substr($request->input('end_date') . ' 23:59:59', 0, 19));
        }

        return $query;
    }

    public function messages(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 100);
        $page = max((int) $request->input('page', 1), 1);

        $q = $this->messageQuery($request);
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get(['uid', 'provider_uid', 'recipient', 'sender_id', 'body', 'sms_type', 'segments', 'units', 'cost', 'status', 'source', 'scheduled_at', 'created_at']);

        return response()->json(['status' => 'success', 'data' => [
            'data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
            'last_page' => max((int) ceil($total / $perPage), 1),
        ]]);
    }

    public function messagesExport(Request $request)
    {
        $rows = $this->messageQuery($request)->orderByDesc('id')->limit(20000)
            ->get(['created_at', 'recipient', 'sender_id', 'sms_type', 'segments', 'units', 'cost', 'status', 'body']);

        $escape = fn ($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"';
        $header = 'Sent at,Recipient,Sender,Type,Segments,Credits,Cost,Status,Message';
        $body = $rows->map(fn ($r) => implode(',', array_map($escape, [
            $r->created_at, $r->recipient, $r->sender_id, $r->sms_type, $r->segments, $r->units, $r->cost, $r->status, $r->body,
        ])))->implode("\n");

        return response("{$header}\n{$body}", 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="messages-' . now()->timestamp . '.csv"',
        ]);
    }

    public function messagesSync(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->dispatch->syncStatuses(40)]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $rows = $request->user()->campaigns()->orderByDesc('id')->limit(50)->get();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    /* ------------------------------------------------------------- sender IDs */

    public function sendersIndex(Request $request): JsonResponse
    {
        $rows = $request->user()->senderIds()->orderByDesc('id')->get(['id', 'mask', 'status', 'note', 'fee_amount', 'created_at']);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function sendersStore(Request $request): JsonResponse
    {
        $check = Sms::validateSenderMask($request->input('mask'));
        if (!$check['ok']) {
            return $this->fail($check['error']);
        }

        $user = $request->user();

        $exists = $user->senderIds()->where('mask', $check['value'])->exists();
        if ($exists) {
            return $this->fail('You have already requested that sender name.', 409);
        }

        // A flat, refundable fee reserved up front — same reserve-then-refund
        // shape as SMS sends, so a rejected request never costs the tenant.
        $fee = (float) Settings::get('sender_id_fee', (string) config('portal.sender_id_fee', 1000));
        $feeUnits = $fee > 0 ? (int) ceil($fee / max($user->rate, 0.01)) : 0;

        if ($feeUnits > 0) {
            try {
                CreditLedger::adjust($user->id, -$feeUnits, [
                    'type' => 'sender_fee',
                    'note' => 'Sender ID registration fee: ' . $check['value'],
                    'amount' => $fee,
                ]);
            } catch (InsufficientCreditsException) {
                return $this->fail('Not enough credits to cover the Rs ' . number_format($fee) . ' sender ID fee.', 402);
            }
        }

        $user->senderIds()->create([
            'mask' => $check['value'],
            'status' => 'pending',
            'fee_units' => $feeUnits,
            'fee_amount' => $fee,
        ]);

        AdminNotification::create([
            'user_id' => $user->id,
            'type' => 'sender_request',
            'message' => $user->name . ' requested sender name "' . $check['value'] . '"' .
                ($fee > 0 ? ' (Rs ' . number_format($fee, 2) . ' fee reserved)' : ''),
            'data' => ['mask' => $check['value'], 'fee' => $fee],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Sender name submitted for approval.',
            'data' => ['credits' => $user->fresh()->credits],
        ], 201);
    }

    public function sendersDestroy(Request $request, int $id): JsonResponse
    {
        $request->user()->senderIds()->where('id', $id)->delete();

        return response()->json(['status' => 'success']);
    }

    /* ---------------------------------------------------------------- groups */

    // Groups are shared upstream, so the name sent to Text.lk is namespaced
    // by tenant while the tenant keeps seeing the name they typed.
    private function upstreamName($user, string $name): string
    {
        return substr("u{$user->id}-{$name}", 0, 60);
    }

    public function groupsIndex(Request $request): JsonResponse
    {
        $rows = $request->user()->groups()->orderByDesc('id')->get(['provider_uid', 'name', 'contacts', 'created_at']);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function groupsStore(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name', ''));
        if (mb_strlen($name) < 2) {
            return $this->fail('Give the group a name.');
        }

        try {
            $payload = $this->textlk->createGroup($this->upstreamName($request->user(), $name));
            $providerUid = $this->textlk->extractUid($payload);
            if (!$providerUid) {
                return $this->fail('The gateway created the group but returned no ID. Refresh and check your groups.', 502);
            }

            $request->user()->groups()->create(['provider_uid' => $providerUid, 'name' => $name]);

            return response()->json(['status' => 'success', 'data' => ['provider_uid' => $providerUid, 'name' => $name, 'contacts' => 0]], 201);
        } catch (TextLkException $e) {
            return $this->fail($e->getMessage(), 502);
        }
    }

    private function ownedGroup(Request $request, string $groupUid): ?Group
    {
        return $request->user()->groups()->where('provider_uid', $groupUid)->first();
    }

    public function groupsUpdate(Request $request, string $groupUid): JsonResponse
    {
        $group = $this->ownedGroup($request, $groupUid);
        if (!$group) {
            return $this->fail('Group not found.', 404);
        }

        $name = trim((string) $request->input('name', ''));
        if (mb_strlen($name) < 2) {
            return $this->fail('Give the group a name.');
        }

        try {
            $this->textlk->updateGroup($group->provider_uid, $this->upstreamName($request->user(), $name));
            $group->update(['name' => $name]);

            return response()->json(['status' => 'success']);
        } catch (TextLkException $e) {
            return $this->fail($e->getMessage(), 502);
        }
    }

    public function groupsDestroy(Request $request, string $groupUid): JsonResponse
    {
        $group = $this->ownedGroup($request, $groupUid);
        if (!$group) {
            return $this->fail('Group not found.', 404);
        }

        try {
            $this->textlk->deleteGroup($group->provider_uid);
            $group->delete();

            return response()->json(['status' => 'success']);
        } catch (TextLkException $e) {
            return $this->fail($e->getMessage(), 502);
        }
    }

    /* --------------------------------------------------------------- contacts */

    public function contactsIndex(Request $request, string $groupUid): JsonResponse
    {
        $group = $this->ownedGroup($request, $groupUid);
        if (!$group) {
            return $this->fail('Group not found.', 404);
        }

        try {
            $payload = $this->textlk->listContacts($group->provider_uid, (int) $request->input('page', 1));
            $data = $payload['data'] ?? null;
            if (is_array($data) && isset($data['total']) && is_int($data['total'])) {
                $group->update(['contacts' => $data['total']]);
            }

            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (TextLkException $e) {
            return $this->fail($e->getMessage(), 502);
        }
    }

    public function contactsStore(Request $request, string $groupUid): JsonResponse
    {
        $group = $this->ownedGroup($request, $groupUid);
        if (!$group) {
            return $this->fail('Group not found.', 404);
        }

        $phone = Sms::normaliseNumber($request->input('PHONE') ?? $request->input('phone'));
        if (!$phone) {
            return $this->fail('Enter a valid phone number.');
        }

        $fields = ['PHONE' => $phone];
        if ($request->filled('FIRST_NAME')) {
            $fields['FIRST_NAME'] = trim($request->input('FIRST_NAME'));
        }
        if ($request->filled('LAST_NAME')) {
            $fields['LAST_NAME'] = trim($request->input('LAST_NAME'));
        }

        try {
            $this->textlk->createContact($group->provider_uid, $fields);
            $group->increment('contacts');

            return response()->json(['status' => 'success'], 201);
        } catch (TextLkException $e) {
            return $this->fail($e->getMessage(), 502);
        }
    }

    /** Paste-in import: one contact per line, `phone, first name, last name`. */
    public function contactsImport(Request $request, string $groupUid): JsonResponse
    {
        $group = $this->ownedGroup($request, $groupUid);
        if (!$group) {
            return $this->fail('Group not found.', 404);
        }

        $lines = array_slice(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $request->input('rows', '')))), 0, 500);
        if (!count($lines)) {
            return $this->fail('Paste at least one contact.');
        }

        $added = [];
        $skipped = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', preg_split('/[,\t]/', $line));
            $phone = Sms::normaliseNumber($parts[0] ?? null);
            if (!$phone) {
                $skipped[] = $line;
                continue;
            }

            $fields = ['PHONE' => $phone];
            if (!empty($parts[1])) {
                $fields['FIRST_NAME'] = $parts[1];
            }
            if (!empty($parts[2])) {
                $fields['LAST_NAME'] = $parts[2];
            }

            try {
                $this->textlk->createContact($group->provider_uid, $fields);
                $added[] = $phone;
            } catch (TextLkException) {
                $skipped[] = $line;
            }
        }

        $group->increment('contacts', count($added));

        return response()->json(['status' => 'success', 'data' => ['added' => count($added), 'skipped' => $skipped]]);
    }

    public function contactsDestroy(Request $request, string $groupUid, string $contactUid): JsonResponse
    {
        $group = $this->ownedGroup($request, $groupUid);
        if (!$group) {
            return $this->fail('Group not found.', 404);
        }

        try {
            $this->textlk->deleteContact($group->provider_uid, $contactUid);
            $group->update(['contacts' => max($group->contacts - 1, 0)]);

            return response()->json(['status' => 'success']);
        } catch (TextLkException $e) {
            return $this->fail($e->getMessage(), 502);
        }
    }

    /* ------------------------------------------------------------ API tokens */

    public function tokensIndex(Request $request): JsonResponse
    {
        $rows = $request->user()->tokens()->orderByDesc('id')
            ->get(['id', 'name', 'prefix', 'last_used_at', 'created_at']);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function tokensStore(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->count();
        if ($count >= 5) {
            return $this->fail('You can hold five active tokens. Revoke one first.');
        }

        $name = trim((string) $request->input('name', 'Default token')) ?: 'Default token';
        $newToken = $request->user()->createToken($name);

        [, $secret] = explode('|', $newToken->plainTextToken, 2);
        $newToken->accessToken->forceFill(['prefix' => substr($secret, 0, 6)])->save();

        return response()->json([
            'status' => 'success',
            'data' => ['token' => $newToken->plainTextToken],
            'message' => 'Copy this token now. It will not be shown again.',
        ], 201);
    }

    public function tokensDestroy(Request $request, int $id): JsonResponse
    {
        $request->user()->tokens()->where('id', $id)->delete();

        return response()->json(['status' => 'success']);
    }

    /* ---------------------------------------------------------------- billing */

    public function transactions(Request $request): JsonResponse
    {
        $rows = $request->user()->transactions()->orderByDesc('id')->limit(100)
            ->get(['type', 'units', 'balance_after', 'amount', 'note', 'created_at']);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    /**
     * Records a credit request. Real money should move through a payment
     * gateway; this queues the request for an admin to confirm once payment
     * clears.
     */
    public function topupRequest(Request $request): JsonResponse
    {
        $units = (int) floor((float) $request->input('units'));
        if ($units < 100) {
            return $this->fail('Request at least 100 credits.');
        }

        $amount = $units * $request->user()->rate;
        $note = $request->input('note') ?: 'Top-up requested';

        $request->user()->transactions()->create([
            'type' => 'request',
            'units' => $units,
            'balance_after' => $request->user()->credits,
            'amount' => $amount,
            'note' => $note,
            'actor' => 'user',
        ]);

        AdminNotification::create([
            'user_id' => $request->user()->id,
            'type' => 'topup_request',
            'message' => $request->user()->name . ' requested ' . number_format($units) . ' credits (Rs ' . number_format($amount, 2) . ')',
            'data' => ['units' => $units, 'amount' => $amount],
        ]);

        return response()->json(['status' => 'success', 'message' => 'Request sent. Credits appear once payment is confirmed.']);
    }

    /* ---------------------------------------------------------------- profile */

    public function updateProfile(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name', ''));
        if (mb_strlen($name) < 2) {
            return $this->fail('Enter your name.');
        }

        $request->user()->update([
            'name' => $name,
            'company' => trim((string) $request->input('company', '')) ?: null,
            'phone' => trim((string) $request->input('phone', '')) ?: null,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Profile saved.']);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('new_password', '');
        if (strlen($new) < 8) {
            return $this->fail('Use a password of at least 8 characters.');
        }

        if (!Hash::check($current, $request->user()->password)) {
            return $this->fail('Your current password is not correct.', 401);
        }

        $request->user()->update(['password' => $new]);

        return response()->json(['status' => 'success', 'message' => 'Password changed.']);
    }
}
