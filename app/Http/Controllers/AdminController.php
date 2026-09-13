<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\TextLkException;
use App\Models\SenderId;
use App\Models\User;
use App\Services\CreditLedger;
use App\Services\Settings;
use App\Services\TextLk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(private readonly TextLk $textlk)
    {
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], $status);
    }

    /** Pull a numeric credit balance out of the loosely typed /balance response. */
    private function readUpstreamUnits(array $payload): ?float
    {
        $data = $payload['data'] ?? null;
        if ($data === null) {
            return null;
        }
        if (is_numeric($data)) {
            return (float) $data;
        }
        if (is_string($data)) {
            $n = (float) preg_replace('/[^\d.\-]/', '', $data);
            return is_finite($n) ? $n : null;
        }
        if (is_array($data)) {
            foreach (['remaining_sms_unit', 'sms_unit', 'remaining', 'balance', 'units', 'available'] as $key) {
                if (isset($data[$key])) {
                    $n = (float) preg_replace('/[^\d.\-]/', '', (string) $data[$key]);
                    if (is_finite($n)) {
                        return $n;
                    }
                }
            }
        }

        return null;
    }

    public function overview(): JsonResponse
    {
        $tenants = User::where('role', 'user')->selectRaw('COUNT(*) AS n, COALESCE(SUM(credits),0) AS credits')->first();

        $traffic = DB::table('messages')
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COUNT(*) AS messages, COALESCE(SUM(units),0) AS units, COALESCE(SUM(cost),0) AS revenue')
            ->first();

        $pendingSenders = SenderId::where('status', 'pending')->count();
        $topupRequests = DB::table('transactions')->where('type', 'request')->count();

        // Reconciliation: credits promised to tenants vs credits actually
        // held upstream. If sold exceeds held, the next sends will fail at
        // the gateway.
        $upstream = null;
        $upstreamError = null;
        try {
            $upstream = $this->readUpstreamUnits($this->textlk->getBalance());
        } catch (TextLkException $e) {
            $upstreamError = $e->getMessage();
        }

        return response()->json(['status' => 'success', 'data' => [
            'tenants' => $tenants->n,
            'credits_sold' => $tenants->credits,
            'upstream_units' => $upstream,
            'upstream_error' => $upstreamError,
            'shortfall' => $upstream !== null ? min($upstream - $tenants->credits, 0) : null,
            'traffic' => $traffic,
            'pending_senders' => $pendingSenders,
            'topup_requests' => $topupRequests,
            'settings' => Settings::all(),
        ]]);
    }

    public function users(Request $request): JsonResponse
    {
        $search = $request->input('search');

        $query = User::query();
        if ($search) {
            $like = "%{$search}%";
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('company', 'like', $like));
        }
        $rows = $query->orderByDesc('id')
            ->get(['id', 'uid', 'name', 'company', 'email', 'phone', 'role', 'status', 'credits', 'rate', 'created_at']);

        $stats = DB::table('messages')->select('user_id', DB::raw('COUNT(*) AS messages'), DB::raw('COALESCE(SUM(units),0) AS units'))
            ->groupBy('user_id')->get()->keyBy('user_id');

        $data = $rows->map(function ($u) use ($stats) {
            $stat = $stats->get($u->id);
            $u->messages = $stat->messages ?? 0;
            $u->units_used = $stat->units ?? 0;

            return $u;
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function usersCredits(Request $request, int $id): JsonResponse
    {
        $units = (int) round((float) $request->input('units'));
        if ($units === 0) {
            return $this->fail('Enter a number of credits to add or remove.');
        }

        $user = User::find($id);
        if (!$user) {
            return $this->fail('User not found.', 404);
        }

        try {
            $balance = CreditLedger::adjust($user->id, $units, [
                'type' => $units > 0 ? 'topup' : 'adjust',
                'note' => $request->input('note') ?: ($units > 0 ? 'Credits added by admin' : 'Credits removed by admin'),
                'amount' => $units > 0 ? $units * ($user->rate ?? 0) : 0,
                'actor' => $request->user()->email,
            ]);

            // Clear any queued requests for this tenant once credits land.
            if ($units > 0) {
                DB::table('transactions')->where('user_id', $user->id)->where('type', 'request')->delete();
            }

            return response()->json(['status' => 'success', 'data' => ['credits' => $balance]]);
        } catch (InsufficientCreditsException) {
            return $this->fail('That would take the balance below zero.');
        }
    }

    public function usersUpdate(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return $this->fail('User not found.', 404);
        }

        $rate = $request->has('rate') ? (float) $request->input('rate') : $user->rate;
        $status = $request->input('status') ?: $user->status;
        $role = $request->input('role') ?: $user->role;

        if (!is_finite($rate) || $rate < 0) {
            return $this->fail('Enter a valid rate.');
        }
        if (!in_array($status, ['active', 'suspended'], true)) {
            return $this->fail('Unknown status.');
        }
        if (!in_array($role, ['user', 'admin'], true)) {
            return $this->fail('Unknown role.');
        }
        if ($user->id === $request->user()->id && ($status !== 'active' || $role !== 'admin')) {
            return $this->fail('You cannot remove your own admin access.');
        }

        $user->update(['rate' => $rate, 'status' => $status, 'role' => $role]);

        return response()->json(['status' => 'success']);
    }

    /* ------------------------------------------------------ sender approvals */

    public function sendersIndex(): JsonResponse
    {
        $rows = SenderId::join('users', 'users.id', '=', 'sender_ids.user_id')
            ->orderByRaw("CASE sender_ids.status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('sender_ids.id')
            ->get(['sender_ids.id', 'sender_ids.mask', 'sender_ids.status', 'sender_ids.note', 'sender_ids.fee_amount', 'sender_ids.created_at', 'users.name', 'users.company', 'users.email']);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function sendersDecision(Request $request, int $id): JsonResponse
    {
        $decision = $request->input('decision');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return $this->fail('Unknown decision.');
        }

        $sender = SenderId::find($id);
        if (!$sender) {
            return $this->fail('Sender name not found.', 404);
        }

        // Refund the registration fee only on the first rejection out of
        // "pending" — later revoking an already-approved name keeps the fee,
        // since the service was already rendered.
        if ($decision === 'rejected' && $sender->status === 'pending' && $sender->fee_units > 0) {
            CreditLedger::adjust($sender->user_id, $sender->fee_units, [
                'type' => 'refund',
                'note' => 'Refund: sender ID "' . $sender->mask . '" rejected',
                'amount' => $sender->fee_amount,
            ]);
            $sender->fee_units = 0;
            $sender->fee_amount = 0;
        }

        $sender->status = $decision;
        $sender->note = $request->input('note');
        $sender->save();

        return response()->json(['status' => 'success']);
    }

    /* ---------------------------------------------------------- all traffic */

    public function messages(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 50), 200);
        $page = max((int) $request->input('page', 1), 1);

        $total = DB::table('messages')->count();
        $rows = DB::table('messages')
            ->join('users', 'users.id', '=', 'messages.user_id')
            ->orderByDesc('messages.id')
            ->forPage($page, $perPage)
            ->get([
                'messages.uid', 'messages.recipient', 'messages.sender_id', 'messages.body', 'messages.status',
                'messages.units', 'messages.cost', 'messages.source', 'messages.created_at',
                'users.name as user_name', 'users.email as user_email',
            ]);

        return response()->json(['status' => 'success', 'data' => ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]]);
    }

    public function requests(): JsonResponse
    {
        $rows = DB::table('transactions')
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->where('transactions.type', 'request')
            ->orderByDesc('transactions.id')
            ->get([
                'transactions.id', 'transactions.units', 'transactions.amount', 'transactions.note', 'transactions.created_at',
                'users.id as user_id', 'users.name', 'users.email', 'users.credits',
            ]);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    /* --------------------------------------------------------------- settings */

    public function settingsStore(Request $request): JsonResponse
    {
        foreach (['brand_name', 'default_rate', 'signup_bonus', 'support_email', 'currency', 'sender_id_fee'] as $key) {
            if ($request->filled($key)) {
                Settings::set($key, $request->input($key));
            }
        }

        return response()->json(['status' => 'success', 'data' => Settings::all()]);
    }
}
