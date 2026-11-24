<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 180)->unique();
            $table->string('password');
            $table->string('role', 20)->default('customer');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('admin', 'customer'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
