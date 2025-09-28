<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmailSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            Log::info('Fetching email settings');

            $settings = EmailSettings::first();

            if (!$settings) {
                // Create default settings if not exists
                $settings = EmailSettings::create([
                    'admin_email' => 'aribiya@gmail.com',
                    'cc_emails' => [],
                    'request_notifications' => true,
                    'low_stock_notifications' => true,
                    'low_stock_threshold' => 10
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'adminEmail' => $settings->admin_email,
                    'ccEmails' => $settings->cc_emails ?? [],
                    'requestNotifications' => (bool) $settings->request_notifications,
                    'lowStockNotifications' => (bool) $settings->low_stock_notifications,
                    'lowStockThreshold' => (int) $settings->low_stock_threshold
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching email settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve email settings: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            Log::info('Updating email settings', $request->all());

            $validator = Validator::make($request->all(), [
                'adminEmail' => 'required|email',
                'ccEmails' => 'nullable|array',
                'ccEmails.*' => 'email',
                'requestNotifications' => 'boolean',
                'lowStockNotifications' => 'boolean',
                'lowStockThreshold' => 'integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $settings = EmailSettings::first();

            if (!$settings) {
                $settings = new EmailSettings();
            }

            $settings->admin_email = $request->adminEmail;
            $settings->cc_emails = $request->ccEmails ?? [];
            $settings->request_notifications = $request->requestNotifications ?? true;
            $settings->low_stock_notifications = $request->lowStockNotifications ?? true;
            $settings->low_stock_threshold = $request->lowStockThreshold ?? 10;

            $settings->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'adminEmail' => $settings->admin_email,
                    'ccEmails' => $settings->cc_emails,
                    'requestNotifications' => (bool) $settings->request_notifications,
                    'lowStockNotifications' => (bool) $settings->low_stock_notifications,
                    'lowStockThreshold' => (int) $settings->low_stock_threshold
                ],
                'message' => 'Email settings updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating email settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email settings: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testEmail(): JsonResponse
    {
        try {
            $settings = EmailSettings::first();

            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email settings not found'
                ], 404);
            }

            // Send test email
            Mail::raw('Ini adalah email pengujian dari sistem Inventopia. Pengaturan email berfungsi dengan baik.', function ($message) use ($settings) {
                $message->to($settings->admin_email)
                    ->subject('Test Email - Inventopia System');
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $settings->admin_email
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending test email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }
}
