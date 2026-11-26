<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Traza de la operación que originó el mensaje; el relay la reenvía como header AMQP
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->string('traceparent', 55)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropColumn('traceparent');
        });
    }
};
