<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /api/users
     * Tampilkan semua user terbaru.
     */
    public function index(): JsonResponse
    {
        try {
            $users = User::with('role')->latest()->get();

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pengguna: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/users/{user}
     * Detail user.
     */
    public function show(User $user): JsonResponse
    {
        try {
            $user->load('role.permissions');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'is_blocked' => $user->is_blocked,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'role_id' => $user->role_id,
                    'role' => $user->role->name, // ✅ Tambahkan nama role
                    'permissions' => $user->role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'description' => $permission->description
                        ];
                    }) ?? [] // ✅ Format sama dengan update response
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail pengguna: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/users
     * Tambahkan user baru sesuai frontend.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users,username',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'role' => 'required|integer|exists:roles,id', // role sekarang ID
                'permissions' => 'nullable|array',
                'permissions.*' => 'integer|exists:permissions,id',
                'is_blocked' => 'boolean',
            ]);

            DB::beginTransaction();

            // Ambil role berdasarkan ID
            $role = Role::find($validated['role']);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role tidak ditemukan'
                ], 422);
            }

            // Buat user baru
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id' => $role->id,
                'is_blocked' => $validated['is_blocked'] ?? false
            ]);

            // Jika ada custom permissions dari frontend (hanya Superadmin)
            if (!empty($validated['permissions'])) {
                $currentUser = $request->user(); // ambil user login

                if ($currentUser && $currentUser->role && $currentUser->role->name === 'Superadmin') {
                    $role->permissions()->sync($validated['permissions']);
                }
            }

            DB::commit();

            // KIRIM RESPONSE DENGAN ROLE NAME
            return response()->json([
                'success' => true,
                'message' => 'User berhasil ditambahkan',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role' => $role->name, // ✅ Tambahkan nama role
                    'is_blocked' => $user->is_blocked,
                    'created_at' => $user->created_at,
                ]
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Validasi gagal'
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah pengguna: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT/PATCH /api/users/{user}
     * Update user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
                'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'password' => 'nullable|string|min:6',
                'role' => 'sometimes|required|integer|exists:roles,id',
                'permissions' => 'sometimes|array', // Sesuai dengan FE
                'permissions.*' => 'integer|exists:permissions,id', // Sesuai dengan FE
                'is_blocked' => 'sometimes|boolean',
            ]);

            $updateData = [];

            // Update fields yang dikirim
            if (isset($validated['name'])) $updateData['name'] = $validated['name'];
            if (isset($validated['username'])) $updateData['username'] = $validated['username'];
            if (isset($validated['email'])) $updateData['email'] = $validated['email'];
            if (isset($validated['is_blocked'])) $updateData['is_blocked'] = $validated['is_blocked'];

            // Update password jika diberikan (sesuai FE - optional)
            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            // Handle role change - SAMA PERSIS dengan store
            $roleChanged = false;
            $newRole = null;

            if ($request->has('role')) {
                $updateData['role_id'] = $validated['role'];
                $roleChanged = true;
                $newRole = Role::find($validated['role']);
            }

            // Update user data
            $user->update($updateData);

            // Handle permissions - SAMA PERSIS dengan store
            if ($request->has('permissions')) {
                $currentUser = $request->user();

                // Hanya Superadmin yang bisa edit permissions (sama seperti store)
                if ($currentUser && $currentUser->role && $currentUser->role->name === 'Superadmin') {
                    $roleToUpdate = $roleChanged ? $newRole : $user->role;

                    if ($roleToUpdate) {
                        $roleToUpdate->permissions()->sync($validated['permissions']);
                    }
                }
            }

            DB::commit();

            // Load relationships untuk response
            $user->load('role.permissions');

            // Response format SAMA PERSIS dengan store
            return response()->json([
                'success' => true,
                'message' => 'User berhasil diperbarui',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role' => $user->role->name, // ✅ Sama dengan store
                    'is_blocked' => $user->is_blocked,
                    'updated_at' => $user->updated_at,
                    'permissions' => $user->role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'description' => $permission->description
                        ];
                    }) ?? [] // ✅ Sama dengan store
                ]
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Validasi gagal'
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate pengguna: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/users/{user}
     * Hapus user.
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pengguna: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/permissions
     * Get permissions untuk dropdown frontend
     */
    public function getPermissions(): JsonResponse
    {
        try {
            $permissions = Permission::select('id', 'name', 'description')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $permissions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data permissions'
            ], 500);
        }
    }

    /**
     * GET /api/roles
     * Get roles untuk dropdown frontend
     */
    public function getRoles(): JsonResponse
    {
        try {
            $roles = Role::select('id', 'name', 'description')
                ->with('permissions:id,name,description')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data roles'
            ], 500);
        }
    }

    /**
     * POST /api/users/{user}/change-password
     * Ganti password user.
     */
    public function changePassword(Request $request, User $user): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password lama salah.'
                ], 422);
            }

            $user->update([
                'password' => Hash::make($validated['new_password']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password berhasil diubah.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah password: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PATCH /api/users/{user}/block
     * Update status blokir user.
     */
    public function updateBlockStatus(Request $request, User $user): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_blocked' => 'required|boolean',
            ]);

            $user->update([
                'is_blocked' => $validated['is_blocked'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status blokir diperbarui.',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status blokir: ' . $e->getMessage()
            ], 500);
        }
    }
}
