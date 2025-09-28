<?php

// app/Http/Controllers/Api/ItemRequestController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemRequest;
use App\Models\ItemRequestDetail;
use App\Models\ItemRequestLog;
use App\Mail\ItemRequestCreated;
use App\Models\Product;
use App\Models\EmailSettings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ItemRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ItemRequest::with(['user', 'details.product.category', 'approvedBy'])
                ->orderBy('created_at', 'desc');

            /** @var User $user */
            $user = Auth::user();

            // Filter by user if not admin
            if (!$user->hasRole('admin')) {
                $query->byUser($user->id);
            }

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Search by request number or user name
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('request_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $requests = $query->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Requests retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // --- Validasi dasar ---
        $validator = Validator::make($request->all(), [
            'note'    => 'nullable|string',
            'action'  => 'nullable|in:draft,submit',    // <-- tambah validasi action
            'details' => 'required|array|min:1',
            'details.*.product_id' => 'required|exists:products,id',
            'details.*.qty'        => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $action = $request->input('action', 'draft'); // default draft
        DB::beginTransaction();

        try {
            // Jika submit, lakukan validasi stok lebih dulu
            if ($action === 'submit') {
                foreach ($request->details as $detail) {
                    $product = Product::findOrFail($detail['product_id']);
                    if ($product->stock_quantity < $detail['qty']) {
                        return response()->json([
                            'success' => false,
                            'message' => "Stok tidak mencukupi untuk {$product->name}. " .
                                "Stok: {$product->stock_quantity}, diminta: {$detail['qty']}"
                        ], 422);
                    }
                }
            }

            // === Buat record utama ===
            $itemRequest = ItemRequest::create([
                'user_id'        => $request->user()->id,
                'note'           => $request->note,
                'request_number' => 'REQ-' . now()->format('YmdHis'),
                'status'         => $action === 'submit' ? 'pending' : 'draft',
                'approved_by'    => null,
                'approved_at'    => null,
            ]);

            // === Detail item ===
            foreach ($request->details as $detail) {
                ItemRequestDetail::create([
                    'item_request_id'    => $itemRequest->id,
                    'product_id'         => $detail['product_id'],
                    'requested_quantity' => $detail['qty'],
                    'approved_quantity'  => 0,
                    'status'             => $action === 'submit' ? 'pending' : 'draft',
                ]);

                // Kurangi stok hanya jika submit
                if ($action === 'submit') {
                    $product = Product::findOrFail($detail['product_id']);
                    $oldStock = $product->stock_quantity;
                    $product->decrement('stock_quantity', $detail['qty']);

                    Log::info("Stock reduced for product: {$product->name}", [
                        'product_id' => $product->id,
                        'old_stock' => $oldStock,
                        'new_stock' => $product->stock_quantity,
                        'quantity_used' => $detail['qty'],
                        'request_number' => $itemRequest->request_number,
                        'user_id' => $request->user()->id
                    ]);
                }
            }

            // Catat log
            ItemRequestLog::create([
                'item_request_id' => $itemRequest->id,
                'user_id'         => $request->user()->id,
                'action'          => $action === 'submit'
                    ? 'created_pending'
                    : 'created_draft',
                'old_data'        => null,
                'new_data'        => $itemRequest->toArray(),
                'description'     => $action === 'submit'
                    ? "Request created and waiting for approval"
                    : "Request saved as draft",
            ]);

            DB::commit();

            // Email hanya jika submit
            if ($action === 'submit') {
                try {
                    $emailSettings = EmailSettings::first();
                    if ($emailSettings && $emailSettings->request_notifications) {
                        $mail = Mail::to($emailSettings->admin_email);

                        if (!empty($emailSettings->cc_emails)) {
                            $mail->cc($emailSettings->cc_emails);
                        }

                        $mail->send(new ItemRequestCreated($itemRequest));
                    }
                } catch (\Exception $mailErr) {
                    Log::error('Gagal kirim email notifikasi: ' . $mailErr->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $itemRequest->load('details.product'),
                'message' => $action === 'submit'
                    ? 'Request berhasil dibuat dan stok dikurangi'
                    : 'Draft berhasil disimpan',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $request = ItemRequest::with([
                'user',
                'details.product.category',
                'approvedBy',
                'logs.user'
            ])->findOrFail($id);

            /** @var User $user */
            $user = Auth::user();

            // Check if user can view this request
            if (!$user->hasRole('admin') && $request->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this request'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $request,
                'message' => 'Request retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource (untuk admin jika ingin mengubah status)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Only admin can update requests
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected,cancelled',
            'admin_note' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $itemRequest = ItemRequest::with('details.product')->findOrFail($id);

            // Store old data for logging
            $oldData = $itemRequest->toArray();

            // Jika status berubah menjadi cancelled, kembalikan stok
            if ($request->status === 'cancelled' && $itemRequest->status === 'approved') {
                foreach ($itemRequest->details as $detail) {
                    if ($detail->status === 'approved') {
                        $product = $detail->product;
                        $product->increment('stock_quantity', $detail->approved_quantity);

                        Log::info("Stock restored for cancelled request", [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity_restored' => $detail->approved_quantity,
                            'request_number' => $itemRequest->request_number
                        ]);
                    }
                }
            }

            // Update main request
            $itemRequest->update([
                'status' => $request->status,
                'admin_note' => $request->admin_note
            ]);

            // Update details status jika di-cancel
            if ($request->status === 'cancelled') {
                $itemRequest->details()->update(['status' => 'cancelled']);
            }

            // Log the update
            ItemRequestLog::create([
                'item_request_id' => $itemRequest->id,
                'user_id' => $user->id,
                'action' => 'status_updated',
                'old_data' => $oldData,
                'new_data' => $itemRequest->fresh()->toArray(),
                'description' => "Request status changed to {$request->status} by admin"
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $itemRequest->fresh(['details.product.category', 'user', 'approvedBy']),
                'message' => 'Request updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $itemRequest = ItemRequest::with('details.product')->findOrFail($id);

            /** @var User $user */
            $user = Auth::user();

            // Only admin can delete requests
            if (!$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can delete requests'
                ], 403);
            }

            DB::beginTransaction();

            // Jika request masih approved, kembalikan stok dulu
            if ($itemRequest->status === 'approved') {
                foreach ($itemRequest->details as $detail) {
                    if ($detail->status === 'approved') {
                        $product = $detail->product;
                        $product->increment('stock_quantity', $detail->approved_quantity);
                    }
                }
            }

            $itemRequest->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a request (for users)
     */
    public function cancel(ItemRequest $itemRequest)
    {
        if ($itemRequest->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu tidak dapat membatalkan request.'
            ], 403);
        }

        // Jalankan transaksi supaya stok dan log konsisten
        DB::transaction(function () use ($itemRequest) {
            // Kembalikan stok tiap produk
            foreach ($itemRequest->details as $detail) {
                // pastikan relasi product sudah ada
                if ($detail->product) {
                    $detail->product->increment('stock_quantity', $detail->requested_quantity);
                }
                $detail->update(['status' => 'cancelled']);
            }

            // Update status di header request
            $oldData = $itemRequest->toArray();
            $itemRequest->update(['status' => 'cancelled']);

            // Simpan log
            $itemRequest->logs()->create([
                'user_id'    => Auth::id(),
                'action'     => 'cancelled',
                'old_data'   => $oldData,
                'new_data'   => $itemRequest->toArray(),
                'description' => 'Request dibatalkan oleh user'
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Request berhasil dibatalkan dan stok dikembalikan!'
        ]);
    }


    /**
     * Get request statistics
     */
    public function stats(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $userId = $user->id;
            $isAdmin = $user->hasRole('admin');

            if ($isAdmin) {
                $stats = [
                    'total_requests' => ItemRequest::count(),
                    'approved_requests' => ItemRequest::where('status', 'approved')->count(),
                    'cancelled_requests' => ItemRequest::where('status', 'cancelled')->count(),
                    'total_items_requested' => ItemRequestDetail::sum('requested_quantity'),
                    'total_items_approved' => ItemRequestDetail::sum('approved_quantity')
                ];
            } else {
                $stats = [
                    'my_total_requests' => ItemRequest::byUser($userId)->count(),
                    'my_approved_requests' => ItemRequest::byUser($userId)->where('status', 'approved')->count(),
                    'my_cancelled_requests' => ItemRequest::byUser($userId)->where('status', 'cancelled')->count()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
