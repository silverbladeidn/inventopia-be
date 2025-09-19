<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        // Cari user berdasarkan email
        $user = User::where('email', $credentials['email'])->first();

        // Cek apakah user ada dan password benar
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.'
            ], 401);
        }

        // Cek apakah user diblokir
        if ($user->is_blocked) {
            return response()->json([
                'message' => 'Akun Anda diblokir, hubungi administrator.'
            ], 403);
        }

        // Hapus token lama (opsional)
        $user->tokens()->delete();

        $tokenName = 'api-token';
        $abilities = ['*'];
        $rememberMe = $credentials['remember'] ?? false;

        // Tentukan masa berlaku token
        $expiresAt = $rememberMe
            ? now()->addDays(30)  // 30 hari untuk remember me
            : now()->addDay();    // 1 hari untuk login biasa

        // Generate Sanctum token dengan expiration
        $token = $user->createToken($tokenName, $abilities, $expiresAt)->plainTextToken;

        return response()->json([
            'message' => 'Login sukses',
            'token'   => $token,
            'user'    => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role, // <- PERBAIKAN: ubah dari $user->roles ke $user->role
            ],
            'remember' => $rememberMe,
        ]);
    }

    public function logout(Request $request)
    {
        // Hapus token aktif
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }
}
