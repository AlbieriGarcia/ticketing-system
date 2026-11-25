<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            // El id hace de secuencia: los consumidores lo usan para descartar mensajes viejos o duplicados
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('type', 100);
            $table->jsonb('payload');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('published_at')->nullable();
        });

        // El relay solo recorre los pendientes, que son pocos frente al histórico
        DB::statement('CREATE INDEX idx_outbox_pending ON outbox_messages (id) WHERE published_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
