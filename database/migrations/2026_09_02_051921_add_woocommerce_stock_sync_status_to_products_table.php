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
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('woocommerce_stock_synced_at')->nullable()->after('woocommerce_variation_id');
            $table->timestamp('woocommerce_stock_sync_failed_at')->nullable()->after('woocommerce_stock_synced_at');
            $table->string('woocommerce_stock_sync_failure_code')->nullable()->after('woocommerce_stock_sync_failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'woocommerce_stock_synced_at',
                'woocommerce_stock_sync_failed_at',
                'woocommerce_stock_sync_failure_code',
            ]);
        });
    }
};
