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
        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->unsignedBigInteger('webhook_id');
            $table->unsignedBigInteger('delivery_id');
            $table->string('topic', 64);
            $table->unsignedBigInteger('external_resource_id');
            $table->char('payload_hash', 64);
            $table->string('status', 32);
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['provider', 'webhook_id', 'delivery_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
    }
};
