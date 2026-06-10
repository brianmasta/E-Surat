<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\MobileApiFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak sesuai.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Akun ini sedang nonaktif. Hubungi Admin Sekretariat.',
            ]);
        }

        $token = $user->createToken($validated['device_name'] ?? 'E-Surat Android')->plainTextToken;

        ActivityLog::record('mobile.login', 'Login dari aplikasi Android.', $user);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => MobileApiFormatter::user($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => MobileApiFormatter::user($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
