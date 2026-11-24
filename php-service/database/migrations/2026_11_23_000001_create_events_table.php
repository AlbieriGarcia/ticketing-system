<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description');
            $table->timestampTz('event_date');
            $table->decimal('ticket_price', 12, 2);
            $table->integer('total_tickets');
            $table->integer('available_tickets');
            $table->string('status', 20)->default('draft');
            $table->timestampsTz();

            $table->index(['status', 'event_date']);
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT chk_events_status CHECK (status IN ('draft', 'published', 'cancelled', 'finished'))");
        DB::statement('ALTER TABLE events ADD CONSTRAINT chk_events_price CHECK (ticket_price >= 0)');
        DB::statement('ALTER TABLE events ADD CONSTRAINT chk_events_total CHECK (total_tickets > 0)');
        // Última barrera contra la sobreventa, independiente del código de la aplicación
        DB::statement('ALTER TABLE events ADD CONSTRAINT chk_events_available CHECK (available_tickets BETWEEN 0 AND total_tickets)');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
