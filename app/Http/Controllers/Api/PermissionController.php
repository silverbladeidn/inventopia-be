<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::all();
        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Get user permissions - SESUAIKAN DENGAN STRUKTUR 1 USER = 1 ROLE
     */
    public function getUserPermissions(int $userId): JsonResponse
    {
        try {
            // Get user dengan role dan permissions (gunakan role() bukan roles())
            $user = User::with(['role.permissions'])->findOrFail($userId);

            if (!$user->role) {
                return response()->json([
                    'success' => true,
                    'message' => 'User has no role assigned',
                    'permissions' => [],
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => null
                    ]
                ]);
            }

            // Get permissions dari role user
            $permissions = $user->role->permissions->pluck('name')->toArray();

            return response()->json([
                'success' => true,
                'message' => 'User permissions retrieved successfully',
                'permissions' => $permissions,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->name
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('getUserPermissions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current authenticated user's permissions - SESUAIKAN
     */
    public function getMyPermissions(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load(['role.permissions']);

            if (!$user->role) {
                return response()->json([
                    'success' => true,
                    'message' => 'You have no role assigned',
                    'permissions' => [],
                    'permissions_count' => 0
                ]);
            }

            // Get permissions dari role user
            $permissions = $user->role->permissions->pluck('name')->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Your permissions retrieved successfully',
                'permissions' => $permissions,
                'permissions_count' => count($permissions)
            ]);
        } catch (\Exception $e) {
            Log::error('getMyPermissions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user has specific permission - SESUAIKAN
     */
    public function checkUserPermission(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string'
        ]);

        try {
            $user = User::with(['role.permissions'])->findOrFail($userId);
            $permissionName = $request->input('permission');

            $hasPermission = $user->role
                ? $user->role->permissions->contains('name', $permissionName)
                : false;

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'permission' => $permissionName,
                    'has_permission' => $hasPermission
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('checkUserPermission error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check user permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
