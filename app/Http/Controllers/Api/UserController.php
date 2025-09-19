<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Tampilkan semua user.
     */
    public function index(): JsonResponse
    {
        $users = User::latest()->get();
        return response()->json($users);
    }

    /**
     * Tambahkan user baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'username'  => 'required|string|max:255|unique:users,username',
            'email'     => 'required|string|email|max:255|unique:users,email',
            'password'  => 'required|string|min:6',
            'role'      => 'nullable|string',
            'is_blocked' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'data' => $user
        ], 201);
    }

    /**
     * Detail user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'username'  => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email'     => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password'  => 'nullable|string|min:6',
            'role'      => 'nullable|string',
            'is_blocked' => 'boolean',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'data' => $user
        ]);
    }

    /**
     * Hapus user.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus.'
        ]);
    }

    public function changePassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'current_password'      => 'required|string',
            'new_password'          => 'required|string|confirmed',
        ]);

        // Cek password lama
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Password lama salah.'
            ], 422);
        }

        // Update password
        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diubah.'
        ]);
    }

    /**
     * Update status block/unblock user.
     */
    public function updateBlockStatus(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'is_blocked' => 'required|boolean',
        ]);

        $user->update([
            'is_blocked' => $validated['is_blocked'],
        ]);

        return response()->json([
            'message' => 'Status blokir diperbarui.',
            'data' => $user
        ]);
    }
}
