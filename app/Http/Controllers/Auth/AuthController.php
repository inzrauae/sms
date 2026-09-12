<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CreditLedger;
use App\Services\Settings;
use App\Support\Uid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2'],
            'company' => ['nullable', 'string'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'Enter your name.',
            'name.min' => 'Enter your name.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'An account already uses that email. Sign in instead.',
            'password.min' => 'Use a password of at least 8 characters.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();
        $defaultRate = (float) Settings::get('default_rate', '0.75');

        $user = User::create([
            'uid' => Uid::make(),
            'name' => $data['name'],
            'company' => $data['company'] ?? null,
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'rate' => $defaultRate,
        ]);

        $bonus = (int) Settings::get('signup_bonus', '10');
        if ($bonus > 0) {
            CreditLedger::adjust($user->id, $bonus, [
                'type' => 'topup',
                'note' => 'Welcome credits',
                'amount' => 0,
                'actor' => 'system',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'status' => 'success',
            'data' => ['uid' => $user->uid, 'name' => $user->name, 'email' => $user->email, 'credits' => $bonus],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $user = User::where('email', $email)->first();

        // Always run a comparison so a missing account and a wrong password
        // take the same amount of time.
        $ok = $user
            ? Hash::check($password, $user->password)
            : Hash::check($password, '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinv');

        if (!$user || !$ok) {
            return response()->json(['status' => 'error', 'message' => 'That email and password do not match.'], 401);
        }
        if (!$user->isActive()) {
            return response()->json(['status' => 'error', 'message' => 'This account is suspended. Contact support.'], 403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['status' => 'success', 'data' => ['role' => $user->role]]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'success']);
    }

    public function session(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Not signed in.'], 401);
        }

        return response()->json(['status' => 'success', 'data' => $user->only([
            'id', 'uid', 'name', 'company', 'email', 'phone', 'role', 'status', 'credits', 'rate', 'created_at',
        ])]);
    }
}
