<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DispatchException;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Services\Dispatch;
use App\Services\Sms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-facing REST API.
 *
 * Deliberately mirrors the Text.lk v3 contract: a customer already
 * integrated with Text.lk can point at this portal by changing the base URL
 * and the token.
 */
class SmsApiController extends Controller
{
    public function __construct(private readonly Dispatch $dispatch)
    {
    }

    private function sendError(DispatchException $e): JsonResponse
    {
        $status = $e->code === 'INSUFFICIENT_CREDITS' ? 402 : 422;

        return response()->json(['status' => 'error', 'message' => $e->getMessage()], $status);
    }

    public function send(Request $request): JsonResponse
    {
        try {
            $result = $this->dispatch->sendToNumbers($request->user(), [
                'recipients' => $request->input('recipient'),
                'sender_id' => trim((string) $request->input('sender_id', '')),
                'message' => $request->input('message'),
                'schedule_time' => $request->input('schedule_time'),
                'dlt_template_id' => $request->input('dlt_template_id'),
                'source' => 'api',
            ]);
        } catch (DispatchException $e) {
            return $this->sendError($e);
        }

        return response()->json(['status' => 'success', 'data' => [
            'uid' => $result['uids'][0],
            'uids' => $result['uids'],
            'to' => implode(',', $result['recipients']),
            'from' => trim((string) $request->input('sender_id', '')),
            'message' => $request->input('message'),
            'sms_type' => $result['encoding'] === 'unicode' ? 'unicode' : 'plain',
            'sms_count' => $result['segments'],
            'cost' => $result['units'],
            'status' => $result['status'],
            'scheduled_at' => $result['scheduled_at'],
        ]]);
    }

    public function campaign(Request $request): JsonResponse
    {
        try {
            $result = $this->dispatch->sendToGroup($request->user(), [
                'group_uid' => (string) ($request->input('contact_list_id') ?: $request->input('recipient', '')),
                'sender_id' => trim((string) $request->input('sender_id', '')),
                'message' => $request->input('message'),
                'schedule_time' => $request->input('schedule_time'),
                'source' => 'api',
            ]);
        } catch (DispatchException $e) {
            return $this->sendError($e);
        }

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    public function show(Request $request, string $uid): JsonResponse
    {
        $row = Message::where('uid', $uid)->where('user_id', $request->user()->id)
            ->selectRaw('uid, recipient AS "to", sender_id AS "from", body AS message, sms_type, source AS direction, status, segments AS sms_count, units AS cost, created_at AS sent_at')
            ->first();

        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'No message with that ID.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $row]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Message::where('user_id', $request->user()->id);

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }
        if ($request->filled('sms_type')) {
            $query->where('sms_type', $request->input('sms_type'));
        }
        if ($request->filled('direction')) {
            $query->where('source', $request->input('direction'));
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $page = max((int) $request->input('page', 1), 1);
        $total = (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()->map(fn (Message $m) => [
            'uid' => $m->uid,
            'to' => $m->recipient,
            'from' => $m->sender_id,
            'message' => $m->body,
            'sms_type' => $m->sms_type,
            'direction' => $m->source,
            'status' => $m->status,
            'sms_count' => $m->segments,
            'cost' => $m->units,
            'sent_at' => $m->created_at,
        ]);

        $base = $request->url();
        $lastPage = max((int) ceil($total / $perPage), 1);

        return response()->json([
            'status' => 'success',
            'message' => 'SMS data fetched successfully',
            'data' => [
                'current_page' => $page,
                'data' => $rows,
                'first_page_url' => "{$base}?page=1",
                'from' => $rows->count() ? ($page - 1) * $perPage + 1 : null,
                'last_page' => $lastPage,
                'last_page_url' => "{$base}?page={$lastPage}",
                'next_page_url' => $page < $lastPage ? "{$base}?page=" . ($page + 1) : null,
                'path' => $base,
                'per_page' => $perPage,
                'prev_page_url' => $page > 1 ? "{$base}?page=" . ($page - 1) : null,
                'to' => $rows->count() ? ($page - 1) * $perPage + $rows->count() : null,
                'total' => $total,
            ],
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => [
            'remaining_sms_unit' => $request->user()->credits,
            'rate' => $request->user()->rate,
            'currency' => 'LKR',
        ]]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['status' => 'success', 'data' => [
            'uid' => $user->uid,
            'name' => $user->name,
            'company' => $user->company,
            'email' => $user->email,
            'phone' => $user->phone,
            'remaining_sms_unit' => $user->credits,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ]]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $rows = $request->user()->groups()->get(['provider_uid as uid', 'name', 'contacts']);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    /** Utility endpoint: cost a message before committing to send it. */
    public function estimate(Request $request): JsonResponse
    {
        $analysis = Sms::analyse((string) $request->input('message', ''));
        ['valid' => $valid, 'invalid' => $invalid] = Sms::parseRecipients($request->input('recipient', ''));
        $units = $analysis['segments'] * max(count($valid), 1);

        return response()->json(['status' => 'success', 'data' => [
            ...$analysis,
            'recipients' => count($valid),
            'invalid' => $invalid,
            'units' => $units,
            'cost' => $units * $request->user()->rate,
        ]]);
    }
}
