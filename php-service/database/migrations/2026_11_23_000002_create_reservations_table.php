<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->string('status', 20)->default('pending');
            $table->timestampTz('expires_at');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE reservations ADD CONSTRAINT chk_reservations_quantity CHECK (quantity BETWEEN 1 AND 10)');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT chk_reservations_status CHECK (status IN ('pending', 'confirmed', 'expired', 'cancelled'))");
        // Índices parciales: solo una reserva activa por usuario/evento, y el job de expiración recorre únicamente las pendientes
        DB::statement("CREATE UNIQUE INDEX uq_reservations_active ON reservations (user_id, event_id) WHERE status = 'pending'");
        DB::statement("CREATE INDEX idx_reservations_expiring ON reservations (expires_at) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
