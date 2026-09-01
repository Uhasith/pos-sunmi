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
            $table->unsignedBigInteger('woocommerce_product_id')->nullable()->after('tax_rate_id');
            $table->unsignedBigInteger('woocommerce_variation_id')->nullable()->unique()->after('woocommerce_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['woocommerce_product_id', 'woocommerce_variation_id']);
        });
    }
};
