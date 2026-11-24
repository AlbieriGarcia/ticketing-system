<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            // UNIQUE: una reserva no puede cobrarse dos veces
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 20)->default('completed');
            $table->timestampTz('purchase_date')->useCurrent();

            $table->index(['user_id', 'purchase_date']);
            $table->index(['event_id', 'status']);
        });

        DB::statement('ALTER TABLE ticket_sales ADD CONSTRAINT chk_sales_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE ticket_sales ADD CONSTRAINT chk_sales_total CHECK (total_amount = quantity * unit_price)');
        DB::statement("ALTER TABLE ticket_sales ADD CONSTRAINT chk_sales_status CHECK (status IN ('completed', 'refunded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sales');
    }
};
