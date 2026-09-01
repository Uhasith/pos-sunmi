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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable()->unique();
            $table->string('source');
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('device_uuid');
            $table->string('device_name');
            $table->foreignId('cashier_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cashier_name');
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_excluding_tax_cents');
            $table->unsignedBigInteger('tax_9_cents');
            $table->unsignedBigInteger('tax_21_cents');
            $table->unsignedBigInteger('tax_total_cents');
            $table->unsignedBigInteger('total_including_tax_cents');
            $table->string('status');
            $table->string('payment_status');
            $table->string('payment_method');
            $table->uuid('idempotency_key');
            $table->char('payload_fingerprint', 64);
            $table->timestamp('ordered_at');
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->unique(['device_id', 'idempotency_key']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_manual');
            $table->string('article_number')->nullable();
            $table->string('description');
            $table->decimal('quantity', total: 15, places: 3);
            $table->unsignedBigInteger('unit_price_including_tax_cents');
            $table->unsignedSmallInteger('tax_rate_basis_points');
            $table->unsignedBigInteger('line_excluding_tax_cents');
            $table->unsignedBigInteger('tax_amount_cents');
            $table->unsignedBigInteger('line_including_tax_cents');
            $table->timestamps();

            $table->unique(['order_id', 'position']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('method');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('tendered_amount_cents');
            $table->unsignedBigInteger('change_due_cents');
            $table->char('currency', 3);
            $table->string('status');
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('order_item_id')
                ->nullable()
                ->unique()
                ->after('actor_user_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
        });

        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
