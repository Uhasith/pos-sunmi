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
        Schema::table('devices', function (Blueprint $table) {
            $table->string('printer_model')->nullable()->after('name');
            $table->string('printer_ip_address', 45)->nullable()->after('printer_model');
            $table->unsignedSmallInteger('printer_port')->nullable()->after('printer_ip_address');
            $table->unsignedTinyInteger('printer_paper_width')->nullable()->after('printer_port');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'printer_model',
                'printer_ip_address',
                'printer_port',
                'printer_paper_width',
            ]);
        });
    }
};
