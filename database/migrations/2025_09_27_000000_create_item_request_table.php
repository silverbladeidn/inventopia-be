<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabel utama untuk request
        Schema::create('item_request', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique(); // Format: REQ-YYYYMMDD-001
            $table->unsignedBigInteger('user_id'); // User yang membuat request
            $table->text('note')->nullable(); // Catatan dari user
            $table->enum('status', [
                'draft',
                'pending',
                'approved',
                'rejected',
                'partially_approved',
                'completed'
            ])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable(); // Admin yang approve
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_note')->nullable(); // Catatan dari admin
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('request_number');
            $table->index('status');
            $table->index('created_at');
        });

        // Tabel detail untuk items dalam request
        Schema::create('item_request_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_request_id'); // Reference ke tabel utama
            $table->unsignedBigInteger('product_id'); // Product yang di-request
            $table->integer('requested_quantity'); // Jumlah yang diminta
            $table->integer('approved_quantity')->default(0); // Jumlah yang disetujui
            $table->enum('status', [
                'draft',
                'pending',
                'approved',
                'rejected',
                'cancelled',
                'partially_approved',
                'completed'
            ])->default('pending');
            $table->text('note')->nullable(); // Catatan khusus untuk item ini
            $table->timestamps();

            // Foreign keys
            $table->foreign('item_request_id')->references('id')->on('item_request')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            // Indexes
            $table->index(['item_request_id', 'product_id']);
            $table->index('product_id');
            $table->index('status');

            // Ensure one product per request (prevent duplicate)
            $table->unique(['item_request_id', 'product_id']);
        });

        // Tabel untuk tracking history/log aktivitas
        Schema::create('item_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_request_id');
            $table->unsignedBigInteger('user_id'); // User yang melakukan aksi
            $table->string('action'); // created, approved, rejected, updated, etc.
            $table->json('old_data')->nullable(); // Data sebelum perubahan
            $table->json('new_data')->nullable(); // Data setelah perubahan
            $table->text('description')->nullable(); // Deskripsi aksi
            $table->timestamps();

            // Foreign keys
            $table->foreign('item_request_id')->references('id')->on('item_request')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['item_request_id', 'created_at']);
            $table->index('user_id');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_request_logs');
        Schema::dropIfExists('item_request_details');
        Schema::dropIfExists('item_request');
    }
};
