<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/web-login', [AuthController::class, 'login']);
Route::get('/api/test-cors', function () {
    return response()->json(['message' => 'CORS OK']);
});
