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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');                      // Nama Lengkap
            $table->string('username')->unique();        // Username unik
            $table->string('email')->unique();           // Email unik
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');                  // Password login
            $table->boolean('is_blocked')->default(false); // Status blokir/unblock
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null'); // 1 user = 1 role
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
